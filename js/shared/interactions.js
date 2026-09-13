(function () {
    'use strict';

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
