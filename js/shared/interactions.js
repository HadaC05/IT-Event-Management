(function () {
    'use strict';

    if (!document.querySelector('link[data-adviser-modal-styles]')) {
        const modalStyles = document.createElement('link');
        modalStyles.rel = 'stylesheet';
        modalStyles.href = 'css/adviser-modals.css?v=20260917-3';
        modalStyles.dataset.adviserModalStyles = '';
        document.head.append(modalStyles);
    }

    const dialogOpeners = new WeakMap();
    const modalStack = [];
    const topModal = () => {
        const tracked = modalStack.filter(dialog => dialog.isConnected && dialog.matches(':modal'));
        if (tracked.length) return tracked[tracked.length - 1];
        const dialogs = document.querySelectorAll('dialog:modal');
        return dialogs[dialogs.length - 1] || null;
    };
    window.AppDialogs = { topModal };
    const nativeShowModal = window.HTMLDialogElement?.prototype.showModal;
    const nativeClose = window.HTMLDialogElement?.prototype.close;

    const dialogSize = dialog => {
        if (dialog.matches('[data-confirm-dialog]')) return ['small', 'confirmation'];
        if (dialog.matches('#team-dialog, #create-event-dialog, [data-task-dialog]')) return ['workflow', 'workflow'];
        if (dialog.matches('#add-user-dialog, dialog[id^="edit-"], #assign-officer-dialog, #officer-details-dialog')) return ['large', dialog.id.includes('details') ? 'detail' : 'form'];
        if (dialog.matches('[data-account-details], [data-event-details-dialog]')) return ['large', 'detail'];
        if (dialog.matches('[data-category-dialog], #change-officer-password-dialog, [data-scoring-admin], [data-assign-event-dialog]')) return ['medium', 'form'];
        return ['medium', dialog.dataset.modalKind || 'form'];
    };

    const prepareDialog = dialog => {
        if (!(dialog instanceof HTMLDialogElement)) return;
        const [size, kind] = dialogSize(dialog);
        dialog.classList.add('app-dialog');
        dialog.dataset.modalSize ||= size;
        dialog.dataset.modalKind ||= kind;
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('role', 'dialog');
        const heading = dialog.querySelector('h1, h2, h3');
        if (heading && !dialog.hasAttribute('aria-labelledby')) {
            heading.id ||= `dialog-title-${Math.random().toString(36).slice(2, 9)}`;
            dialog.setAttribute('aria-labelledby', heading.id);
        }
    };

    if (nativeShowModal && nativeClose) {
        window.HTMLDialogElement.prototype.showModal = function () {
            prepareDialog(this);
            dialogOpeners.set(this, document.activeElement);
            nativeShowModal.call(this);
            const previous = modalStack.indexOf(this);
            if (previous !== -1) modalStack.splice(previous, 1);
            modalStack.push(this);
            this.dispatchEvent(new CustomEvent('cite:dialog-opened', { bubbles: true }));
            requestAnimationFrame(() => {
                if (topModal() !== this) return;
                const target = this.matches('[data-confirm-dialog]') ? this.querySelector('[data-confirm-cancel]') : this.querySelector('[autofocus], input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled), [href], [tabindex]:not([tabindex="-1"])');
                target?.focus({ preventScroll: true });
            });
        };
        window.HTMLDialogElement.prototype.close = function (returnValue) {
            nativeClose.call(this, returnValue);
        };
    }

    const focusable = dialog => [...dialog.querySelectorAll('a[href], button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')]
        .filter(element => !element.hidden && element.getClientRects().length);

    document.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const dialog = topModal();
        if (!dialog) return;
        const controls = focusable(dialog);
        if (!controls.length) return;
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    document.addEventListener('click', event => {
        const dialog = event.target;
        if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return;
        if (!['detail', 'confirmation'].includes(dialog.dataset.modalKind || '')) return;
        const bounds = dialog.getBoundingClientRect();
        const inside = event.clientX >= bounds.left && event.clientX <= bounds.right && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
        if (!inside) dialog.close('dismissed');
    });

    document.addEventListener('close', event => {
        const dialog = event.target;
        if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;
        const index = modalStack.indexOf(dialog);
        if (index !== -1) modalStack.splice(index, 1);
        const opener = dialogOpeners.get(dialog);
        if (opener instanceof HTMLElement && opener.isConnected) opener.focus({ preventScroll: true });
        dialogOpeners.delete(dialog);
    }, true);

    const isPlainNavigation = (event, link) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (!link?.href || link.target || link.hasAttribute('download') || link.dataset.noTransition !== undefined) return false;
        if (link.matches('[data-dialog-open], [data-login-open], [data-nav-close]')) return false;

        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin) return false;
        if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return false;

        return true;
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('dialog').forEach(prepareDialog);
        new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
            if (!(node instanceof Element)) return;
            if (node.matches('dialog')) prepareDialog(node);
            node.querySelectorAll?.('dialog').forEach(prepareDialog);
        }))).observe(document.body, { childList: true, subtree: true });
        const progress = document.querySelector('[data-route-progress]');
        const page = document.querySelector('[data-page-content]');

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (!isPlainNavigation(event, link)) return;

            event.preventDefault();
            progress?.setAttribute('data-active', 'true');
            page?.classList.add('page-leaving');
            window.setTimeout(() => window.location.assign(link.href), 125);
        });

        document.addEventListener('pointerdown', (event) => {
            const control = event.target.closest('button, [role="button"], summary');
            if (!control || control.matches(':disabled')) return;
            control.classList.add('is-pressed');
        });
        document.addEventListener('pointerup', () => document.querySelectorAll('.is-pressed').forEach(control => control.classList.remove('is-pressed')));
        document.addEventListener('pointercancel', () => document.querySelectorAll('.is-pressed').forEach(control => control.classList.remove('is-pressed')));
    });

    window.addEventListener('pageshow', () => {
        document.querySelector('[data-route-progress]')?.setAttribute('data-active', 'false');
        document.querySelector('[data-page-content]')?.classList.remove('page-leaving');
    });
})();
