(function () {
    'use strict';

    const icons = {
        success: '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',
        error: '<svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>',
        info: '<svg viewBox="0 0 24 24"><path d="M12 16v-4m0-4h.01m9 4a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    };

    const region = () => document.querySelector('[data-notification-region]');
    const snackbarRegion = () => document.querySelector('[data-snackbar-region]');
    const toastTones = {
        success: { border: 'border-l-[#397565]', icon: 'bg-[#C6F24E] text-[#121017]', progress: 'bg-[#397565]', title: 'All set' },
        error: { border: 'border-l-[#FF6B2C]', icon: 'bg-[#FF6B2C]/12 text-[#D64A12]', progress: 'bg-[#FF6B2C]', title: 'Sign-in failed' },
        warning: { border: 'border-l-[#FF6B2C]', icon: 'bg-[#FF6B2C]/12 text-[#D64A12]', progress: 'bg-[#FF6B2C]', title: 'Please check this' },
        info: { border: 'border-l-[#2F3AE0]', icon: 'bg-[#2F3AE0]/10 text-[#2F3AE0]', progress: 'bg-[#2F3AE0]', title: 'Good to know' },
    };

    function toast(type, message, options = {}) {
        const host = region();
        if (!host || !message) return null;

        const kind = icons[type] ? type : 'info';
        const tone = toastTones[kind];
        const duration = options.duration || (kind === 'error' ? 6500 : 4200);
        const element = document.createElement('div');
        element.className = `pointer-events-auto relative grid min-h-[72px] grid-cols-[auto_1fr_auto] items-center gap-3 overflow-hidden rounded-2xl border border-l-[4px] border-[#121017]/10 ${tone.border} bg-white/95 p-3.5 shadow-[0_18px_50px_rgba(18,16,23,.18)] backdrop-blur-xl transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_22px_58px_rgba(18,16,23,.22)]`;
        element.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        element.innerHTML = `<span class="grid h-9 w-9 place-items-center rounded-full ${tone.icon} [&_svg]:h-4 [&_svg]:w-4 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[2.5]" aria-hidden="true">${icons[kind]}</span><span class="min-w-0"><strong class="block text-sm font-black tracking-[-.01em] text-[#121017]">${tone.title}</strong><span data-toast-message class="mt-0.5 block text-xs font-semibold leading-5 text-[#121017]/60"></span></span><button class="grid h-8 w-8 place-items-center rounded-lg border-0 bg-transparent text-lg text-[#121017]/35 transition hover:bg-[#121017]/6 hover:text-[#121017]" type="button" aria-label="Dismiss notification">&times;</button><span data-toast-progress class="absolute inset-x-0 bottom-0 h-1 origin-left ${tone.progress}"></span>`;
        element.querySelector('[data-toast-message]').textContent = message;
        host.appendChild(element);

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reducedMotion) {
            element.animate([
                { opacity: 0, transform: 'translate3d(18px,-14px,0) scale(.92)' },
                { opacity: 1, transform: 'translate3d(-3px,2px,0) scale(1.015)', offset: .72 },
                { opacity: 1, transform: 'translate3d(0,0,0) scale(1)' },
            ], { duration: 440, easing: 'cubic-bezier(.2,.9,.25,1)' });
            element.querySelector(':scope > span:first-child')?.animate([
                { transform: 'scale(.45) rotate(-18deg)' },
                { transform: 'scale(1.12) rotate(4deg)', offset: .7 },
                { transform: 'scale(1) rotate(0)' },
            ], { duration: 480, easing: 'cubic-bezier(.2,.9,.25,1)' });
        }
        element.querySelector('[data-toast-progress]')?.animate(
            [{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }],
            { duration, easing: 'linear', fill: 'forwards' },
        );
        let dismissed = false;
        const dismiss = () => {
            if (dismissed) return;
            dismissed = true;
            if (reducedMotion) {
                element.remove();
                return;
            }
            const exit = element.animate([
                { opacity: 1, transform: 'translate3d(0,0,0) scale(1)' },
                { opacity: 0, transform: 'translate3d(24px,-7px,0) scale(.94)' },
            ], { duration: 240, easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards' });
            exit.finished.then(() => element.remove()).catch(() => element.remove());
        };
        element.querySelector('button').addEventListener('click', dismiss);
        window.setTimeout(dismiss, duration);

        return element;
    }

    function confirmAction(options = {}) {
        const dialog = document.querySelector('[data-confirm-dialog]');
        if (!dialog) return Promise.resolve(window.confirm(options.message || 'Are you sure?'));

        dialog.querySelector('[data-confirm-title]').textContent = options.title || 'Confirm action';
        dialog.querySelector('[data-confirm-message]').textContent = options.message || 'Are you sure you want to continue?';
        const accept = dialog.querySelector('[data-confirm-accept]');
        const cancel = dialog.querySelector('[data-confirm-cancel]');
        accept.textContent = options.action || 'Confirm';

        return new Promise((resolve) => {
            const finish = (result) => {
                accept.removeEventListener('click', onAccept);
                cancel.removeEventListener('click', onCancel);
                dialog.removeEventListener('cancel', onCancel);
                if (dialog.open) dialog.close();
                resolve(result);
            };
            const onAccept = () => finish(true);
            const onCancel = (event) => {
                event?.preventDefault();
                finish(false);
            };

            accept.addEventListener('click', onAccept);
            cancel.addEventListener('click', onCancel);
            dialog.addEventListener('cancel', onCancel);
            dialog.showModal();
            cancel.focus();
        });
    }

    function undo(message, callback, options = {}) {
        const host = snackbarRegion();
        if (!host) return null;
        host.replaceChildren();

        const element = document.createElement('div');
        element.className = 'pointer-events-auto flex min-h-14 translate-y-2 items-center gap-3 rounded-xl bg-slate-800 px-4 py-2.5 text-sm text-white opacity-0 shadow-2xl transition duration-200';
        element.setAttribute('role', 'status');
        element.innerHTML = `<span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-300 text-emerald-950 [&_svg]:h-3.5 [&_svg]:w-3.5 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-2" aria-hidden="true">${icons.success}</span><span data-snackbar-message class="flex-1"></span><button class="rounded-lg bg-white/10 px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-emerald-300 hover:bg-white/15" type="button">Undo</button>`;
        element.querySelector('[data-snackbar-message]').textContent = message;
        host.appendChild(element);
        requestAnimationFrame(() => element.classList.remove('opacity-0', 'translate-y-2'));

        let timer;
        const dismiss = () => {
            window.clearTimeout(timer);
            element.classList.add('opacity-0', 'translate-y-2');
            window.setTimeout(() => element.remove(), 180);
        };
        element.querySelector('button').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            setLoading(button, true, 'Restoring…');
            try {
                const restored = await callback();
                if (restored !== false) dismiss();
                else setLoading(button, false);
            } catch (error) {
                setLoading(button, false);
                toast('error', error.message || 'Unable to undo that action.');
            }
        });
        timer = window.setTimeout(dismiss, options.duration || 8000);

        return element;
    }

    function setLoading(button, loading, label) {
        if (!button) return;

        if (loading) {
            if (button.dataset.loadingActive === 'true') return;
            button.dataset.loadingActive = 'true';
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.classList.add('cursor-wait', 'opacity-75');
            const text = label || button.dataset.loadingText || 'Please wait…';
            button.replaceChildren();
            const spinner = document.createElement('span');
            spinner.className = 'h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent';
            spinner.setAttribute('aria-hidden', 'true');
            button.appendChild(spinner);
            if (!button.classList.contains('icon-button')) {
                const textNode = document.createElement('span');
                textNode.textContent = text;
                button.appendChild(textNode);
            }
        } else {
            button.disabled = false;
            button.classList.remove('cursor-wait', 'opacity-75');
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.loadingActive;
        }
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(options.headers || {}),
            },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Something went wrong. Please try again.');
        return body;
    }

    window.Notifications = {
        toast,
        success: (message, options) => toast('success', message, options),
        error: (message, options) => toast('error', message, options),
        warning: (message, options) => toast('warning', message, options),
        info: (message, options) => toast('info', message, options),
        confirm: confirmAction,
        undo,
        setLoading,
        request,
    };

    document.addEventListener('DOMContentLoaded', () => {
        const host = region();
        if (host?.dataset.flashMessage) toast(host.dataset.flashType || 'info', host.dataset.flashMessage);

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-async-unassign]');
            if (!form) return;

            event.preventDefault();
            const item = form.closest('[data-assignment-item]');
            if (!item) return;

            const anchor = document.createComment('assignment-placeholder');
            item.before(anchor);
            item.remove();

            try {
                const response = await request(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                });

                undo(response.message, async () => {
                    const restored = await request(response.undo_url, { method: 'POST' });
                    anchor.replaceWith(item);
                    toast('success', restored.message);
                    return true;
                });
            } catch (error) {
                anchor.replaceWith(item);
                toast('error', error.message || 'Failed to unassign user from event.');
            }
        });

        const confirmedForms = new WeakSet();
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.matches('[data-async-unassign]')) return;

            if (form.dataset.confirmTitle && !confirmedForms.has(form)) {
                event.preventDefault();
                const accepted = await confirmAction({
                    title: form.dataset.confirmTitle,
                    message: form.dataset.confirmMessage,
                    action: form.dataset.confirmAction,
                });
                if (accepted) {
                    confirmedForms.add(form);
                    form.requestSubmit(event.submitter || undefined);
                }
                return;
            }

            if (!form.checkValidity()) return;
            setLoading(event.submitter, true);
        });
    });
})();
