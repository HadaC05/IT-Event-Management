window.SharedNavigation.ready.then(() => {
    'use strict';

    let csrfToken = '';
    let roles = [];
    let yearLevels = [];
    let teams = [];
    let events = [];
    let userSavePending = false;
    let searchTimer;
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
    const rosterDialog = document.querySelector('#student-roster-import-dialog');
    const rosterForm = rosterDialog?.querySelector('[data-roster-import-form]');
    const rosterApply = rosterDialog?.querySelector('[data-roster-apply]');
    const rosterStatusFilter = rosterDialog?.querySelector('[data-roster-status-filter]');
    const rosterPrevious = rosterDialog?.querySelector('[data-roster-page-previous]');
    const rosterNext = rosterDialog?.querySelector('[data-roster-page-next]');
    const facultyImportDialog = document.querySelector('#faculty-import-dialog');
    const facultyImportForm = facultyImportDialog?.querySelector('[data-faculty-import-form]');
    const facultyImportApply = facultyImportDialog?.querySelector('[data-faculty-apply]');
    let facultyImportToken = '';
    let facultyImportPending = false;
    let activeRosterBatchId = null;
    let activeRosterPage = 1;
    let rosterImportPending = false;
    const filterForm = document.querySelector('form[method="GET"]');
    const logoutForm = document.querySelector('form[action="api/auth.php?action=logout"]');
    const directoryHeader = document.querySelector('.admin-main > section:nth-of-type(2) > header > div');
    const paginationNav = document.querySelector('.admin-main > section:nth-of-type(2) > nav');
    const manageableRoles = ['SBO', 'Faculty', 'Student'];
    const creatableRoles = ['SBO Adviser', 'Faculty', 'Student'];
    const isManageable = role => manageableRoles.includes(role);
    const initials = user => `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase();
    const visibleEmail = user => /@pending\.invalid$/i.test(String(user?.email || '')) ? '' : String(user?.email || '');
    const loginLabel = user => user.id_number && user.username === user.id_number
        ? `${user.role === 'Faculty' ? 'Faculty' : 'Student'} ID: ${user.id_number}`
        : `@${user.username}${user.id_number ? ` / ${user.id_number}` : ''}`;
    const notify = (type, message) => window.Notifications?.[type]?.(message);
    const showError = error => notify('error', error.response?.data?.message || 'Unable to complete the request.');

    // The directory is rendered from the API. Remove legacy server-rendered edit
    // dialogs so there is only one reusable add/edit form in the accessibility tree.
    document.querySelectorAll('dialog[id^="edit-"]').forEach(dialog => dialog.remove());

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
        const allowedRoles = user?.role === 'SBO Officer' ? ['SBO Officer', 'Student'] : creatableRoles;
        fillSelect(userForm.elements.role_id, roles, user?.role_id ?? '', allowedRoles);
        fillSelect(userForm.elements.year_level, yearLevels, user?.year_level ?? '');
        fillSelect(userForm.elements.faculty_team_id, teams.map(team => ({id:team.id,label:team.name})), user?.faculty_team_id ?? '');
        userForm.elements.password.required = !user;
        userForm.elements.password_confirmation.required = !user;
        const passwordLabel = userForm.elements.password.closest('label')?.querySelector('span:first-child');
        if (passwordLabel) {
            passwordLabel.innerHTML = user
                ? 'New temporary password <small class="font-medium text-slate-400">Leave blank to keep</small>'
                : 'Temporary password';
        }
        addDialog.querySelector('h2').textContent = user ? 'Edit User' : 'Add User';
        addDialog.querySelector('header p:last-child').textContent = user
            ? `Update ${user.full_name}'s information and access.`
            : 'Create an account with temporary credentials. The user must choose a new password after signing in.';
        userForm.querySelector('footer button[type="submit"]').textContent = user ? 'Save Changes' : 'Add User';
        refreshRoleFields();
        refreshPasswordStrength();
    };

    const selectedRoleName = () => roles.find(role => String(role.id) === userForm.elements.role_id.value)?.name || '';

    const refreshRoleFields = () => {
        const role = selectedRoleName();
        const faculty = role === 'Faculty';
        const student = role === 'Student';
        userForm.querySelector('[data-faculty-team-field]')?.classList.toggle('hidden', !faculty);
        const yearLevelField = userForm.querySelector('[data-year-level-field]');
        yearLevelField?.classList.toggle('hidden', !student);
        userForm.elements.year_level.disabled = !student;
        const usernameField = userForm.querySelector('[data-username-field]');
        usernameField?.classList.toggle('hidden', faculty);
        userForm.elements.username.disabled = faculty;
        const idNumber = userForm.elements.id_number;
        const idLabel = userForm.querySelector('[data-id-number-label]');
        idNumber.required = student || faculty;
        if (faculty) {
            if (idLabel) idLabel.textContent = 'Faculty ID';
            idNumber.placeholder = '23-2324-F';
            idNumber.maxLength = 11;
            idNumber.inputMode = 'text';
            idNumber.pattern = '2[0-9]-[0-9]{3,6}-[A-Za-z]';
            idNumber.title = 'Use two digits starting with 2, 3–6 middle digits, and one final letter.';
            userForm.elements.username.value = idNumber.value.toUpperCase();
        } else if (student || !role) {
            if (idLabel) idLabel.textContent = student ? 'Student ID' : 'ID number';
            idNumber.placeholder = '02-xxxx-xxxxxx';
            idNumber.maxLength = 14;
            idNumber.inputMode = 'numeric';
            idNumber.pattern = '02-[0-9]{4}-[0-9]{5,6}';
            idNumber.title = 'Use 02-xxxx-xxxxx or 02-xxxx-xxxxxx.';
        } else {
            if (idLabel) idLabel.textContent = 'ID number';
            idNumber.placeholder = '';
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
        const accepted = window.Notifications?.confirm
            ? await window.Notifications.confirm({
                title: `${action[0].toUpperCase()}${action.slice(1)} user?`,
                message: action === 'deactivate'
                    ? `${user.full_name} will not be able to sign in until the account is activated again.`
                    : `${user.full_name} will regain access to the system.`,
                action: `${action[0].toUpperCase()}${action.slice(1)}`,
            })
            : false;
        if (!accepted) return;
        try {
            const response = await axios.post('api/users.php', { action: 'toggle', id: user.id, active: user.status !== 'inactive' ? false : true }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            notify('success', response.data.message || `${user.full_name} ${action}d successfully.`);
            await loadUsers().catch(() => notify('warning', 'Saved, but the user list could not refresh. Reload the page.'));
        } catch (error) {
            showError(error);
        }
    };

    const closeUserMenu = () => document.querySelector('[data-user-actions-menu]')?.remove();

    const resetUserPassword = user => {
        closeUserMenu();
        editUser(user);
        requestAnimationFrame(() => {
            userForm.elements.password.scrollIntoView({ behavior: 'smooth', block: 'center' });
            userForm.elements.password.focus();
        });
    };

    const viewUser = user => {
        closeUserMenu();
        const viewer = document.createElement('dialog');
        viewer.dataset.accountDetails = '';
        viewer.dataset.modalSize = 'large';
        viewer.dataset.modalKind = 'detail';
        viewer.innerHTML = '<header class="flex items-start justify-between gap-5 border-b border-[#121017]/8 px-6 py-5"><div class="flex min-w-0 items-center gap-4"><span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-[#397565] text-sm font-black text-white" data-value="avatar"></span><div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-[.14em] text-[#397565]">Account details</p><h2 class="mt-1 truncate text-2xl font-black"></h2><p class="mt-1 text-xs text-[#121017]/50" data-value="id"></p></div></div><div class="flex shrink-0 items-center gap-3"><span class="inline-flex items-center gap-2 rounded-full bg-[#397565]/8 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#397565]" data-value="status"><i class="h-2 w-2 rounded-full bg-[#397565]"></i><span></span></span><button type="button" data-modal-close aria-label="Close account details">×</button></div></header><div class="px-6 py-2"><dl class="divide-y divide-[#121017]/8"><div class="grid gap-1 py-4 sm:grid-cols-[150px_minmax(0,1fr)] sm:items-baseline"><dt class="text-[10px] font-black uppercase tracking-[.12em] text-[#121017]/40">Email</dt><dd class="break-words text-sm font-bold" data-value="email"></dd></div><div class="grid gap-1 py-4 sm:grid-cols-[150px_minmax(0,1fr)] sm:items-baseline"><dt class="text-[10px] font-black uppercase tracking-[.12em] text-[#121017]/40">Username</dt><dd class="text-sm font-bold" data-value="username"></dd></div><div class="grid gap-1 py-4 sm:grid-cols-[150px_minmax(0,1fr)] sm:items-baseline"><dt class="text-[10px] font-black uppercase tracking-[.12em] text-[#121017]/40">Role</dt><dd class="text-sm font-bold" data-value="role"></dd></div><div class="grid gap-1 py-4 sm:grid-cols-[150px_minmax(0,1fr)] sm:items-baseline"><dt class="text-[10px] font-black uppercase tracking-[.12em] text-[#121017]/40">Account type</dt><dd class="text-sm font-bold" data-value="account-type"></dd></div><div class="grid gap-1 py-4 sm:grid-cols-[150px_minmax(0,1fr)] sm:items-baseline"><dt class="text-[10px] font-black uppercase tracking-[.12em] text-[#121017]/40">Responsibility</dt><dd class="text-sm font-bold" data-value="responsibility"></dd></div></dl></div>';
        const initials = (user.full_name || user.username || 'User').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase();
        const status = (user.status || 'unknown').replace(/^./, value => value.toUpperCase());
        viewer.querySelector('h2').textContent = user.full_name;
        viewer.querySelector('[data-value="avatar"]').textContent = initials;
        viewer.querySelector('[data-value="id"]').textContent = user.id_number || 'No school ID';
        const email = visibleEmail(user);
        const emailRow = viewer.querySelector('[data-value="email"]').closest('div');
        emailRow.classList.toggle('hidden', !email);
        viewer.querySelector('[data-value="email"]').textContent = email;
        viewer.querySelector('[data-value="username"]').textContent = loginLabel(user);
        viewer.querySelector('[data-value="role"]').textContent = user.role || 'No role assigned';
        viewer.querySelector('[data-value="account-type"]').textContent = user.role === 'SBO Adviser'
            ? 'Protected adviser account'
            : user.role === 'SBO Officer'
                ? 'Separate officer login linked to a student'
                : user.role === 'Student'
                    ? 'Student participant account'
                    : `${user.role || 'Standard'} account`;
        viewer.querySelector('[data-value="status"] span').textContent = status;
        if (user.status === 'inactive') {
            viewer.querySelector('[data-value="status"]').className = 'inline-flex items-center gap-2 rounded-full bg-[#FF6B2C]/8 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#d9470a]';
            viewer.querySelector('[data-value="status"] i').className = 'h-2 w-2 rounded-full bg-[#FF6B2C]';
        }
        const responsibility = user.role === 'SBO Adviser'
            ? 'Protected account'
            : user.role === 'SBO Officer'
                ? `${user.responsibility_events_count} active ${user.responsibility_events_count === 1 ? 'event' : 'events'}`
                : user.role === 'Faculty'
                    ? (user.faculty_team_name ? `Attendance responsibility · ${user.faculty_team_name}` : 'No team assigned')
                    : user.role === 'SBO'
                        ? `${user.assigned_events.length} assigned ${user.assigned_events.length === 1 ? 'event' : 'events'}`
                        : '—';
        viewer.querySelector('[data-value="responsibility"]').textContent = responsibility;
        viewer.querySelector('[data-modal-close]').onclick = () => viewer.close();
        viewer.addEventListener('close', () => viewer.remove());
        document.body.append(viewer);
        viewer.showModal();
    };

    const openUserMenu = (anchor, user) => {
        closeUserMenu();
        const menu = document.createElement('div');
        menu.dataset.userActionsMenu = '';
        menu.className = 'fixed z-[80] w-52 rounded-xl border border-[#121017]/10 bg-white p-1.5 shadow-2xl';
        const add = (label, action, danger = false) => {
            const control = actionButton(label, `block w-full rounded-lg px-3 py-2.5 text-left text-xs font-bold transition hover:bg-[#F3F0E9]/60 ${danger ? 'text-[#d9470a]' : 'text-[#121017]'}`, () => {
                closeUserMenu();
                action();
            });
            menu.append(control);
        };
        add('View details', () => viewUser(user));
        if (isManageable(user.role)) {
            add('Edit account', () => editUser(user));
            add('Reset password', () => resetUserPassword(user));
            const divider = document.createElement('div');
            divider.className = 'my-1 border-t border-[#121017]/8';
            menu.append(divider);
            add(user.status === 'inactive' ? 'Activate user' : 'Deactivate user', () => toggleUser(user), user.status !== 'inactive');
        } else if (user.role === 'SBO Officer') {
            add('Manage officer account', () => { location.href = `pages/adviser/officers.html?search=${encodeURIComponent(user.id_number || user.username)}`; });
        }
        document.body.append(menu);
        const anchorRect = anchor.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        menu.style.left = `${Math.max(12, Math.min(innerWidth - menuRect.width - 12, anchorRect.right - menuRect.width))}px`;
        menu.style.top = `${Math.min(innerHeight - menuRect.height - 12, anchorRect.bottom + 6)}px`;
    };

    const unassignEvent = async (user, event) => {
        const accepted = window.Notifications?.confirm
            ? await window.Notifications.confirm({
                title: 'Remove event assignment?',
                message: `${user.full_name} will no longer be assigned to ${event.title}.`,
                action: 'Remove assignment',
            })
            : false;
        if (!accepted) return;
        try {
            const response = await axios.post('api/users.php', { action: 'unassign_event', id: user.id, event_id: event.id }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            notify('success', response.data.message || 'Event assignment removed.');
            await loadUsers().catch(() => notify('warning', 'Saved, but the user list could not refresh. Reload the page.'));
        } catch (error) {
            showError(error);
        }
    };

    const assignEvent = user => {
        const assignedIds = new Set(user.assigned_events.map(event => String(event.id)));
        const available = events.filter(event => !assignedIds.has(String(event.id)));
        if (!available.length) {
            notify('warning', 'No active events are available for this user.');
            return;
        }
        const dialog = document.createElement('dialog');
        dialog.dataset.assignEventDialog = '';
        dialog.dataset.modalSize = 'medium';
        dialog.dataset.modalKind = 'form';
        const form = document.createElement('form');
        form.dataset.axiosForm = '';
        form.innerHTML = '<header class="flex items-start justify-between gap-5 border-b border-[#121017]/8 px-6 py-5"><div><p class="text-[10px] font-black uppercase tracking-[.14em] text-[#397565]">Event responsibility</p><h2 class="mt-1 text-2xl font-black">Assign Event</h2><p class="mt-1.5 text-sm text-[#121017]/50"></p></div><button type="button" data-dialog-close aria-label="Close event assignment">×</button></header><div class="p-6"><label class="grid gap-2"><span class="text-sm font-bold">Available event</span><select class="h-12 rounded-xl border border-[#121017]/12 bg-[#F3F0E9]/35 px-3 text-sm outline-none focus:border-[#397565] focus:bg-white" required><option value="">Select an event</option></select></label></div><footer class="flex justify-end gap-2 border-t border-[#121017]/8 bg-white px-6 py-4"><button class="min-h-11 rounded-xl border border-[#121017]/12 px-4 text-sm font-bold" type="button" data-dialog-close>Cancel</button><button class="min-h-11 rounded-xl px-5 text-sm font-black" type="submit" data-modal-primary>Assign Event</button></footer>';
        form.querySelector('p').textContent = `Choose an event for ${user.full_name}.`;
        const select = form.querySelector('select');
        available.forEach(event => {
            const date = event.start_at ? new Date(event.start_at.replace(' ', 'T')).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Date pending';
            select.add(new Option(`${event.title} · ${date}`, event.id));
        });
        form.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        form.addEventListener('submit', async submitEvent => {
            submitEvent.preventDefault();
            const submit = submitEvent.submitter || form.querySelector('button[type="submit"]');
            window.Notifications?.setLoading(submit, true, 'Assigning…');
            try {
                const response = await axios.post('api/users.php', { action: 'assign_event', id: user.id, event_id: select.value }, {
                    headers: { 'X-CSRF-Token': csrfToken },
                });
                dialog.close();
                notify('success', response.data.message || 'Event assigned successfully.');
                await loadUsers().catch(() => notify('warning', 'Saved, but the user list could not refresh. Reload the page.'));
            } catch (error) {
                showError(error);
            } finally {
                window.Notifications?.setLoading(submit, false);
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
        const email = visibleEmail(user);
        identityText[1].textContent = email || loginLabel(user);
        identityText[2].textContent = email ? loginLabel(user) : '';

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
        if (user.role === 'Faculty') {
            const badge = document.createElement('span');
            badge.className = 'inline-flex min-h-8 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800';
            badge.textContent = user.faculty_team_name
                ? `Attendance · ${user.faculty_team_name}`
                : 'Assign a team to enable attendance';
            responsibility.append(badge);
        } else if (user.role === 'SBO') {
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
                officerLink.className = 'inline-flex min-h-9 items-center gap-2 text-xs font-extrabold text-[#397565]';
                officerLink.href = `pages/adviser/officers.html?search=${encodeURIComponent(user.id_number || '')}`;
                officerLink.innerHTML = `<i class="h-2 w-2 rounded-full ${active ? 'bg-[#C6F24E]' : 'bg-[#FF6B2C]'}"></i>`;
                const count = Number(user.responsibility_events_count || 0);
                officerLink.append(document.createTextNode(`${count} active ${count === 1 ? 'event' : 'events'}`));
                responsibility.append(officerLink);
            } else {
                responsibility.textContent = user.role === 'Student' ? '—' : 'Protected account';
            }
        }
        const actions = document.createElement('td');
        actions.className = 'px-6 py-5 text-right';
        const controls = document.createElement('div');
        controls.className = 'inline-flex items-center gap-2';
        controls.append(actionButton(isManageable(user.role) ? 'View / Edit' : 'View Details', 'inline-flex min-h-10 items-center rounded-xl border border-[#397565]/25 bg-[#397565]/8 px-4 text-xs font-extrabold text-[#397565]', () => isManageable(user.role) ? editUser(user) : viewUser(user)));
        const more = actionButton('⋯', 'grid h-10 w-10 place-items-center rounded-xl border border-[#121017]/10 text-lg font-black text-[#121017]/45 hover:bg-[#F3F0E9]/60', event => openUserMenu(event.currentTarget, user));
        more.dataset.userMenuTrigger = '';
        more.setAttribute('aria-label', `More actions for ${user.full_name}`);
        controls.append(more);
        actions.append(controls);
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
        meta.textContent = `${user.role || 'Unassigned'} · ${user.status || 'Unassigned'} · ${loginLabel(user)}`;
        card.append(title, meta);
        const responsibility = document.createElement('p');
        responsibility.className = 'mt-2 text-xs text-[#121017]/45';
        responsibility.textContent = user.role === 'SBO Adviser'
            ? 'Protected account'
            : user.role === 'SBO Officer'
                ? `${Number(user.responsibility_events_count || 0)} active events`
                : user.role === 'Faculty'
                    ? (user.faculty_team_name ? `Attendance · ${user.faculty_team_name}` : 'Assign a team to enable attendance')
                    : user.role === 'Student' ? '—' : `${user.assigned_events.length} assigned events`;
        const controls = document.createElement('div');
        controls.className = 'mt-4 flex items-center gap-2 border-t border-slate-100 pt-4';
        controls.append(actionButton(isManageable(user.role) ? 'View / Edit' : 'View Details', 'min-h-10 flex-1 rounded-lg border border-[#397565]/25 bg-[#397565]/8 px-3 text-xs font-bold text-[#397565]', () => isManageable(user.role) ? editUser(user) : viewUser(user)));
        const more = actionButton('⋯', 'grid h-10 w-10 place-items-center rounded-lg border border-[#121017]/10 text-lg font-black text-[#121017]/45', event => openUserMenu(event.currentTarget, user));
        more.dataset.userMenuTrigger = '';
        more.setAttribute('aria-label', `More actions for ${user.full_name}`);
        controls.append(more);
        card.append(responsibility, controls);
        mobileList.append(card);
    };

    const activeFilters = () => filtersApplied;

    const syncUrl = () => {
        const url = new URL(location.href);
        url.search = '';
        if (filtersApplied) Object.entries(currentFilters).forEach(([key, value]) => {
            if (value) url.searchParams.set(key, value);
        });
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
        teams = data.teams || [];
        events = data.events;
        const roleFilter = filterForm?.querySelector('select[name="role"]');
        if (roleFilter) {
            const selectedRole = currentFilters.role;
            roleFilter.replaceChildren(new Option('All roles', ''));
            data.filter_roles.forEach(role => roleFilter.add(new Option(role.name, role.name, false, role.name === selectedRole)));
        }
        const statusFilter = filterForm?.querySelector('select[name="status"]');
        if (statusFilter) {
            const selectedStatus = currentFilters.status;
            statusFilter.replaceChildren(new Option('Any status', ''));
            data.filter_statuses.forEach(status => statusFilter.add(new Option(
                status.label.replace(/^./, character => character.toUpperCase()),
                status.label,
                false,
                status.label === selectedStatus,
            )));
        }
        const summary = document.querySelector('[data-user-summary]');
        if (summary) {
            const total = Number(data.summary.total || 0);
            const active = Number(data.summary.active || 0);
            const officers = Number(data.summary.sbo || 0);
            summary.textContent = `${total.toLocaleString()} ${total === 1 ? 'user' : 'users'} · ${active.toLocaleString()} active · ${officers.toLocaleString()} ${officers === 1 ? 'officer' : 'officers'}`;
        }
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

    const rosterSummary = data => data.summary || {
        total: data.batch?.total_rows || 0,
        ready: data.batch?.ready_rows || 0,
        warning: data.batch?.warning_rows || 0,
        review: data.batch?.review_rows || 0,
        blocked: data.batch?.blocked_rows || 0,
        importable: (data.batch?.ready_rows || 0) + (data.batch?.warning_rows || 0),
        excluded: (data.batch?.review_rows || 0) + (data.batch?.blocked_rows || 0),
    };

    const clearRosterPreview = (message = 'Preview the selected workbook before replacing any data.') => {
        activeRosterBatchId = null;
        activeRosterPage = 1;
        rosterApply.disabled = true;
        rosterDialog.querySelector('[data-roster-import-results]')?.classList.add('hidden');
        const note = rosterDialog.querySelector('[data-roster-import-note]');
        if (note) note.textContent = message;
    };

    const renderRosterImport = data => {
        const batch = data.batch || null;
        const summary = rosterSummary(data);
        activeRosterBatchId = Number(data.batch_id || batch?.id || 0) || null;
        const results = rosterDialog.querySelector('[data-roster-import-results]');
        const summaryHost = rosterDialog.querySelector('[data-roster-import-summary]');
        const flagsHost = rosterDialog.querySelector('[data-roster-flag-counts]');
        const rowsHost = rosterDialog.querySelector('[data-roster-flagged-rows]');
        const totalHost = rosterDialog.querySelector('[data-roster-flagged-total]');
        const note = rosterDialog.querySelector('[data-roster-import-note]');
        const cards = [
            ['Total rows', summary.total, '#121017'],
            ['Ready', summary.ready, '#397565'],
            ['Warnings', summary.warning, '#806000'],
            ['Review', summary.review, '#B45309'],
            ['Blocked', summary.blocked, '#C2410C'],
        ];
        summaryHost.replaceChildren(...cards.map(([label, value, color]) => {
            const card = document.createElement('div');
            card.className = 'rounded-xl border border-[#121017]/9 bg-white p-3.5';
            const count = document.createElement('strong');
            count.className = 'block text-xl font-black';
            count.style.color = color;
            count.textContent = Number(value || 0).toLocaleString();
            const caption = document.createElement('span');
            caption.className = 'mt-1 block text-[10px] font-black uppercase tracking-[.1em] text-[#121017]/40';
            caption.textContent = label;
            card.append(count, caption);
            return card;
        }));

        const flagCounts = (data.flag_counts || []).filter(flag => Number(flag.count) > 0);
        flagsHost.replaceChildren(...flagCounts.map(flag => {
            const row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-3 rounded-xl border border-[#121017]/8 bg-white px-3.5 py-3';
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.className = 'block text-xs font-black capitalize';
            title.textContent = String(flag.code || '').replaceAll('_', ' ');
            const message = document.createElement('p');
            message.className = 'mt-1 text-[11px] leading-4 text-[#121017]/48';
            message.textContent = flag.message;
            copy.append(title, message);
            const count = document.createElement('span');
            count.className = `rounded-full px-2.5 py-1 text-[10px] font-black ${flag.severity === 'blocked' ? 'bg-[#FF6B2C]/12 text-[#d9470a]' : flag.severity === 'review' ? 'bg-amber-100 text-amber-800' : 'bg-[#397565]/9 text-[#397565]'}`;
            count.textContent = Number(flag.count).toLocaleString();
            row.append(copy, count);
            return row;
        }));

        const flagged = data.flagged_rows || { rows: [], pagination: { total: 0 } };
        activeRosterPage = Number(flagged.pagination?.page || 1);
        const lastPage = Number(flagged.pagination?.lastPage || 1);
        totalHost.textContent = `${Number(flagged.pagination?.total || 0).toLocaleString()} flagged`;
        rosterDialog.querySelector('[data-roster-page-label]').textContent = `${activeRosterPage} / ${lastPage}`;
        rosterPrevious.disabled = activeRosterPage <= 1;
        rosterNext.disabled = activeRosterPage >= lastPage;
        rowsHost.replaceChildren(...flagged.rows.map(item => {
            const row = document.createElement('tr');
            const values = [
                item.source_row,
                `${item.official_name || 'Name unavailable'}${item.student_id ? ` · ${item.student_id}` : ''}`,
                item.validation_status,
                (item.flags || []).map(flag => flag.message).join(' '),
            ];
            values.forEach((value, index) => {
                const cell = document.createElement('td');
                cell.className = index === 2 ? 'px-3 py-3 font-black capitalize' : 'px-3 py-3 align-top text-[#121017]/65';
                if (index === 2) cell.style.color = item.validation_status === 'blocked' ? '#d9470a' : item.validation_status === 'review' ? '#B45309' : '#397565';
                cell.textContent = value;
                row.append(cell);
            });
            return row;
        }));
        if (!flagged.rows.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'px-4 py-8 text-center text-xs text-[#121017]/45';
            cell.textContent = 'No flagged rows in this preview.';
            row.append(cell);
            rowsHost.append(row);
        }

        const canApply = (batch?.status || 'previewed') === 'previewed' && Number(summary.importable || 0) > 0;
        rosterApply.disabled = !canApply;
        note.textContent = batch?.status === 'completed'
            ? Number(batch.skipped_rows || 0) > 0
                ? `${Number(batch.imported_rows).toLocaleString()} accounts were imported by the previous policy; ${Number(batch.skipped_rows).toLocaleString()} rows were excluded. Preview the workbook again to include every row.`
                : `${Number(batch.imported_rows).toLocaleString()} accounts imported. Correction warnings remain visible; no student rows were excluded.`
            : `${Number(summary.total || 0).toLocaleString()} student rows will be imported. Incomplete values receive stable pending identities and remain visible as warnings.`;
        results.classList.remove('hidden');
    };

    const loadLatestRosterImport = async (page = 1) => {
        try {
            const response = await axios.get('api/student-roster-imports.php', { params: {
                batch_id: activeRosterBatchId || undefined,
                status: rosterStatusFilter?.value || undefined,
                page,
            } });
            if (response.data.data?.batch) renderRosterImport(response.data.data);
        } catch (error) {
            if (error.response?.status !== 404) showError(error);
        }
    };

    document.querySelector('[data-roster-import-open]')?.addEventListener('click', () => {
        rosterDialog.showModal();
        loadLatestRosterImport();
    });
    rosterDialog?.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => rosterDialog.close()));
    rosterForm?.querySelector('input[type="file"]')?.addEventListener('change', event => {
        if (event.currentTarget.files?.length) clearRosterPreview();
    });
    rosterStatusFilter?.addEventListener('change', () => loadLatestRosterImport(1));
    rosterPrevious?.addEventListener('click', () => loadLatestRosterImport(Math.max(1, activeRosterPage - 1)));
    rosterNext?.addEventListener('click', () => loadLatestRosterImport(activeRosterPage + 1));
    rosterForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (rosterImportPending || !rosterForm.reportValidity()) return;
        const submit = rosterForm.querySelector('[data-roster-preview]');
        const body = new FormData(rosterForm);
        body.append('action', 'preview');
        rosterImportPending = true;
        clearRosterPreview('Validating the selected workbook…');
        window.Notifications?.setLoading(submit, true, 'Validating…');
        try {
            const response = await axios.post('api/student-roster-imports.php?action=preview', body, { headers: { 'X-CSRF-Token': csrfToken } });
            rosterStatusFilter.value = '';
            renderRosterImport(response.data.data);
            notify('success', response.data.message || 'Preview complete. Every student row will be imported; correction warnings remain visible.');
        } catch (error) {
            const note = rosterDialog.querySelector('[data-roster-import-note]');
            if (note) note.textContent = 'The selected workbook was not previewed. No accounts or existing roster data were changed.';
            showError(error);
        } finally {
            rosterImportPending = false;
            window.Notifications?.setLoading(submit, false);
        }
    });
    rosterApply?.addEventListener('click', async () => {
        if (!activeRosterBatchId || rosterImportPending) return;
        const accepted = window.Notifications?.confirm
            ? await window.Notifications.confirm({
                title: 'Replace current operational data?',
                message: 'This replaces current operational data and imports every student row. Missing IDs receive PENDING identities, and data-quality warnings remain visible for correction. The SBO Adviser account and import audit are preserved.',
                action: 'Replace data',
            })
            : false;
        if (!accepted) return;
        rosterImportPending = true;
        window.Notifications?.setLoading(rosterApply, true, 'Importing…');
        try {
            const response = await axios.post('api/student-roster-imports.php', { action: 'apply', batch_id: activeRosterBatchId }, { headers: { 'X-CSRF-Token': csrfToken }, timeout: 600000 });
            notify('success', response.data.message || 'Complete roster imported with no excluded student rows. Students must replace their one-time password at first sign-in.');
            await loadLatestRosterImport();
            currentPage = 1;
            await loadUsers();
        } catch (error) {
            showError(error);
        } finally {
            rosterImportPending = false;
            window.Notifications?.setLoading(rosterApply, false);
        }
    });

    const clearFacultyPreview = (message = 'Preview the selected workbook before importing faculty accounts.') => {
        facultyImportToken = '';
        facultyImportApply.disabled = true;
        facultyImportDialog?.querySelector('[data-faculty-results]')?.classList.add('hidden');
        const note = facultyImportDialog?.querySelector('[data-faculty-note]');
        if (note) note.textContent = message;
    };

    const renderFacultyPreview = data => {
        facultyImportToken = data.token || '';
        const summary = data.summary || {};
        const cards = [['Total rows', summary.total, '#121017'], ['Ready', summary.ready, '#397565'], ['Blocked', summary.blocked, '#C2410C']];
        const summaryHost = facultyImportDialog.querySelector('[data-faculty-summary]');
        summaryHost.replaceChildren(...cards.map(([label, value, color]) => {
            const card = document.createElement('div');
            card.className = 'rounded-xl border border-[#121017]/9 bg-white p-3.5';
            const count = document.createElement('strong');
            count.className = 'block text-xl font-black';
            count.style.color = color;
            count.textContent = Number(value || 0).toLocaleString();
            const caption = document.createElement('span');
            caption.className = 'mt-1 block text-[10px] font-black uppercase tracking-[.1em] text-[#121017]/40';
            caption.textContent = label;
            card.append(count, caption);
            return card;
        }));
        const rowsHost = facultyImportDialog.querySelector('[data-faculty-rows]');
        rowsHost.replaceChildren(...(data.rows || []).map(item => {
            const row = document.createElement('tr');
            [item.source_row, `${item.name} · ${item.email}`, item.faculty_id, item.status, (item.errors || []).join(' ') || 'Ready to import.'].forEach((value, index) => {
                const cell = document.createElement('td');
                cell.className = index === 3 ? 'px-3 py-3 font-black capitalize' : 'px-3 py-3 align-top text-[#121017]/65';
                if (index === 3) cell.style.color = item.status === 'ready' ? '#397565' : '#C2410C';
                cell.textContent = value;
                row.append(cell);
            });
            return row;
        }));
        facultyImportApply.disabled = !facultyImportToken || Number(summary.ready || 0) < 1;
        facultyImportDialog.querySelector('[data-faculty-note]').textContent = `${Number(summary.ready || 0).toLocaleString()} valid Faculty account${Number(summary.ready || 0) === 1 ? '' : 's'} will be added. Faculty start without a team assignment.`;
        facultyImportDialog.querySelector('[data-faculty-results]').classList.remove('hidden');
    };

    document.querySelector('[data-faculty-import-open]')?.addEventListener('click', () => {
        clearFacultyPreview();
        facultyImportForm?.reset();
        facultyImportDialog.showModal();
    });
    facultyImportDialog?.querySelectorAll('[data-faculty-import-close]').forEach(button => button.addEventListener('click', () => facultyImportDialog.close()));
    facultyImportForm?.querySelector('input[type="file"]')?.addEventListener('change', () => clearFacultyPreview());
    facultyImportForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (facultyImportPending || !facultyImportForm.reportValidity()) return;
        const submit = facultyImportForm.querySelector('[data-faculty-preview]');
        const body = new FormData(facultyImportForm);
        body.append('action', 'preview');
        facultyImportPending = true;
        clearFacultyPreview('Validating the faculty workbook…');
        window.Notifications?.setLoading(submit, true, 'Validating…');
        try {
            const response = await axios.post('api/faculty-imports.php?action=preview', body, { headers: { 'X-CSRF-Token': csrfToken } });
            renderFacultyPreview(response.data.data);
            notify('success', response.data.message || 'Faculty workbook validated.');
        } catch (error) {
            clearFacultyPreview('The workbook was not imported. Correct the file and preview it again.');
            showError(error);
        } finally {
            facultyImportPending = false;
            window.Notifications?.setLoading(submit, false);
        }
    });
    facultyImportApply?.addEventListener('click', async () => {
        if (!facultyImportToken || facultyImportPending) return;
        const accepted = await window.Notifications.confirm({
            title: 'Import valid faculty?',
            message: 'Valid rows will be added with the Faculty role and no team assignment. Existing users and blocked rows will remain unchanged.',
            action: 'Import faculty',
        });
        if (!accepted) return;
        facultyImportPending = true;
        window.Notifications?.setLoading(facultyImportApply, true, 'Importing…');
        try {
            const response = await axios.post('api/faculty-imports.php', { action: 'apply', token: facultyImportToken }, { headers: { 'X-CSRF-Token': csrfToken } });
            notify('success', response.data.message || 'Faculty accounts imported.');
            facultyImportToken = '';
            facultyImportApply.disabled = true;
            facultyImportDialog.querySelector('[data-faculty-note]').textContent = 'Import complete. Faculty must change their temporary password at first sign-in.';
            currentPage = 1;
            await loadUsers();
        } catch (error) {
            showError(error);
        } finally {
            facultyImportPending = false;
            window.Notifications?.setLoading(facultyImportApply, false);
        }
    });

    document.querySelector('[data-dialog-open="add-user-dialog"]')?.addEventListener('click', () => {
        prepareForm();
        addDialog.showModal();
    });
    addDialog?.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => addDialog.close()));
    userForm?.elements.role_id.addEventListener('change', refreshRoleFields);
    userForm?.elements.id_number.addEventListener('input', event => {
        if (selectedRoleName() === 'Faculty') {
            const cleaned = event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 9);
            const prefix = cleaned.slice(0, 2);
            const remainder = cleaned.slice(2);
            const digits = (remainder.match(/^\d{0,6}/)?.[0] || '');
            const suffix = remainder.slice(digits.length).replace(/[^A-Z]/g, '').slice(0, 1);
            event.target.value = prefix + (digits || remainder.length ? `-${digits}` : '') + (suffix ? `-${suffix}` : '');
            userForm.elements.username.value = event.target.value;
            return;
        }
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
    const applyFilters = async () => {
        currentFilters = Object.fromEntries(new FormData(filterForm));
        filtersApplied = Object.values(currentFilters).some(Boolean);
        currentPage = 1;
        try {
            await loadUsers();
            syncUrl();
        } catch (error) {
            showError(error);
        }
    };
    filterForm?.addEventListener('submit', event => {
        event.preventDefault();
        applyFilters();
    });
    filterForm?.addEventListener('change', applyFilters);
    filterForm?.elements.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 320);
    });
    userForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (userSavePending || !userForm.reportValidity()) return;
        const data = Object.fromEntries(new FormData(userForm));
        data.action = userForm.dataset.userId ? 'update' : 'create';
        if (userForm.dataset.userId) data.id = userForm.dataset.userId;
        const submit = userForm.querySelector('button[type="submit"]');
        userSavePending = true;
        window.Notifications?.setLoading(submit, true, 'Saving…');
        try {
            const response = await axios.post('api/users.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            addDialog.close();
            notify('success', response.data.message || (data.action === 'update' ? 'User updated successfully.' : 'User added successfully.'));
            await loadUsers().catch(() => notify('warning', 'Saved, but the user list could not refresh. Reload the page.'));
        } catch (error) {
            if (error.response?.status >= 500) {
                notify('error', 'The server could not save this account. Your entries are still here; please try again.');
            } else showError(error);
        } finally {
            userSavePending = false;
            window.Notifications?.setLoading(submit, false);
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
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-user-actions-menu]') && !event.target.closest('[data-user-menu-trigger]')) closeUserMenu();
    });
    addEventListener('resize', closeUserMenu);
    addEventListener('scroll', closeUserMenu, true);

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
});
