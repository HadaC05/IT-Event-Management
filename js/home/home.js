(() => {
    'use strict';

    const state = { events: [], featured: [], filter: 'all', search: '', csrfToken: '', user: null };
    const searches = [...document.querySelectorAll('[data-event-search]')];
    const agendaList = document.querySelector('#agenda-list');
    const eventList = document.querySelector('#event-list');
    const agendaEmpty = document.querySelector('#agenda-empty');
    const dashboardUrl = user => user?.role === 'SBO Adviser' ? 'pages/adviser/dashboard.html' : 'pages/dashboard.html';

    const parseDate = value => new Date(String(value).replace(' ', 'T'));
    const month = value => new Intl.DateTimeFormat('en-US', { month: 'short' }).format(parseDate(value));
    const day = value => String(parseDate(value).getDate()).padStart(2, '0');
    const dateTime = value => new Intl.DateTimeFormat('en-US', {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    }).format(parseDate(value));
    const matches = event => {
        const typeMatches = state.filter === 'all' || event.timing === state.filter;
        const text = `${event.type} ${event.title} ${event.location} ${event.description || ''}`.toLowerCase();
        return typeMatches && text.includes(state.search);
    };

    const agendaItem = event => {
        const article = document.createElement('article');
        article.className = 'agenda-item grid grid-cols-[56px_minmax(0,1fr)] gap-4 py-5 first:pt-0 sm:grid-cols-[56px_minmax(0,1fr)_auto] sm:items-center';
        const time = document.createElement('time');
        time.className = `grid h-14 place-content-center rounded-lg text-center ${event.timing === 'current' ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#FF6B2C]/10 text-[#FF6B2C]'}`;
        time.dateTime = event.start_at.slice(0, 10);
        time.innerHTML = `<strong class="text-xl font-black leading-none">${day(event.start_at)}</strong><span class="mt-1 text-[9px] font-black uppercase">${month(event.start_at)}</span>`;
        const details = document.createElement('div');
        details.className = 'min-w-0';
        const type = document.createElement('p');
        type.className = 'mb-1 text-[9px] font-black uppercase tracking-[.14em] text-[#397565]';
        type.textContent = event.type;
        const title = document.createElement('h3');
        title.className = 'truncate text-sm font-black sm:text-base';
        title.textContent = event.title;
        const meta = document.createElement('p');
        meta.className = 'mt-1 truncate text-xs text-[#121017]/52';
        meta.textContent = `${dateTime(event.start_at)} · ${event.location}`;
        details.append(type, title, meta);
        const badge = document.createElement('span');
        badge.className = `col-start-2 w-fit rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide sm:col-start-auto ${event.timing === 'current' ? 'bg-[#C6F24E] text-[#121017]' : 'bg-[#2F3AE0]/10 text-[#2F3AE0]'}`;
        badge.textContent = event.timing;
        article.append(time, details, badge);
        return article;
    };

    const eventRow = (event, index) => {
        const article = document.createElement('article');
        article.className = 'event-row group grid grid-cols-[64px_1fr_auto] items-center gap-4 border-b border-[#121017]/12 py-6 sm:grid-cols-[76px_1fr_auto] sm:gap-6';
        const palettes = ['bg-[#397565]/12 text-[#397565]', 'bg-[#2F3AE0]/8 text-[#2F3AE0]', 'bg-[#FF6B2C]/10 text-[#FF6B2C]'];
        const time = document.createElement('time');
        time.className = `grid h-[72px] place-content-center rounded-lg text-center ${palettes[index % palettes.length]}`;
        time.dateTime = event.start_at.slice(0, 10);
        time.innerHTML = `<strong class="text-2xl font-black leading-none">${day(event.start_at)}</strong><span class="mt-1 text-[10px] font-black uppercase">${month(event.start_at)}</span>`;
        const details = document.createElement('div');
        details.className = 'min-w-0';
        const title = document.createElement('h3');
        title.className = 'truncate text-lg font-black tracking-[-.02em] sm:text-xl';
        title.textContent = event.title;
        const meta = document.createElement('p');
        meta.className = 'mt-2 truncate text-sm text-[#121017]/55 sm:text-base';
        meta.textContent = `${dateTime(event.start_at)} · ${event.location}`;
        details.append(title, meta);
        const arrow = document.createElement('span');
        arrow.className = 'text-3xl text-[#397565]/65 transition group-hover:translate-x-1 group-hover:text-[#2F3AE0]';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '→';
        article.append(time, details, arrow);
        return article;
    };

    const renderLists = () => {
        const visible = state.events.filter(matches);
        agendaList.replaceChildren();
        eventList.replaceChildren();
        if (!visible.length) {
            const agendaPlaceholder = document.createElement('div');
            agendaPlaceholder.className = 'rounded-xl border border-dashed border-[#397565]/25 bg-[#397565]/5 px-5 py-10 text-center';
            agendaPlaceholder.innerHTML = '<strong class="text-sm">No upcoming events</strong><p class="mt-1 text-xs text-[#121017]/50">Published event schedules will appear here.</p>';
            agendaList.append(agendaPlaceholder);
            const eventPlaceholder = document.createElement('div');
            eventPlaceholder.className = 'grid min-h-[260px] place-items-center border-b border-[#121017]/12 text-center';
            eventPlaceholder.innerHTML = '<div><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#C6F24E] text-xl">✦</span><h3 class="mt-5 text-xl font-black">The calendar is clearing its throat.</h3><p class="mt-2 text-sm text-[#121017]/50">Upcoming events will appear here once they’re published.</p></div>';
            eventList.append(eventPlaceholder);
            agendaEmpty.classList.toggle('hidden', state.filter === 'all' && !state.search);
            return;
        }
        visible.slice(0, 3).forEach(event => agendaList.append(agendaItem(event)));
        visible.slice(0, 5).forEach((event, index) => eventList.append(eventRow(event, index)));
        agendaEmpty.classList.add('hidden');
    };

    const renderFeatured = () => {
        if (!state.featured.length) return;
        const event = state.featured[0];
        const carousel = document.querySelector('[data-feature-carousel]');
        carousel.replaceChildren();
        const article = document.createElement('article');
        article.className = 'poster-glow poster-grid relative min-h-[320px] overflow-hidden rounded-[14px] p-7 text-white shadow-[0_22px_45px_rgba(18,16,23,.16)] sm:min-h-[360px] sm:p-10';
        article.innerHTML = '<div class="ring-shape absolute -right-10 -top-16 h-52 w-52 rounded-full border-[34px] border-[#C6F24E]/20"></div>';
        const content = document.createElement('div');
        content.className = 'relative flex min-h-[266px] flex-col justify-between sm:min-h-[286px]';
        content.innerHTML = '<div class="flex items-start justify-between gap-4"><p class="text-xs font-black uppercase tracking-[.2em] text-[#C6F24E]">Featured event</p><span class="rounded-full bg-[#121017]/35 px-3 py-1 text-xs font-extrabold backdrop-blur">1 / '+state.featured.length+'</span></div>';
        const body = document.createElement('div');
        body.className = 'max-w-2xl';
        const type = document.createElement('span');
        type.className = 'inline-flex rounded-full bg-[#C6F24E] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#121017]';
        type.textContent = event.type;
        const title = document.createElement('h2');
        title.className = 'mt-3 text-3xl font-black tracking-[-.04em] sm:text-5xl';
        title.textContent = event.title;
        const description = document.createElement('p');
        description.className = 'mt-3 max-w-xl text-sm leading-6 text-white/75';
        description.textContent = event.description || '';
        const meta = document.createElement('p');
        meta.className = 'mt-3 text-sm font-bold text-white/80';
        meta.textContent = `${dateTime(event.start_at)} · ${event.location}`;
        body.append(type, title, description, meta);
        content.append(body);
        article.append(content);
        carousel.append(article);
    };

    document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
        state.filter = button.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        renderLists();
    }));
    searches.forEach(input => {
        input.addEventListener('input', () => {
            state.search = input.value.trim().toLowerCase();
            searches.filter(item => item !== input).forEach(item => { item.value = input.value; });
            renderLists();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.querySelector('#upcoming')?.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('#nav-menu');
    const closeNav = () => {
        if (!navToggle || !navMenu) return;
        navMenu.hidden = true;
        navToggle.setAttribute('aria-expanded', 'false');
    };
    navToggle?.addEventListener('click', event => {
        event.stopPropagation();
        const opening = navMenu.hidden;
        navMenu.hidden = !opening;
        navToggle.setAttribute('aria-expanded', String(opening));
    });
    document.querySelectorAll('[data-nav-close]').forEach(item => item.addEventListener('click', closeNav));
    document.addEventListener('click', closeNav);

    const loginModal = document.querySelector('#login-modal');
    const authResult = document.querySelector('#auth-result');
    const showAuthResult = (type, message) => {
        loginModal?.close();
        authResult.dataset.type = type;
        authResult.querySelector('#auth-result-title').textContent = type === 'success' ? 'Sign-in successful' : 'Unable to sign in';
        authResult.querySelector('#auth-result-message').textContent = message;
        authResult.querySelector('[data-auth-result-eyebrow]').textContent = type === 'success' ? 'Signed in' : 'Please try again';
        authResult.querySelector('[data-success-icon]')?.classList.toggle('hidden', type !== 'success');
        authResult.querySelector('[data-error-icon]')?.classList.toggle('hidden', type === 'success');
        authResult.querySelector('[data-auth-redirecting]')?.classList.toggle('hidden', type !== 'success');
        const confirm = authResult.querySelector('[data-auth-result-confirm]');
        confirm.classList.toggle('hidden', type === 'success');
        confirm.classList.toggle('inline-flex', type !== 'success');
        authResult.showModal();
    };
    document.querySelectorAll('[data-login-open]').forEach(button => button.addEventListener('click', () => {
        if (state.user) {
            window.location.assign(dashboardUrl(state.user));
            return;
        }
        loginModal?.showModal();
    }));
    document.querySelector('[data-login-close]')?.addEventListener('click', () => loginModal?.close());
    document.querySelector('[data-password-toggle]')?.addEventListener('click', event => {
        const password = document.querySelector('#modal-password');
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        event.currentTarget.setAttribute('aria-pressed', String(!showing));
        event.currentTarget.querySelector('[data-eye-visible]')?.classList.toggle('hidden', !showing);
        event.currentTarget.querySelector('[data-eye-hidden]')?.classList.toggle('hidden', showing);
    });
    authResult?.querySelector('[data-auth-result-confirm]')?.addEventListener('click', () => {
        authResult.close();
        loginModal?.showModal();
    });
    document.querySelector('[data-login-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const submit = form.querySelector('[data-login-submit]');
        const original = submit.innerHTML;
        submit.disabled = true;
        submit.innerHTML = '<span class="h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent"></span><span>Signing in…</span>';
        try {
            const fields = new FormData(form);
            const response = await axios.post('api/auth.php?action=login', {
                login: fields.get('login'),
                password: fields.get('password'),
                remember: fields.get('remember') === '1'
            }, { headers: { 'X-CSRF-Token': state.csrfToken } });
            state.user = response.data.user;
            state.csrfToken = response.data.csrf_token;
            showAuthResult('success', response.data.message);
            window.setTimeout(() => window.location.assign(response.data.redirect_url), 1500);
        } catch (error) {
            showAuthResult('error', error.response?.data?.message || 'Please check your credentials and try again.');
        } finally {
            submit.disabled = false;
            submit.innerHTML = original;
        }
    });

    axios.get('api/auth.php?action=session').then(response => {
        state.csrfToken = response.data.csrf_token;
        state.user = response.data.user;
        const loginReason = new URLSearchParams(window.location.search).get('login');
        if (!state.user && loginReason === 'password-changed') {
            loginModal?.showModal();
            loginModal?.querySelector('[name="login"]')?.focus();
            window.history.replaceState({}, '', window.location.pathname + window.location.hash);
        }
        if (state.user) {
            document.querySelectorAll('[data-login-open]').forEach(button => {
                button.firstChild.textContent = 'Dashboard ';
            });
        }
    }).catch(error => console.error('Unable to initialize the session:', error));

    axios.get('api/events.php')
        .then(response => {
            if (!response.data?.success) throw new Error('Invalid API response.');
            state.events = response.data.data.upcoming || [];
            state.featured = response.data.data.featured || [];
            renderLists();
            renderFeatured();
        })
        .catch(error => console.error('Unable to load homepage events:', error));
})();
