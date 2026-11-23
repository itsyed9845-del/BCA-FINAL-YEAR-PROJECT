'use strict';

const sf = window.SmartFuel;
const sfHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

let sfDismissToast;
function sfToast(message, target, onDismiss = () => {}) {
    if (sfDismissToast) sfDismissToast();
    let region = document.querySelector('.sf-toast-region');
    if (!region) {
        region = document.createElement('div');
        region.className = 'sf-toast-region';
        region.setAttribute('aria-live', 'polite');
        document.body.append(region);
    }
    const notice = document.createElement('div');
    notice.className = 'sf-toast';
    const link = document.createElement('a');
    link.textContent = message;
    link.href = sf.base + '/' + (target || 'account/notifications.php');
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Dismiss notification');
    let dismissed = false;
    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        clearTimeout(timer);
        notice.remove();
        if (sfDismissToast === dismiss) sfDismissToast = null;
        onDismiss();
    };
    const timer = setTimeout(dismiss, 5000);
    sfDismissToast = dismiss;
    close.onclick = dismiss;
    link.addEventListener('click', dismiss);
    notice.append(link, close);
    region.append(notice);
}

async function sfReadNotifications(ids) {
    if (!ids.length) return;
    const body = new FormData();
    body.set('csrf_token', sf.csrf);
    ids.forEach(id => body.append('ids[]', id));
    const data = await sfJson(sf.base + '/api/notification_read.php', {
        method: 'POST', body, keepalive: true
    });
    document.querySelectorAll('[data-unread]').forEach(node => node.textContent = data.unread);
}

function sfNotificationInbox() {
    const inbox = document.querySelector('[data-notification-inbox]');
    if (!inbox) return;
    const rows = [...inbox.querySelectorAll('[data-notification-id]')];
    if (!rows.length) return;
    let timer;
    let completed = false;
    const schedule = () => {
        clearTimeout(timer);
        if (document.hidden || completed) return;
        timer = setTimeout(async () => {
            try {
                await sfReadNotifications(rows.map(row => row.dataset.notificationId));
                completed = true;
                rows.forEach(row => row.remove());
                inbox.querySelector('[data-inbox-empty]').hidden = false;
            } catch (_) {
                inbox.querySelector('[data-inbox-state]').textContent = 'Could not clear notifications. Use Mark all read or refresh to retry.';
            }
        }, 5000);
    };
    document.addEventListener('visibilitychange', schedule);
    schedule();
}

async function sfJson(path, options = {}) {
    const response = await fetch(path, { cache: 'no-store', headers: sfHeaders, ...options });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Connection unavailable.');
    return data;
}

function sfCreateMap(element) {
    if (!window.L || !element) return null;
    const map = L.map(element, { scrollWheelZoom: false }).setView([22.5, 79], 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    return map;
}

function sfGps() {
    const form = document.querySelector('[data-location-form]');
    if (!form) return;
    const button = form.querySelector('[data-location-button]');
    const label = button.querySelector('span');
    const status = form.querySelector('[data-location-status]');
    const key = 'sf-gps-' + sf.userId;
    let watch = null;
    let heartbeat = null;
    let lastSent = 0;
    let sending = false;
    let active = false;
    const stop = async () => {
        active = false;
        if (watch !== null) navigator.geolocation.clearWatch(watch);
        clearInterval(heartbeat);
        watch = null;
        sessionStorage.removeItem(key);
        label.textContent = 'Share GPS';
        button.classList.remove('location-active');
        status.textContent = 'GPS off';
        const body = new FormData(form);
        body.set('stop','1');
        try {
            await sfJson(form.action,{method:'POST',body});
        } catch (_) {
            status.textContent = 'GPS stopped; last fix expires within 90 seconds.';
        }
    };
    const received = async position => {
        if (!active || sending || Date.now()-lastSent<5000 || Date.now()-position.timestamp>30000) return;
        sending = true;
        lastSent = Date.now();
        const body = new FormData(form);
        body.set('latitude',position.coords.latitude);
        body.set('longitude',position.coords.longitude);
        body.set('accuracy',position.coords.accuracy);
        try {
            await sfJson(form.action,{method:'POST',body});
            if (active) status.textContent = 'GPS shared · ±' + Math.round(position.coords.accuracy) + ' m';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            sending = false;
        }
    };
    const failure = () => {
        status.textContent = 'GPS unavailable. Check location permission; stale fixes expire.';
    };
    const start = () => {
        if (!navigator.geolocation || !window.isSecureContext) {
            status.textContent = 'Use localhost or HTTPS and allow location.';
            return;
        }
        active = true;
        sessionStorage.setItem(key,'1');
        label.textContent = 'Stop GPS';
        button.classList.add('location-active');
        status.textContent = 'Finding GPS…';
        const options = { enableHighAccuracy:true,timeout:12000,maximumAge:0 };
        watch = navigator.geolocation.watchPosition(received,failure,options);
        // Obtain a fresh fix while stationary; never re-date an old cached fix.
        heartbeat = setInterval(() => {
            if (active && !document.hidden) navigator.geolocation.getCurrentPosition(received,failure,options);
        },15000);
    };
    button.addEventListener('click', () => active ? stop() : start());
    if (sessionStorage.getItem(key)==='1') start();
}

function sfTracking() {
    document.querySelectorAll('[data-real-tracking]').forEach(panel => {
        if (panel.dataset.initialized) return;
        panel.dataset.initialized = '1';
        const map = sfCreateMap(panel.querySelector('[data-delivery-map]'));
        let rider;
        let destination;
        let fitted = false;
        let busy = false;
        const text = (selector,value) => {
            const node = panel.querySelector(selector);
            if (node) node.textContent = value;
        };
        const clearRider = () => {
            if (map && rider) map.removeLayer(rider);
            rider = null;
        };
        const refresh = async () => {
            if (!panel.isConnected) {
                clearInterval(timer);
                map?.remove();
                return;
            }
            if (busy || document.hidden) return;
            busy = true;
            try {
                const data = await sfJson(sf.base+'/api/tracking.php?code='+encodeURIComponent(panel.dataset.code));
                text('[data-gps-state]',data.live ? 'GPS live' : 'GPS unavailable · '+data.status);
                document.querySelectorAll('[data-live-status]').forEach(node => node.textContent=data.status);
                text('[data-real-agent]',data.agent.name || 'Awaiting assignment');
                text('[data-real-age]',data.gps_age === null ? 'No signal' : data.gps_age+' seconds ago');
                text('[data-real-accuracy]',data.accuracy === null ? '—' : '±'+Math.round(data.accuracy)+' m');
                text('[data-real-eta]', data.eta_minutes === null ? (['Completed','Cancelled'].includes(data.status) ? data.status : 'Waiting for GPS / destination')
                    : data.eta_minutes === 0 ? 'Partner has arrived'
                    : 'About '+data.eta_minutes+' min · '+new Date(Date.now()+data.eta_minutes*60000).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}));
                text('[data-coordinate-fallback]',data.position ? 'Partner location updated on the map.' : 'Live location appears after dispatch while the partner shares fresh GPS.');
                const call = panel.querySelector('[data-agent-call]');
                if (call) {
                    call.hidden = !data.agent.phone;
                    if (data.agent.phone) call.href='tel:'+data.agent.phone;
                }
                if (map && data.destination) {
                    if (!destination) destination = L.circleMarker(data.destination,{radius:9,color:'#f59e0b',fillOpacity:1}).addTo(map).bindTooltip('Your destination');
                    else destination.setLatLng(data.destination);
                }
                if (map && data.live && data.position) {
                    if (!rider) rider = L.marker(data.position,{
                        icon:L.divIcon({className:'sf-rider-pin',html:'<span aria-label="Fuel delivery partner">🏍</span>',iconSize:[42,42],iconAnchor:[21,21]})
                    }).addTo(map).bindTooltip('Fuel delivery partner');
                    else rider.setLatLng(data.position);
                    if (!fitted) {
                        map.fitBounds(L.latLngBounds(data.destination ? [data.position,data.destination] : [data.position,data.position]),{padding:[40,40],maxZoom:16});
                        fitted=true;
                    }
                } else clearRider();
                const history = panel.querySelector('[data-real-history]');
                if (history) {
                    history.replaceChildren(...data.history.map(event => {
                        const item=document.createElement('li');
                        const title=document.createElement('strong');
                        title.textContent=event.order_status;
                        const time=document.createElement('small');
                        time.textContent=event.recorded_at;
                        item.append(title,time);
                        return item;
                    }));
                }
            } catch (_) {
                clearRider();
                text('[data-gps-state]','Connection interrupted');
                text('[data-real-eta]','Estimate paused');
                text('[data-coordinate-fallback]','Connection interrupted. Location will update when connectivity returns.');
            } finally { busy=false; }
        };
        const timer=setInterval(refresh,5000);
        refresh();
    });
}

function sfLiveUpdates() {
    let revision;
    let busy=false;
    let dirty=false;
    let seen=Number(sessionStorage.getItem('sf-notifications-'+sf.userId) || 0);
    document.addEventListener('input',event=>{
        const form=event.target.closest('[data-sync-content] form');
        if (form && form.method.toLowerCase()==='post' && !form.matches('[data-chat-form]')) dirty=true;
    });
    const refreshContent = async () => {
        const safePages=['dashboard.php','manage_orders.php','manage_users.php','financial_reports.php','earnings.php','order_history.php','inventory.php','active_deliveries.php','track_order.php'];
        if (!safePages.includes(location.pathname.split('/').pop())) return;
        if (dirty || document.activeElement?.matches('input,select,textarea') || document.querySelector('.invoice-modal.open')) {
            sfToast('Updates available. Finish your changes, then refresh this page.',location.pathname.slice(sf.base.length+1)+location.search);
            return;
        }
        const response=await fetch(location.href,{headers:sfHeaders,cache:'no-store'});
        if (!response.ok) return;
        const parsed=new DOMParser().parseFromString(await response.text(),'text/html');
        const content=parsed.querySelector('[data-sync-content]');
        const current=document.querySelector('[data-sync-content]');
        if (!content || !current) return;
        current.replaceChildren(...content.childNodes);
        initCounters(true);
        initToasts();
        initInvoiceModals();
        initTableFilters();
        initSortableTables();
        initDutyToggle();
        initOrderChats();
        sfTracking();
        sfAddressEntry();
    };
    const poll=async()=>{
        if (busy || document.hidden) return;
        busy=true;
        try {
            const data=await sfJson(sf.base+'/api/live.php');
            document.querySelectorAll('[data-unread]').forEach(node=>node.textContent=data.unread);
            const fresh=data.notifications.filter(item=>Number(item.id)>seen).reverse();
            if (fresh.length && !document.querySelector('[data-notifications-page]')) {
                const latest = fresh[fresh.length - 1];
                sfToast(fresh.length === 1 ? latest.message : fresh.length + ' new notifications. Open your activity feed.',
                    fresh.length === 1 ? latest.target : 'account/notifications.php',
                    () => sfReadNotifications(fresh.map(item => item.id)).catch(() => {}));
            }
            if (fresh.length) {
                seen=Math.max(seen,...fresh.map(item=>Number(item.id)));
                sessionStorage.setItem('sf-notifications-'+sf.userId,String(seen));
            }
            if (revision && revision!==data.revision) await refreshContent();
            revision=data.revision;
        } catch (_) {
            document.querySelectorAll('[data-unread]').forEach(node=>node.textContent='Offline');
        } finally { busy=false; }
    };
    poll();
    setInterval(poll,5000);
}

document.addEventListener('DOMContentLoaded',()=>{
    sfNotificationInbox();
    sfGps();
    sfTracking();
    sfLiveUpdates();
});
