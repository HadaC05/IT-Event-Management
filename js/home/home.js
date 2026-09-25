(() => {
    'use strict';

    const state = { events: [], featured: [], filter: 'all', search: '', csrfToken: '', user: null };
    const searches = [...document.querySelectorAll('[data-event-search]')];
    const agendaList = document.querySelector('#agenda-list');
    const eventList = document.querySelector('#event-list');
    const agendaEmpty = document.querySelector('#agenda-empty');
    const dashboardUrl = user => ({
        'Admin': 'pages/admin/media.html',
        'SBO': 'pages/admin/media.html',
        'SBO Adviser': 'pages/adviser/dashboard.html',
        'SBO Officer': 'pages/sbo/attendance.html',
        'Faculty': 'pages/faculty/students.html',
        'Student': 'pages/student/home.html'
    })[user?.role] || './';
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

    let featuredTimer;
    const renderFeatured = () => {
        if (featuredTimer) window.clearInterval(featuredTimer);
        if (!state.featured.length) return;
        const slides = state.featured.flatMap(event => {
            const images = event.feature_images?.length ? event.feature_images.map(image => image.image_path) : [event.poster_path];
            return images.map(image => ({ event, image }));
        });
        if (!slides.length) return;
        let current = 0;
        let event = slides[current].event;
        const carousel = document.querySelector('[data-feature-carousel]');
        carousel.replaceChildren();
        const article = document.createElement('article');
        article.className = 'homepage-feature-article poster-glow poster-grid relative overflow-hidden text-white duration-500';
        article.innerHTML = '<div class="ring-shape absolute -right-10 -top-16 h-52 w-52 rounded-full border-[34px] border-[#C6F24E]/20"></div>';
        if (slides[current].image) {
            article.style.backgroundImage = `linear-gradient(0deg,rgba(16,45,38,.94),rgba(31,85,72,.35)),url("${slides[current].image}")`;
            article.style.backgroundPosition = 'center';
            article.style.backgroundSize = 'cover';
        }
        const content = document.createElement('div');
        content.className = 'homepage-feature-content relative flex flex-col';
        content.innerHTML = '<div class="absolute right-0 top-0 flex items-start gap-4"><p class="hidden text-xs font-black uppercase tracking-[.2em] text-[#C6F24E] sm:block">Featured event</p><span class="rounded-full bg-[#121017]/35 px-3 py-1 text-xs font-extrabold backdrop-blur">1 / '+slides.length+'</span></div>';
        const welcome = document.createElement('div');
        welcome.className = 'max-w-3xl pr-8 sm:pr-20';
        const welcomeLabel = document.createElement('p');
        welcomeLabel.className = 'mb-5 flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-[#C6F24E]';
        welcomeLabel.innerHTML = '<span class="h-2 w-2 rounded-full bg-[#FF6B2C]"></span>CITE Fest 2026';
        const welcomeTitle = document.createElement('h1');
        welcomeTitle.className = 'text-[clamp(2.8rem,6vw,5.8rem)] font-black leading-[.96] tracking-[-.065em]';
        welcomeTitle.textContent = 'Where IT events come alive.';
        const welcomeDescription = document.createElement('p');
        welcomeDescription.className = 'mt-6 max-w-2xl text-base leading-7 text-white/80 sm:text-lg';
        welcomeDescription.textContent = 'Discover competitions, activities, and memorable moments created for the CITE community.';
        welcome.append(welcomeLabel, welcomeTitle, welcomeDescription);
        const body = document.createElement('div');
        body.className = 'homepage-feature-event-info max-w-2xl';
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
        const actions = document.createElement('div');
        actions.className = 'homepage-feature-actions absolute flex flex-wrap justify-end gap-3';
        const signIn = document.createElement('button');
        signIn.className = 'inline-flex min-h-12 items-center rounded-lg bg-[#397565] px-5 text-sm font-extrabold text-white shadow-[0_8px_20px_rgba(57,117,101,.22)] transition hover:-translate-y-0.5 hover:bg-[#2e6355]';
        signIn.type = 'button';
        signIn.textContent = 'Sign in to your portal';
        signIn.addEventListener('click', () => {
            if (state.user) {
                if (state.user.must_change_password) window.RequiredPasswordGate.open(state.user, state.csrfToken);
                else window.location.assign(dashboardUrl(state.user));
                return;
            }
            loginModal?.showModal();
        });
        const explore = document.createElement('a');
        explore.className = 'inline-flex min-h-12 items-center rounded-lg border border-white/55 bg-white/15 px-5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-white hover:text-[#2F3AE0]';
        explore.href = '#upcoming';
        explore.textContent = 'Explore Events';
        actions.append(signIn, explore);
        content.append(welcome, body, actions);
        article.append(content);
        carousel.append(article);
        const counter = content.querySelector('span');
        const showSlide = index => {
            current = (index + slides.length) % slides.length;
            const slide = slides[current];
            event = slide.event;
            article.style.backgroundImage = slide.image
                ? `linear-gradient(0deg,rgba(16,45,38,.94),rgba(31,85,72,.35)),url("${slide.image}")`
                : '';
            article.style.backgroundPosition = 'center';
            article.style.backgroundSize = slide.image ? 'cover' : '';
            type.textContent = event.type;
            title.textContent = event.title;
            description.textContent = event.description || '';
            meta.textContent = `${dateTime(event.start_at)} Â· ${event.location}`;
            counter.textContent = `${current + 1} / ${slides.length}`;
        };
        if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            featuredTimer = window.setInterval(() => showSlide(current + 1), 6000);
        }
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
                closeNav();
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
        navToggle.setAttribute('aria-label', 'Open menu');
    };
    navToggle?.addEventListener('click', event => {
        event.stopPropagation();
        const opening = navMenu.hidden;
        navMenu.hidden = !opening;
        navToggle.setAttribute('aria-expanded', String(opening));
        navToggle.setAttribute('aria-label', opening ? 'Close menu' : 'Open menu');
    });
    navMenu?.addEventListener('click', event => event.stopPropagation());
    document.querySelectorAll('[data-nav-close]').forEach(item => item.addEventListener('click', closeNav));
    document.addEventListener('click', closeNav);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && navMenu && !navMenu.hidden) {
            closeNav();
            navToggle.focus();
        }
    });

    const loginModal = document.querySelector('#login-modal');
    const passwordChangeSuccess = loginModal?.querySelector('[data-password-change-success]');
    const firstLoginGuide = loginModal?.querySelector('[data-first-login-guide]');
    const signInWait = document.querySelector('#sign-in-wait');
    const signInCancel = signInWait?.querySelector('[data-sign-in-cancel]');
    let activeLogin = null;
    const cancelPendingLogin = () => {
        const attempt = activeLogin;
        if (!attempt || attempt.completed) return;
        activeLogin = null;
        attempt.controller.abort();
        signInWait?.close();
        if (!loginModal?.open) loginModal?.showModal();
        const form = loginModal?.querySelector('[data-login-form]');
        const errorMessage = form?.querySelector('[data-login-error]');
        if (errorMessage) {
            errorMessage.textContent = 'Sign-in cancelled. You can try again.';
            errorMessage.classList.remove('hidden');
        }
        const submit = form?.querySelector('[data-login-submit]');
        if (submit) submit.disabled = false;
        requestAnimationFrame(() => form?.elements.password.focus({ preventScroll: true }));
    };
    signInWait?.addEventListener('cancel', event => {
        event.preventDefault();
        cancelPendingLogin();
    });
    signInCancel?.addEventListener('click', cancelPendingLogin);
    document.querySelectorAll('[data-login-open]').forEach(button => button.addEventListener('click', event => {
        event.preventDefault();
        if (state.user) {
            if (state.user.must_change_password) {
                window.RequiredPasswordGate.open(state.user, state.csrfToken);
                return;
            }
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
        event.currentTarget.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        event.currentTarget.querySelector('[data-eye-visible]')?.classList.toggle('hidden', !showing);
        event.currentTarget.querySelector('[data-eye-hidden]')?.classList.toggle('hidden', showing);
    });
    document.querySelector('[data-login-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        if (activeLogin) return;
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const attempt = { controller: new AbortController(), completed: false };
        activeLogin = attempt;
        passwordChangeSuccess?.classList.add('hidden');
        const submit = form.querySelector('[data-login-submit]');
        const errorMessage = form.querySelector('[data-login-error]');
        errorMessage.classList.add('hidden');
        submit.disabled = true;
        const fields = new FormData(form);
        const startedAt = performance.now();
        const finishWait = async () => {
            const remaining = Math.max(0, 550 - (performance.now() - startedAt));
            if (remaining) await new Promise(resolve => window.setTimeout(resolve, remaining));
        };
        loginModal?.close();
        signInWait?.showModal();
        if (signInCancel) signInCancel.disabled = false;
        try {
            const response = await axios.post('api/auth.php?action=login', {
                login: fields.get('login'),
                password: fields.get('password'),
                remember: fields.get('remember') === '1'
            }, {
                headers: { 'X-CSRF-Token': state.csrfToken },
                signal: attempt.controller.signal,
                timeout: 20000
            });
            if (activeLogin !== attempt) return;
            attempt.completed = true;
            if (signInCancel) signInCancel.disabled = true;
            state.user = response.data.user;
            state.csrfToken = response.data.csrf_token;
            await finishWait();
            if (activeLogin !== attempt) return;
            if (state.user.must_change_password) {
                signInWait?.close();
                window.RequiredPasswordGate.open(state.user, state.csrfToken);
                return;
            }
            window.location.assign(response.data.redirect_url || dashboardUrl(state.user));
        } catch (error) {
            if (activeLogin !== attempt) return;
            await finishWait();
            if (activeLogin !== attempt) return;
            signInWait?.close();
            loginModal?.showModal();
            errorMessage.textContent = ['ECONNABORTED', 'ETIMEDOUT'].includes(error.code)
                ? 'Sign-in is taking too long. Check your connection and try again.'
                : error.response?.data?.message || 'Please check your ID or username and password, then try again.';
            errorMessage.classList.remove('hidden');
            requestAnimationFrame(() => form.elements.password.focus({ preventScroll: true }));
        } finally {
            if (activeLogin === attempt) {
                activeLogin = null;
                submit.disabled = false;
            }
        }
    });

    axios.get('api/auth.php?action=session').then(response => {
        state.csrfToken = response.data.csrf_token;
        state.user = response.data.user;
        const loginReason = new URLSearchParams(window.location.search).get('login');
        if (!state.user && ['password-changed', 'sign-in'].includes(loginReason)) {
            if (loginReason === 'password-changed') {
                passwordChangeSuccess?.classList.remove('hidden');
                firstLoginGuide?.classList.add('hidden');
            }
            loginModal?.showModal();
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('login');
            window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
        }
        if (state.user) {
            document.querySelectorAll('[data-login-open]').forEach(button => {
                button.firstChild.textContent = 'Dashboard ';
            });
            if (state.user.must_change_password) window.RequiredPasswordGate.open(state.user, state.csrfToken);
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
