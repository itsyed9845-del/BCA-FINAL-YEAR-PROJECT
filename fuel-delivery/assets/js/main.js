document.addEventListener('DOMContentLoaded', () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(pointer: fine)').matches;

    initNavigation();
    initSidebar();
    initToasts();
    initPasswordToggles();
    initOrderCalculator();
    initInvoiceModals();
    initTableFilters();
    initSortableTables();
    initDutyToggle();
    initOrderChats();
    initCounters(reducedMotion);

    if (finePointer && !reducedMotion) {
        initMagneticButtons();
        initTiltCards();
    }
});

function initNavigation() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-main-nav]');

    toggle?.addEventListener('click', () => nav?.classList.toggle('open'));
}

function initSortableTables() {
    document.querySelectorAll('table').forEach((table) => {
        table.querySelectorAll('thead th').forEach((heading, columnIndex) => {
            heading.style.cursor = 'pointer';
            heading.title = 'Click to sort';
            let ascending = true;

            heading.addEventListener('click', () => {
                const body = table.tBodies[0];
                if (!body) return;
                const rows = Array.from(body.rows);
                rows.sort((first, second) => {
                    const a = first.cells[columnIndex]?.textContent.trim() || '';
                    const b = second.cells[columnIndex]?.textContent.trim() || '';
                    return a.localeCompare(b, undefined, { numeric: true }) * (ascending ? 1 : -1);
                });
                rows.forEach((row) => body.appendChild(row));
                ascending = !ascending;
            });
        });
    });
}

function initDutyToggle() {
    const form = document.querySelector('[data-duty-form]');
    const button = form?.querySelector('[data-duty-button]');

    if (!form || !button) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Could not update duty status.');
            button.classList.toggle('online', Boolean(result.online));
            button.querySelector('span').textContent = result.online ? 'Go Offline' : 'Go Online';
        } catch (error) {
            alert(error.message || 'Connection interrupted. Refresh to check your current duty status.');
        } finally {
            button.disabled = false;
        }
    });
}

function initOrderChats() {
    document.querySelectorAll('[data-order-chat]').forEach((chat) => {
        if (chat.dataset.initialized) return;
        chat.dataset.initialized = '1';
        const endpoint = chat.dataset.chatUrl;
        const code = chat.dataset.chatCode;
        const messagesBox = chat.querySelector('[data-chat-messages]');
        const form = chat.querySelector('[data-chat-form]');
        const input = form?.querySelector('input[name="message"]');
        let lastMessageId = 0;

        const render = (result) => {
            if (!messagesBox || !Array.isArray(result.messages)) return;
            const newestId = Number(result.messages.at(-1)?.id || 0);
            if (newestId === lastMessageId && messagesBox.querySelector('.chat-bubble')) return;
            lastMessageId = newestId;
            messagesBox.replaceChildren();

            if (!result.messages.length) {
                const empty = document.createElement('span');
                empty.className = 'chat-empty';
                empty.textContent = 'No messages yet. Start the secure order conversation.';
                messagesBox.append(empty);
            }

            result.messages.forEach((message) => {
                const bubble = document.createElement('div');
                bubble.className = `chat-bubble ${Number(message.sender_id) === Number(result.current_user_id) ? 'mine' : 'theirs'}`;
                const author = document.createElement('strong');
                const text = document.createElement('p');
                const time = document.createElement('small');
                author.textContent = Number(message.sender_id) === Number(result.current_user_id) ? 'You' : message.full_name;
                text.textContent = message.message;
                time.textContent = new Date(message.created_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                bubble.append(author, text, time);
                messagesBox.append(bubble);
            });
            messagesBox.scrollTop = messagesBox.scrollHeight;
            if (result.closed && input) {
                input.disabled = true;
                input.placeholder = 'This order conversation is closed';
            }
        };

        const load = async () => {
            if (!chat.isConnected) {
                clearInterval(timer);
                return;
            }
            if (document.hidden) return;
            try {
                const response = await fetch(`${endpoint}?code=${encodeURIComponent(code)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                const result = await response.json();
                if (response.ok && result.success) render(result);
            } catch (error) {
                // The next polling cycle retries automatically.
            }
        };

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!input?.value.trim()) return;
            const formData = new FormData(form);
            formData.append('code', code);
            try {
                const response = await fetch(endpoint, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Message failed.');
                input.value = '';
                render(result);
            } catch (error) {
                input.setCustomValidity(error.message);
                input.reportValidity();
                window.setTimeout(() => input.setCustomValidity(''), 2500);
            }
        });

        chat.querySelectorAll('[data-quick-message]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!input) return;
                input.value = button.dataset.quickMessage;
                input.focus();
            });
        });

        const timer = window.setInterval(load, 5000);
        load();
    });
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');

    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => sidebar?.classList.add('open'));
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => sidebar?.classList.remove('open'));
}

function initToasts() {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const close = () => toast.remove();
        toast.querySelector('button')?.addEventListener('click', close);
        window.setTimeout(close, 5000);
    });
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.textContent = 'Show';
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            button.textContent = hidden ? 'Hide' : 'Show';
            button.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        });
    });
}

function initOrderCalculator() {
    const calculator = document.querySelector('[data-order-calculator]');
    if (!calculator) return;

    const fuel = calculator.querySelector('[data-fuel-select]');
    const slider = calculator.querySelector('[data-liter-slider]');
    const number = calculator.querySelector('[data-liter-input]');
    const priceOutput = calculator.querySelector('[data-unit-price]');
    const subtotalOutput = calculator.querySelector('[data-subtotal]');
    const feeOutput = calculator.querySelector('[data-fee]');
    const taxOutput = calculator.querySelector('[data-tax]');
    const totalOutput = calculator.querySelector('[data-total]');

    const format = (value) => `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const calculate = () => {
        const selected = fuel?.options[fuel.selectedIndex];
        const price = Number(selected?.dataset.price || 0);
        const liters = Math.max(1, Number(number?.value || slider?.value || 1));
        const deliveryFee = liters >= 20 ? 79 : 129;
        const subtotal = price * liters;
        const tax = subtotal * 0.05;
        const total = subtotal + deliveryFee + tax;

        if (slider) slider.value = String(liters);
        if (number) number.value = String(liters);
        if (priceOutput) priceOutput.textContent = format(price);
        if (subtotalOutput) subtotalOutput.textContent = format(subtotal);
        if (feeOutput) feeOutput.textContent = format(deliveryFee);
        if (taxOutput) taxOutput.textContent = format(tax);
        if (totalOutput) totalOutput.textContent = format(total);
    };

    fuel?.addEventListener('change', calculate);
    slider?.addEventListener('input', () => {
        if (number) number.value = slider.value;
        calculate();
    });
    number?.addEventListener('input', () => {
        if (slider) slider.value = number.value;
        calculate();
    });
    calculate();
}

function initInvoiceModals() {
    document.querySelectorAll('[data-invoice-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.invoiceOpen);
            modal?.classList.add('open');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.invoice-modal')?.classList.remove('open'));
    });

    document.querySelectorAll('.invoice-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) modal.classList.remove('open');
        });
    });
}

function initTableFilters() {
    document.querySelectorAll('[data-table-filter]').forEach((filter) => {
        filter.addEventListener('input', () => {
            const table = document.querySelector(filter.dataset.tableFilter);
            const term = filter.value.toLowerCase().trim();
            table?.querySelectorAll('tbody tr').forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
            });
        });
    });
}

function initCounters(reducedMotion) {
    document.querySelectorAll('[data-counter]').forEach((element) => {
        const target = Number(element.dataset.counter || 0);
        const prefix = element.dataset.prefix || '';
        const suffix = element.dataset.suffix || '';
        const decimals = Number(element.dataset.decimals || 0);
        const duration = reducedMotion ? 0 : 1100;
        const start = performance.now();

        const tick = (time) => {
            const progress = duration === 0 ? 1 : Math.min((time - start) / duration, 1);
            const value = target * (1 - Math.pow(1 - progress, 3));
            element.textContent = `${prefix}${value.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}${suffix}`;
            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    });
}

function initMagneticButtons() {
    document.querySelectorAll('[data-magnetic], .btn').forEach((button) => {
        button.addEventListener('pointermove', (event) => {
            const rect = button.getBoundingClientRect();
            const x = (event.clientX - rect.left - rect.width / 2) * 0.14;
            const y = (event.clientY - rect.top - rect.height / 2) * 0.18;
            button.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        });
        button.addEventListener('pointerleave', () => {
            button.style.transition = 'transform 500ms cubic-bezier(.2,.8,.2,1)';
            button.style.transform = 'translate3d(0,0,0)';
            window.setTimeout(() => button.style.removeProperty('transition'), 520);
        });
    });
}

function initTiltCards() {
    document.querySelectorAll('[data-tilt]').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - 0.5;
            const y = (event.clientY - rect.top) / rect.height - 0.5;
            card.style.transform = `perspective(900px) rotateX(${-y * 3}deg) rotateY(${x * 4}deg)`;
        });
        card.addEventListener('pointerleave', () => {
            card.style.transform = 'perspective(900px) rotateX(0) rotateY(0)';
        });
    });
}
