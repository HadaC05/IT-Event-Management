(() => {
    'use strict';

    let csrfToken = '';
    let roles = [];
    let yearLevels = [];
    let events = [];
    const initialQuery = new URLSearchParams(location.search);
    let currentPage = Math.max(1, Number(initialQuery.get('page')) || 1);
    let currentFilters = {
        search: initialQuery.get('search') || '',
        role: initialQuery.get('role') || '',
        status: initialQuery.get('status') || '',
    };
    let filtersApplied = ['search', 'role', 'status'].some(key => initialQuery.has(key));

    const tableBody = document.querySelector('table tbody');
    const mobileList = document.querySelector('.divide-y.divide-slate-100.lg\\:hidden');
    const addDialog = document.querySelector('#add-user-dialog');
    const userForm = addDialog?.querySelector('form');
    const filterForm = document.querySelector('form[method="GET"]');
    const logoutForm = document.querySelector('form[action="api/auth.php?action=logout"]');
    const directoryHeader = document.querySelector('.admin-main > section:nth-of-type(2) > header > div');
    const paginationNav = document.querySelector('.admin-main > section:nth-of-type(2) > nav');
    const manageableRoles = ['SBO', 'Faculty', 'Student'];
    const creatableRoles = ['SBO Adviser', 'Faculty', 'Student'];
    const isManageable = role => manageableRoles.includes(role);
    const initials = user => `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase();
    const showError = error => alert(error.response?.data?.message || 'Unable to complete the request.');

    [filterForm, userForm, logoutForm].forEach(form => {
        if (form) form.dataset.axiosForm = '';
    });
    Object.entries(currentFilters).forEach(([name, value]) => {
        if (filterForm?.elements[name]) filterForm.elements[name].value = value;
    });

    const actionButton = (label, classes, handler) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = classes;
        element.textContent = label;
        element.addEventListener('click', handler);
        return element;
    };

    const fillSelect = (select, items, selected = '', allowed = null) => {
        if (!select) return;
        select.replaceChildren(new Option(select.name === 'role_id' ? 'Select role' : 'Not applicable', ''));
        items.filter(item => !allowed || allowed.includes(item.name)).forEach(item => {
            select.add(new Option(item.name ?? item.label, item.id, false, String(item.id) === String(selected)));
        });
    };

    const prepareForm = (user = null) => {
        userForm.reset();
        if (user) userForm.dataset.userId = user.id;
        else delete userForm.dataset.userId;
        fillSelect(userForm.elements.role_id, roles, user?.role_id ?? '', user ? null : creatableRoles);
        fillSelect(userForm.elements.year_level, yearLevels, user?.year_level ?? '');
        userForm.elements.password.required = !user;
        userForm.elements.password_confirmation.required = !user;
        const passwordLabel = userForm.elements.password.closest('label')?.querySelector('span:first-child');
        if (passwordLabel) {
            passwordLabel.innerHTML = user
                ? 'Password <small class="font-medium text-slate-400">Leave blank to keep</small>'
                : 'Password';
        }
        addDialog.querySelector('h2').textContent = user ? 'Edit User' : 'Add User';
        addDialog.querySelector('header p:last-child').textContent = user
            ? `Update ${user.full_name}'s information and access.`
            : 'Create an account and assign the correct system access.';
        userForm.querySelector('footer button[type="submit"]').textContent = user ? 'Save Changes' : 'Add User';
        refreshRoleFields();
        refreshPasswordStrength();
    };

    const selectedRoleName = () => roles.find(role => String(role.id) === userForm.elements.role_id.value)?.name || '';

    const refreshRoleFields = () => {
        const idNumber = userForm.elements.id_number;
        const student = selectedRoleName() === 'Student';
        idNumber.required = student;
        if (student || !selectedRoleName()) {
            idNumber.maxLength = 14;
            idNumber.inputMode = 'numeric';
            idNumber.pattern = '02-[0-9]{4}-[0-9]{6}';
            idNumber.title = 'Use 02-xxxx-xxxxxx (12 digits).';
        } else {
            ['maxlength', 'inputmode', 'pattern', 'title'].forEach(attribute => idNumber.removeAttribute(attribute));
        }
    };

    const refreshPasswordStrength = () => {
        const password = userForm.elements.password;
        const confirmation = userForm.elements.password_confirmation;
        const meter = userForm.querySelector('[data-password-strength]');
        const value = password.value;
        const rules = {
            length: value.length >= 8,
            uppercase: /[A-Z]/.test(value),
            lowercase: /[a-z]/.test(value),
            number: /\d/.test(value),
            special: /[^A-Za-z0-9]/.test(value),
        };
        const score = Object.values(rules).filter(Boolean).length;
        const labels = ['Not entered', 'Weak', 'Developing', 'Fair', 'Strong', 'Very strong'];
        const colors = ['#1210171a', '#FF6B2C', '#FF6B2C', '#2F3AE0', '#397565', '#C6F24E'];
        meter.querySelectorAll('[data-strength-bar]').forEach((bar, index) => {
            bar.style.backgroundColor = value && index < score ? colors[score] : '#1210171a';
        });
        const label = meter.querySelector('[data-strength-label]');
        label.textContent = value ? labels[score] : labels[0];
        label.style.color = value ? colors[score] : '#12101773';
        Object.entries(rules).forEach(([rule, passed]) => {
            const criterion = meter.querySelector(`[data-password-rule="${rule}"]`);
            criterion?.classList.toggle('text-[#397565]', passed);
            if (criterion?.querySelector('i')) criterion.querySelector('i').style.backgroundColor = passed ? '#C6F24E' : '#12101726';
        });
        const hasConfirmation = confirmation.value.length > 0;
        const matches = value === confirmation.value;
        confirmation.setCustomValidity(hasConfirmation && !matches ? 'Passwords do not match.' : '');
        const match = meter.querySelector('[data-password-match]');
        match.classList.toggle('hidden', !hasConfirmation);
        if (hasConfirmation) {
            match.textContent = matches ? '✓ Passwords match' : 'Passwords do not match yet';
            match.style.color = matches ? '#397565' : '#FF6B2C';
        }
    };

    const editUser = user => {
        prepareForm(user);
        ['first_name', 'middle_name', 'last_name', 'id_number', 'username', 'email', 'role_id', 'year_level']
            .forEach(name => {
                if (userForm.elements[name]) userForm.elements[name].value = user[name] ?? '';
            });
        addDialog.showModal();
    };

    const toggleUser = async user => {
        const action = user.status === 'inactive' ? 'activate' : 'deactivate';
        if (!confirm(`${action[0].toUpperCase()}${action.slice(1)} ${user.full_name}?`)) return;
        try {
            await axios.post('api/users.php', { action: 'toggle', id: user.id }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            await loadUsers();
        } catch (error) {
            showError(error);
        }
    };

    const unassignEvent = async (user, event) => {
        if (!confirm(`Remove ${user.full_name} from ${event.title}?`)) return;
        try {
            await axios.post('api/users.php', { action: 'unassign_event', id: user.id, event_id: event.id }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            await loadUsers();
        } catch (error) {
            showError(error);
        }
    };

    const assignEvent = user => {
        const assignedIds = new Set(user.assigned_events.map(event => String(event.id)));
        const available = events.filter(event => !assignedIds.has(String(event.id)));
        if (!available.length) {
            alert('No active events are available for this user.');
            return;
        }
        const dialog = document.createElement('dialog');
        dialog.className = 'm-auto w-[min(520px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/60';
        const form = document.createElement('form');
        form.className = 'p-6';
        form.dataset.axiosForm = '';
        form.innerHTML = '<h2 class="text-2xl font-extrabold">Assign Event</h2><p class="mt-1.5 text-sm text-slate-500"></p><label class="mt-5 grid gap-2"><span class="text-sm font-bold text-slate-700">Available event</span><select class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm" required><option value="">Select an event</option></select></label><footer class="mt-6 flex justify-end gap-2"><button class="min-h-10 rounded-xl border border-slate-200 px-4 text-sm font-bold" type="button">Cancel</button><button class="min-h-10 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white" type="submit">Assign Event</button></footer>';
        form.querySelector('p').textContent = `Choose an event for ${user.full_name}.`;
        const select = form.querySelector('select');
        available.forEach(event => {
            const date = event.start_at ? new Date(event.start_at.replace(' ', 'T')).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Date pending';
            select.add(new Option(`${event.title} · ${date}`, event.id));
        });
        form.querySelector('button[type="button"]').addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async submitEvent => {
            submitEvent.preventDefault();
            try {
                await axios.post('api/users.php', { action: 'assign_event', id: user.id, event_id: select.value }, {
                    headers: { 'X-CSRF-Token': csrfToken },
                });
                dialog.close();
                await loadUsers();
            } catch (error) {
                showError(error);
            }
        });
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
    };

    const renderDesktopUser = user => {
        const row = document.createElement('tr');
        row.className = 'transition hover:bg-slate-50/70';
        const identity = document.createElement('td');
        identity.className = 'px-6 py-5';
        identity.innerHTML = '<div class="flex min-w-64 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#121017] text-xs font-extrabold text-[#F3F0E9]"></span><span class="grid min-w-0 gap-0.5"><strong class="truncate text-sm text-slate-700"></strong><small class="truncate text-xs text-slate-400"></small><small class="truncate text-xs text-slate-400"></small></span></div>';
        identity.querySelector('div > span:first-child').textContent = initials(user);
        const identityText = identity.querySelectorAll('strong, small');
        identityText[0].textContent = user.full_name;
        identityText[1].textContent = user.email || '';
        identityText[2].textContent = `@${user.username}${user.id_number ? ` · ${user.id_number}` : ''}`;

        const role = document.createElement('td');
        role.className = 'px-4 py-5';
        role.innerHTML = '<span class="inline-flex rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-600"></span>';
        role.firstElementChild.textContent = user.role || 'Unassigned';
        const status = document.createElement('td');
        status.className = 'px-4 py-5';
        status.innerHTML = '<span class="inline-flex items-center gap-2 text-xs font-bold"><i class="h-2 w-2 rounded-full"></i></span>';
        const statusBadge = status.firstElementChild;
        const active = user.status === 'active';
        statusBadge.classList.add(active ? 'text-[#397565]' : 'text-[#FF6B2C]');
        statusBadge.querySelector('i').classList.add(active ? 'bg-[#C6F24E]' : 'bg-[#FF6B2C]');
        statusBadge.append(document.createTextNode((user.status || 'Unassigned').replace(/^./, value => value.toUpperCase())));

        const responsibility = document.createElement('td');
        responsibility.className = 'min-w-72 px-4 py-5 text-xs text-slate-400';
        if (['SBO', 'Faculty'].includes(user.role)) {
            const assignments = document.createElement('div');
            assignments.className = 'flex flex-wrap items-center gap-2';
            user.assigned_events.forEach(event => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex min-h-8 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 py-1 pl-2.5 pr-1 text-xs font-bold text-emerald-800';
                chip.append(document.createTextNode(event.title));
                chip.append(actionButton('×', 'grid h-6 w-6 place-items-center rounded text-base text-emerald-700 hover:text-rose-600', () => unassignEvent(user, event)));
                assignments.append(chip);
            });
            assignments.append(actionButton('+ Assign Event', 'rounded-lg px-2.5 py-1.5 text-xs font-extrabold text-emerald-700', () => assignEvent(user)));
            responsibility.append(assignments);
        } else {
            if (user.role === 'SBO Officer') {
                const officerLink = document.createElement('a');
                officerLink.className = 'inline-flex min-h-9 items-center gap-2 rounded-lg bg-[#397565]/8 px-3 text-xs font-extrabold text-[#397565]';
                officerLink.href = `pages/adviser/officers.html?search=${encodeURIComponent(user.id_number || '')}`;
                officerLink.innerHTML = `<i class="h-2 w-2 rounded-full ${active ? 'bg-[#C6F24E]' : 'bg-[#FF6B2C]'}"></i>`;
                officerLink.append(document.createTextNode('Manage officer assignment'));
                responsibility.append(officerLink);
            } else {
                responsibility.textContent = user.role === 'Student' ? 'Student participant' : 'Protected account';
            }
        }
        const actions = document.createElement('td');
        actions.className = 'px-6 py-5 text-right';
        if (isManageable(user.role)) {
            const controls = document.createElement('div');
            controls.className = 'inline-flex items-center gap-2';
            controls.append(
                actionButton('Edit', 'inline-flex min-h-10 items-center rounded-xl border border-[#397565]/25 bg-[#397565]/8 px-4 text-xs font-extrabold text-[#397565]', () => editUser(user)),
                actionButton(active ? 'Deactivate' : 'Activate', 'inline-flex min-h-10 items-center rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 px-4 text-xs font-extrabold text-[#d9470a]', () => toggleUser(user)),
            );
            actions.append(controls);
        }
        row.append(identity, role, status, responsibility, actions);
        tableBody.append(row);
    };

    const renderMobileUser = user => {
        if (!mobileList) return;
        const card = document.createElement('article');
        card.className = 'p-5';
        const title = document.createElement('strong');
        title.className = 'text-sm text-slate-700';
        title.textContent = user.full_name;
        const meta = document.createElement('p');
        meta.className = 'mt-1 text-xs text-slate-400';
        meta.textContent = `${user.role || 'Unassigned'} · ${user.status || 'Unassigned'} · @${user.username}`;
        card.append(title, meta);
        if (isManageable(user.role)) {
            const controls = document.createElement('div');
            controls.className = 'mt-4 flex gap-2';
            controls.append(
                actionButton('Edit', 'rounded-lg border px-3 py-2 text-xs font-bold text-[#397565]', () => editUser(user)),
                actionButton(user.status === 'inactive' ? 'Activate' : 'Deactivate', 'rounded-lg border px-3 py-2 text-xs font-bold text-[#FF6B2C]', () => toggleUser(user)),
            );
            card.append(controls);
        }
        mobileList.append(card);
    };

    const activeFilters = () => filtersApplied;

    const syncUrl = () => {
        const url = new URL(location.href);
        url.search = '';
        if (filtersApplied) Object.entries(currentFilters).forEach(([key, value]) => url.searchParams.set(key, value));
        if (currentPage > 1) url.searchParams.set('page', String(currentPage));
        history.replaceState(null, '', url);
    };

    const renderClearFilters = () => {
        directoryHeader?.querySelector('[data-clear-filters]')?.remove();
        if (!activeFilters()) return;
        const clear = document.createElement('a');
        clear.href = 'pages/adviser/users.html';
        clear.dataset.clearFilters = '';
        clear.className = 'mt-2 text-xs font-extrabold text-emerald-700 sm:mt-0';
        clear.textContent = 'Clear all filters';
        clear.addEventListener('click', async clickEvent => {
            clickEvent.preventDefault();
            filterForm.reset();
            currentFilters = { search: '', role: '', status: '' };
            filtersApplied = false;
            currentPage = 1;
            await loadUsers();
            syncUrl();
        });
        directoryHeader?.append(clear);
    };

    const renderPagination = pagination => {
        if (!paginationNav) return;
        paginationNav.classList.toggle('hidden', pagination.last_page <= 1);
        paginationNav.replaceChildren();
        if (pagination.last_page <= 1) return;

        const showing = document.createElement('small');
        showing.className = 'text-slate-400';
        showing.textContent = `Showing ${pagination.from}–${pagination.to} of ${pagination.total}`;
        const controls = document.createElement('div');
        controls.className = 'flex items-center gap-2';
        const pageControl = (label, target, disabled) => {
            if (disabled) {
                const span = document.createElement('span');
                span.className = 'rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300';
                span.textContent = label;
                return span;
            }
            return actionButton(label, 'rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:bg-slate-50', async () => {
                currentPage = target;
                await loadUsers();
                syncUrl();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        };
        const pageNumber = document.createElement('span');
        pageNumber.className = 'px-2 text-slate-400';
        pageNumber.textContent = `${pagination.current_page} / ${pagination.last_page}`;
        controls.append(
            pageControl('Previous', pagination.current_page - 1, pagination.current_page === 1),
            pageNumber,
            pageControl('Next', pagination.current_page + 1, pagination.current_page === pagination.last_page),
        );
        paginationNav.append(showing, controls);
    };

    const render = data => {
        roles = data.roles;
        yearLevels = data.year_levels;
        events = data.events;
        const roleFilter = filterForm?.querySelector('select[name="role"]');
        if (roleFilter) {
            const selectedRole = currentFilters.role;
            roleFilter.replaceChildren(new Option('All roles', ''));
            data.filter_roles.forEach(role => roleFilter.add(new Option(role.name, role.name, false, role.name === selectedRole)));
        }
        const summary = document.querySelectorAll('[aria-label="Account overview"] strong');
        ['total', 'students', 'faculty', 'sbo', 'active', 'inactive'].forEach((key, index) => {
            if (summary[index]) summary[index].textContent = Number(data.summary[key] || 0).toLocaleString();
        });
        const resultCount = document.querySelector('.admin-main > section:nth-of-type(2) header h2 + p');
        if (resultCount) resultCount.textContent = `${data.pagination.total} ${data.pagination.total === 1 ? 'account' : 'accounts'} found`;
        currentPage = data.pagination.current_page;
        renderClearFilters();
        renderPagination(data.pagination);
        tableBody.replaceChildren();
        mobileList?.replaceChildren();
        if (!data.users.length) {
            const row = document.createElement('tr');
            row.innerHTML = '<td class="px-6 py-16 text-center" colspan="5"><strong class="text-sm text-slate-600">No users found</strong><p class="mt-1 text-xs text-slate-400">Try adjusting your filters.</p></td>';
            tableBody.append(row);
            return;
        }
        data.users.forEach(user => {
            renderDesktopUser(user);
            renderMobileUser(user);
        });
    };

    const loadUsers = async () => {
        const params = { ...currentFilters };
        params.page = currentPage;
        const response = await axios.get('api/users.php', { params });
        render(response.data.data);
    };

    document.querySelector('[data-dialog-open="add-user-dialog"]')?.addEventListener('click', () => {
        prepareForm();
        addDialog.showModal();
    });
    addDialog?.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => addDialog.close()));
    userForm?.elements.role_id.addEventListener('change', refreshRoleFields);
    userForm?.elements.id_number.addEventListener('input', event => {
        if (selectedRoleName() && selectedRoleName() !== 'Student') return;
        const digits = event.target.value.replace(/\D/g, '').slice(0, 12);
        event.target.value = digits.length <= 2 ? digits
            : (digits.length <= 6 ? `${digits.slice(0, 2)}-${digits.slice(2)}` : `${digits.slice(0, 2)}-${digits.slice(2, 6)}-${digits.slice(6)}`);
    });
    userForm?.querySelectorAll('[data-user-password-toggle]').forEach(toggle => toggle.addEventListener('click', () => {
        const input = toggle.parentElement.querySelector('input');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!showing));
        toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        toggle.querySelector('[data-eye-visible]').classList.toggle('hidden', !showing);
        toggle.querySelector('[data-eye-hidden]').classList.toggle('hidden', showing);
    }));
    userForm?.querySelectorAll('[data-password-strength-source], [data-password-confirmation]').forEach(input => input.addEventListener('input', refreshPasswordStrength));
    filterForm?.addEventListener('submit', async event => {
        event.preventDefault();
        currentFilters = Object.fromEntries(new FormData(filterForm));
        filtersApplied = true;
        currentPage = 1;
        const submitButton = event.submitter || filterForm.querySelector('button[type="submit"]');
        window.Notifications?.setLoading(submitButton, true, 'Loading…');
        try {
            await loadUsers();
            syncUrl();
        } catch (error) {
            showError(error);
        } finally {
            window.Notifications?.setLoading(submitButton, false);
        }
    });
    userForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!userForm.reportValidity()) return;
        const data = Object.fromEntries(new FormData(userForm));
        data.action = userForm.dataset.userId ? 'update' : 'create';
        if (userForm.dataset.userId) data.id = userForm.dataset.userId;
        try {
            await axios.post('api/users.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            addDialog.close();
            await loadUsers();
        } catch (error) {
            showError(error);
        }
    });
    logoutForm?.addEventListener('submit', async event => {
        event.preventDefault();
        try {
            await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } });
        } finally {
            location.replace('./');
        }
    });

    const sidebar = document.querySelector('#sidebar');
    const scrim = document.querySelector('[data-sidebar-scrim]');
    document.querySelectorAll('[data-sidebar-toggle]').forEach(control => control.addEventListener('click', () => {
        const opening = sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('-translate-x-full', !opening);
        sidebar.classList.toggle('translate-x-0', opening);
        scrim.classList.toggle('hidden', !opening);
    }));

    axios.get('api/auth.php?action=session').then(response => {
        if (response.data.user?.role !== 'SBO Adviser') {
            location.replace('./');
            return;
        }
        csrfToken = response.data.csrf_token;
        const user = response.data.user;
        const account = document.querySelector('[data-account-menu]');
        if (account) {
            const avatar = account.querySelector('summary > span:first-child');
            avatar.childNodes[0].textContent = initials(user);
            account.querySelector('summary strong').textContent = user.full_name;
            account.querySelector('summary small').textContent = user.role;
            account.querySelector('div > div strong').textContent = user.full_name;
            account.querySelector('div > div span').textContent = user.email || user.username;
        }
        return loadUsers();
    }).catch(() => location.replace('./'));
})();
