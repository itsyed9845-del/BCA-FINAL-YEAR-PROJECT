'use strict';

function sfAddressEntry() {
    document.querySelectorAll('[data-address-entry]').forEach(entry => {
        if (entry.dataset.initialized) return;
        entry.dataset.initialized = '1';
        const address = entry.querySelector('[data-address-text]');
        const postcode = entry.querySelector('[data-address-postcode]');
        const lat = entry.querySelector('[data-address-lat]');
        const lng = entry.querySelector('[data-address-lng]');
        const button = entry.querySelector('[data-address-current]');
        const status = entry.querySelector('[data-address-status]');
        let generation = 0;
        let pending = false;
        let controller;
        function finish() {
            pending = false;
            button.disabled = false;
            button.textContent = 'Use current location';
        }
        function manualEdit() {
            generation++;
            controller?.abort();
            lat.value = '';
            lng.value = '';
            finish();
            status.textContent = 'Your typed address will be used. Add your house number in the landmark field if needed.';
        }
        address.addEventListener('input', manualEdit);
        postcode.addEventListener('input', manualEdit);
        entry.closest('form')?.addEventListener('submit', event => {
            if (pending) {
                event.preventDefault();
                status.textContent = 'Finding your address. Wait a moment, or type an address to cancel the lookup.';
            }
        });
        button.addEventListener('click', () => {
            if (!navigator.geolocation || !window.isSecureContext) {
                status.textContent = 'Location needs localhost or HTTPS. You can enter your address manually.';
                return;
            }
            entry.dispatchEvent(new Event('input', { bubbles: true }));
            const request = ++generation;
            pending = true;
            button.disabled = true;
            button.textContent = 'Finding address…';
            status.textContent = 'Allow location access when asked. You can still type your address instead.';
            navigator.geolocation.getCurrentPosition(async position => {
                if (request !== generation) return;
                controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 15000);
                try {
                    const body = new FormData();
                    body.set('csrf_token', window.SmartFuel.csrf);
                    body.set('latitude', position.coords.latitude);
                    body.set('longitude', position.coords.longitude);
                    const data = await sfJson(window.SmartFuel.base + '/api/reverse_address.php', {
                        method: 'POST', body, signal: controller.signal
                    });
                    if (request !== generation) return;
                    address.value = data.address;
                    postcode.value = data.pincode || '';
                    lat.value = String(position.coords.latitude);
                    lng.value = String(position.coords.longitude);
                    // Notify dashboard sync that this form contains unsaved changes.
                    entry.dispatchEvent(new Event('input', { bubbles: true }));
                    status.textContent = data.pincode
                        ? 'Address filled. Check it and add your house number or landmark before saving.'
                        : 'Address filled. Enter the six-digit pincode and check your house number before saving.';
                } catch (error) {
                    if (request === generation) status.textContent = 'Could not find your address. Please type it manually; your existing text is unchanged.';
                } finally {
                    clearTimeout(timeout);
                    if (request === generation) finish();
                }
            }, () => {
                if (request !== generation) return;
                finish();
                status.textContent = 'Location unavailable or permission denied. Please enter your address manually.';
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
        });
    });
}

document.addEventListener('DOMContentLoaded', sfAddressEntry);
