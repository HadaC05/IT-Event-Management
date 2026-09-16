window.SharedNavigation.ready.then(() => {
    'use strict';

    let csrfToken = '';
    let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);
    let students = [];
    let searchTimer;

    const list = document.querySelector('[data-officer-list]');
    const listForm = document.querySelector('[data-officer-list-form]');
    const selectAll = document.querySelector('[data-select-all-officers]');
    const selectionBar = document.querySelector('[data-officer-selection-bar]');
    const selectionCount = document.querySelector('[data-officer-selection-count]');
    const filters = document.querySelector('[data-officer-filters]');
    const pagination = document.querySelector('[data-officer-pagination]');
    const dialog = document.querySelector('#assign-officer-dialog');
    const assignForm = document.querySelector('[data-assign-officer-form]');
    const detailsDialog = document.querySelector('#officer-details-dialog');
    const scannerForm = detailsDialog.querySelector('[data-scanner-config-form]');
    const passwordDialog = document.querySelector('#change-officer-password-dialog');
    const passwordForm = document.querySelector('[data-change-officer-password-form]');
    const studentList = document.querySelector('[data-officer-student-list]');
    const studentSearch = document.querySelector('[data-officer-student-search]');
    const studentYear = document.querySelector('[data-officer-year-filter]');
    const selectedStudentLabel = document.querySelector('[data-selected-student-label]');
    const username = document.querySelector('[data-officer-username]');
    const logoutForm = document.querySelector('form[action="api/auth.php?action=logout"]');

    const initials = person => `${person.first_name?.[0] || ''}${person.last_name?.[0] || ''}`.toUpperCase();
    const errorMessage = error => error.response?.data?.message || 'Unable to complete the request.';
    const notify = (type, message) => window.Notifications?.[type]?.(message) || (type === 'error' ? alert(message) : null);

    const button = (label, classes, handler) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = classes;
        element.textContent = label;
        element.addEventListener('click', handler);
        return element;
    };

    const confirmAction = async options => window.Notifications?.confirm
        ? window.Notifications.confirm(options)
        : confirm(options.message);

    const updateUrl = () => {
        const url = new URL(location.href);
        url.search = '';
        new FormData(filters).forEach((value, key) => {
            if (value) url.searchParams.set(key, value);
        });
        if (currentPage > 1) url.searchParams.set('page', String(currentPage));
        history.replaceState(null, '', url);
    };

    const syncSelection = () => {
        const checks = [...list.querySelectorAll('[data-officer-checkbox]:not(:disabled)')];
        const selected = checks.filter(input => input.checked).length;
        selectAll.checked = checks.length > 0 && selected === checks.length;
        selectAll.indeterminate = selected > 0 && selected < checks.length;
        selectionCount.textContent = `${selected} ${selected === 1 ? 'officer' : 'officers'} selected`;
        selectionBar.style.display = selected ? 'flex' : 'none';
    };

    const closeOfficerMenu = () => document.querySelector('[data-officer-actions-menu]')?.remove();

    const openEventAssignments = assignment => {
        if (assignment.status !== 'Active') return notify('error', 'Only active SBO Officers can be assigned to an event.');
        const taskDialog = document.querySelector('[data-task-dialog]');
        const opener = document.querySelector('[data-task-open]');
        if (!taskDialog || !opener) return notify('error', 'Event responsibilities are unavailable. Reload this page and try again.');
        const chooseOfficer = () => {
            if (!taskDialog.open) return false;
            const select = taskDialog.querySelector('[data-task-officer]');
            if (select) {
                select.value = String(assignment.id);
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            const team = taskDialog.querySelector('[data-task-team]');
            if (team && assignment.team_id) team.value = String(assignment.team_id);
            const event = taskDialog.querySelector('[data-task-event]');
            if (event && !event.querySelector('option[value=""]')) event.prepend(new Option('Select an event day', ''));
            if (event) { event.value = ''; event.dispatchEvent(new Event('change', {bubbles: true})); }
            const activity = taskDialog.querySelector('input[name="activity_name"]');
            if (activity && !activity.value.trim()) activity.value = 'Event duties';
            select?.focus();
            return true;
        };
        if (chooseOfficer()) return;
        const observer = new MutationObserver(() => { if (chooseOfficer()) observer.disconnect(); });
        observer.observe(taskDialog, {attributes: true, attributeFilter: ['open']});
        setTimeout(() => observer.disconnect(), 10000);
        opener.click();
    };

    const eventGroups = tasks => {
        const groups = new Map();
        tasks.forEach(task => {
            const key = `${task.event_id}:${task.schedule_date}`;
            if (!groups.has(key)) groups.set(key, {name: task.event_name, date: task.schedule_date, roles: new Set()});
            groups.get(key).roles.add(task.responsibility);
        });
        return [...groups.values()];
    };

    const openOfficerMenu = (anchor, assignment) => {
        closeOfficerMenu();
        const menu = document.createElement('div');
        menu.dataset.officerActionsMenu = '';
        menu.className = 'fixed z-[80] w-56 rounded-xl border border-[#121017]/10 bg-white p-1.5 shadow-2xl';
        const menuItem = (label, action, danger = false) => {
            const control = button(label, `block w-full rounded-lg px-3 py-2.5 text-left text-xs font-bold transition hover:bg-[#F3F0E9]/60 ${danger ? 'text-[#d9470a]' : 'text-[#121017]'}`, () => {
                closeOfficerMenu();
                action();
            });
            menu.append(control);
        };
        if (assignment.status === 'Active') {
            menuItem('Assign to event', () => openEventAssignments(assignment));
            menuItem('Reset officer login', () => openPasswordDialog(assignment));
            const divider = document.createElement('div');
            divider.className = 'my-1 border-t border-[#121017]/8';
            menu.append(divider);
            menuItem('Unassign officer', () => unassign(assignment), true);
        } else {
            menuItem('View assignment details', () => openDetailsDialog(assignment));
        }
        document.body.append(menu);
        const anchorRect = anchor.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        menu.style.left = `${Math.max(12, Math.min(innerWidth - menuRect.width - 12, anchorRect.right - menuRect.width))}px`;
        menu.style.top = `${Math.min(innerHeight - menuRect.height - 12, anchorRect.bottom + 6)}px`;
    };

    const unassign = async assignment => {
        const accepted = await confirmAction({
            title: 'Unassign officer?',
            message: 'Their officer login will be disabled immediately. The student account and assignment history will remain unchanged.',
            action: 'Unassign',
        });
        if (!accepted) return;
        try {
            const response = await axios.post('api/officers.php', { action: 'unassign', id: assignment.id }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            notify('success', response.data.message);
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        }
    };

    const openPasswordDialog = assignment => {
        passwordForm.reset();
        passwordForm.elements.password_confirmation.setCustomValidity('');
        passwordForm.querySelectorAll('[data-password-toggle]').forEach(toggle => {
            toggle.parentElement.querySelector('input').type = 'password';
            toggle.querySelector('[data-eye-slash]')?.remove();
            toggle.setAttribute('aria-pressed', 'false');
        });
        passwordForm.elements.assignment_id.value = assignment.id;
        passwordForm.querySelector('[data-password-officer-name]').textContent = assignment.full_name;
        passwordForm.querySelector('[data-password-student-email]').textContent = `Student email: ${assignment.student_email || 'No email provided'}`;
        passwordForm.querySelector('[data-password-officer-username]').textContent = `SBO username: ${assignment.username}`;
        passwordDialog.showModal();
        passwordForm.elements.password.focus();
    };

    const openDetailsDialog = assignment => {
        detailsDialog.querySelector('[data-details-student-name]').textContent = assignment.full_name;
        detailsDialog.querySelector('[data-details-student-id]').textContent = `Student ID: ${assignment.student_id}`;
        detailsDialog.querySelector('[data-details-student-email]').textContent = `Student email: ${assignment.student_email || 'No email provided'}`;
        detailsDialog.querySelector('[data-details-officer-username]').textContent = assignment.username;
        const status = detailsDialog.querySelector('[data-details-status]');
        status.textContent = assignment.status;
        status.className = `rounded-full px-3 py-1.5 text-[10px] font-black uppercase ${assignment.status === 'Active' ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40'}`;
        detailsDialog.querySelector('[data-details-password-state]').textContent = assignment.must_change_password
            ? 'A temporary password is active. The officer must replace it after signing in.'
            : 'The officer has completed their required password change.';
        detailsDialog.querySelector('[data-details-position]').textContent = `Position: ${assignment.position}`;
        detailsDialog.querySelector('[data-details-term]').textContent = `Term: ${assignment.term}`;
        detailsDialog.querySelector('[data-details-team]').textContent = `Tribe: ${assignment.team_name || 'No tribe'}`;
        scannerForm.hidden = assignment.status !== 'Active';
        scannerForm.elements.assignment_id.value = assignment.id;
        scannerForm.elements.scanner_mode.value = assignment.scanner_mode || 'specific';
        scannerForm.elements.team_id.value = String(assignment.team_id || '');
        const responsibilityHost = detailsDialog.querySelector('[data-details-event-responsibilities]');
        responsibilityHost.replaceChildren();
        if (!assignment.event_responsibilities.length) {
            const empty = document.createElement('p');
            empty.className = 'text-xs text-[#121017]/50';
            empty.textContent = assignment.status === 'Active' ? 'No active event responsibility yet.' : 'This officer login is inactive.';
            responsibilityHost.append(empty);
        } else assignment.event_responsibilities.forEach(task => {
            const item = document.createElement('div');
            item.className = 'rounded-xl border border-[#397565]/15 bg-[#397565]/5 p-3';
            const eventName = document.createElement('strong');
            eventName.className = 'block text-sm';
            eventName.textContent = task.event_name;
            const context = document.createElement('span');
            context.className = 'mt-1 block text-xs text-[#121017]/60';
            context.textContent = `${task.schedule_date} · ${task.session_code.replace('_', ' ')} · ${task.responsibility} · ${task.team_name} · ${task.activity_name}`;
            item.append(eventName, context);
            responsibilityHost.append(item);
        });
        const assignEvent = detailsDialog.querySelector('[data-details-assign-event]');
        assignEvent.hidden = assignment.status !== 'Active';
        assignEvent.onclick = () => {
            detailsDialog.close();
            openEventAssignments(assignment);
        };
        const changePassword = detailsDialog.querySelector('[data-details-change-password]');
        changePassword.hidden = assignment.status !== 'Active';
        changePassword.onclick = () => {
            detailsDialog.close();
            openPasswordDialog(assignment);
        };
        detailsDialog.showModal();
    };

    const renderAssignments = data => {
        list.replaceChildren();
        document.querySelector('[data-officer-result-count]').textContent = `${data.pagination.total} ${data.pagination.total === 1 ? 'assignment' : 'assignments'} found`;
        if (!data.assignments.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-14 text-center';
            const filtered = [...new FormData(filters).values()].some(Boolean);
            empty.innerHTML = `<strong class="text-base font-black">${filtered ? 'No officers match these filters' : 'No officer assignments yet'}</strong><p class="mt-1 text-sm text-[#121017]/45">${filtered ? 'Try another search or assignment status.' : 'Assign an existing student to create their separate officer login.'}</p>`;
            list.append(empty);
            syncSelection();
            return;
        }

        data.assignments.forEach(assignment => {
            const active = assignment.status === 'Active';
            const article = document.createElement('article');
            article.className = 'grid gap-4 px-5 py-5 lg:grid-cols-[auto_minmax(220px,1fr)_minmax(180px,.7fr)_minmax(180px,.7fr)_auto] lg:items-center';

            const check = document.createElement('input');
            check.className = 'h-4 w-4 accent-[#397565] disabled:opacity-25';
            check.type = 'checkbox';
            check.value = assignment.id;
            check.dataset.officerCheckbox = '';
            check.disabled = !active;
            check.setAttribute('aria-label', `Select ${assignment.full_name}`);
            check.addEventListener('change', syncSelection);

            const identity = document.createElement('div');
            identity.className = 'min-w-0';
            identity.innerHTML = '<strong class="block truncate text-sm font-black"></strong><span class="mt-1 block truncate text-xs text-[#121017]/45"></span>';
            identity.querySelector('strong').textContent = assignment.full_name;
            identity.querySelector('span').textContent = `${assignment.school_year_label ? `SY ${assignment.school_year_label}` : assignment.term} · ${assignment.team_name || 'No tribe assigned'}`;
            const account = document.createElement('div');
            account.innerHTML = '<strong class="block text-sm">SBO Officer account</strong><span class="mt-1 block text-[10px] text-[#121017]/45"></span>';
            const position = assignment.position ? assignment.position.charAt(0).toUpperCase() + assignment.position.slice(1) : 'Officer';
            account.querySelector('span').textContent = `${position} · Term ${assignment.term}`;
            const eventCell = document.createElement('div');
            eventCell.className = 'mt-3 min-w-0 rounded-xl bg-[#397565]/5 p-3';
            const eventLabel = document.createElement('strong');
            eventLabel.className = 'block text-xs font-black text-[#397565]';
            eventLabel.textContent = 'Events in charge';
            eventCell.append(eventLabel);
            const groups = eventGroups(assignment.event_responsibilities);
            if (!groups.length) {
                const empty = document.createElement('p');
                empty.className = 'mt-1 text-xs text-[#121017]/45';
                empty.textContent = active ? 'No event assigned' : 'Officer login inactive';
                eventCell.append(empty);
            } else {
                groups.slice(0, 2).forEach(group => {
                    const item = document.createElement('p');
                    item.className = 'mt-1 text-xs leading-5';
                    const title = document.createElement('span');
                    title.className = 'block truncate font-bold';
                    title.textContent = group.name;
                    const detail = document.createElement('small');
                    detail.className = 'block text-[#121017]/50';
                    detail.textContent = `${group.date} · ${[...group.roles].join(', ')}`;
                    item.append(title, detail);
                    eventCell.append(item);
                });
                if (groups.length > 2) {
                    const moreEvents = document.createElement('small');
                    moreEvents.className = 'mt-1 block font-bold text-[#397565]';
                    moreEvents.textContent = `+${groups.length - 2} more in View Details`;
                    eventCell.append(moreEvents);
                }
            }
            if (active) eventCell.append(button('Assign to event', 'mt-2 min-h-9 rounded-lg border border-[#397565]/25 px-3 text-xs font-black text-[#397565]', () => openEventAssignments(assignment)));
            const actions = document.createElement('div');
            actions.className = 'flex flex-wrap items-center justify-end gap-2';
            const status = document.createElement('span');
            status.className = `w-fit rounded-full px-3 py-1.5 text-[10px] font-black uppercase ${active ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40'}`;
            status.textContent = assignment.status;
            actions.append(button('View Details', 'min-h-9 rounded-lg border border-[#397565]/25 bg-[#397565]/8 px-3 text-xs font-black text-[#397565]', () => {
                closeOfficerMenu();
                openDetailsDialog(assignment);
            }));
            const more = button('⋯', 'grid h-9 w-9 place-items-center rounded-lg border border-[#121017]/10 text-lg font-black text-[#121017]/45 hover:bg-[#F3F0E9]/60', event => openOfficerMenu(event.currentTarget, assignment));
            more.dataset.officerMenuTrigger = '';
            more.setAttribute('aria-label', `More actions for ${assignment.full_name}`);
            actions.append(more);
            identity.append(eventCell);
            article.append(check, identity, account, status, actions);
            list.append(article);
        });
        syncSelection();
    };

    const renderPagination = data => {
        pagination.classList.toggle('hidden', data.pagination.last_page <= 1);
        pagination.replaceChildren();
        if (data.pagination.last_page <= 1) return;
        const nav = document.createElement('nav');
        nav.className = 'flex flex-col items-center justify-between gap-3 text-xs sm:flex-row';
        const count = document.createElement('small');
        count.className = 'text-[#121017]/40';
        count.textContent = `Showing ${data.pagination.from}–${data.pagination.to} of ${data.pagination.total}`;
        const controls = document.createElement('div');
        controls.className = 'flex items-center gap-2';
        const pageButton = (label, target, disabled) => {
            const element = document.createElement(disabled ? 'span' : 'button');
            element.className = disabled
                ? 'rounded-lg border border-[#121017]/8 bg-[#F3F0E9]/50 px-3 py-2 text-[#121017]/25'
                : 'rounded-lg border border-[#121017]/12 px-3 py-2 font-bold text-[#121017]/65 hover:bg-[#F3F0E9]/50';
            element.textContent = label;
            if (!disabled) {
                element.type = 'button';
                element.addEventListener('click', async () => {
                    currentPage = target;
                    await load();
                    updateUrl();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            return element;
        };
        const position = document.createElement('span');
        position.className = 'px-2 text-[#121017]/40';
        position.textContent = `${data.pagination.current_page} / ${data.pagination.last_page}`;
        controls.append(
            pageButton('Previous', data.pagination.current_page - 1, data.pagination.current_page === 1),
            position,
            pageButton('Next', data.pagination.current_page + 1, data.pagination.current_page === data.pagination.last_page),
        );
        nav.append(count, controls);
        pagination.append(nav);
    };

    const showSelectedStudent = () => {
        const selected = assignForm.querySelector('input[name="student_user_id"]:checked');
        selectedStudentLabel.textContent = selected
            ? `Selected: ${students.find(student => String(student.id) === selected.value)?.full_name || ''}`
            : 'Select one student to continue.';
    };

    const filterStudents = () => {
        const term = studentSearch.value.trim().toLowerCase();
        const year = studentYear.value;
        let visible = 0;
        studentList.querySelectorAll('[data-officer-student-option]').forEach(option => {
            const matches = (!term || option.dataset.search.includes(term)) && (!year || option.dataset.year === year);
            option.classList.toggle('hidden', !matches);
            const selected = option.querySelector('input:checked');
            if (!matches && selected) selected.checked = false;
            visible += Number(matches);
        });
        studentList.querySelector('[data-officer-student-empty]')?.classList.toggle('hidden', visible > 0);
        showSelectedStudent();
    };

    const renderFormOptions = data => {
        students = data.students;
        studentYear.replaceChildren(new Option('All year levels', ''));
        data.year_levels.forEach(level => studentYear.add(new Option(level.label, level.id)));
        studentYear.add(new Option('No year level', 'none'));
        const team = assignForm.elements.team_id;
        team.replaceChildren(new Option('Select a tribe', ''));
        data.teams.forEach(item => team.add(new Option(item.name, item.id)));
        const scannerTeam = scannerForm.elements.team_id;
        const previousScannerTeam = scannerTeam.value;
        scannerTeam.replaceChildren(...data.teams.map(item => new Option(item.name, item.id)));
        if (previousScannerTeam) scannerTeam.value = previousScannerTeam;
        studentList.replaceChildren();
        data.students.forEach(student => {
            const option = document.createElement('label');
            option.className = 'group flex cursor-pointer items-center gap-3 rounded-xl border border-transparent bg-white px-3 py-2.5 transition hover:border-[#397565]/25 hover:bg-[#397565]/5 has-[:checked]:border-[#397565] has-[:checked]:bg-[#397565]/8';
            option.dataset.officerStudentOption = '';
            option.dataset.search = `${student.full_name} ${student.id_number} ${student.email}`.toLowerCase();
            option.dataset.year = student.year_level ?? 'none';
            const radio = document.createElement('input');
            radio.className = 'h-4 w-4 shrink-0 accent-[#397565]';
            radio.name = 'student_user_id';
            radio.value = student.id;
            radio.type = 'radio';
            radio.required = true;
            radio.dataset.suggestedUsername = student.suggested_username;
            const avatar = document.createElement('span');
            avatar.className = 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[10px] font-black text-white';
            avatar.textContent = initials(student);
            const details = document.createElement('span');
            details.className = 'min-w-0 flex-1';
            details.innerHTML = '<strong class="block truncate text-xs font-black"></strong><small class="mt-0.5 block truncate text-[10px] text-[#121017]/45"></small>';
            details.querySelector('strong').textContent = student.full_name;
            details.querySelector('small').textContent = `${student.id_number} · ${student.email}`;
            const year = document.createElement('span');
            year.className = 'shrink-0 rounded-full bg-[#2F3AE0]/8 px-2.5 py-1 text-[9px] font-black text-[#2F3AE0]';
            year.textContent = student.year_level_label || 'No year';
            option.append(radio, avatar, details, year);
            studentList.append(option);
        });
        const empty = document.createElement('p');
        empty.className = `${data.students.length ? 'hidden ' : ''}px-4 py-8 text-center text-sm text-[#121017]/45`;
        empty.dataset.officerStudentEmpty = '';
        empty.textContent = data.students.length ? 'No students match your search and year level.' : 'No active student accounts are available.';
        studentList.append(empty);
    };

    const load = async () => {
        const params = Object.fromEntries(new FormData(filters));
        params.page = currentPage;
        const response = await axios.get('api/officers.php', { params });
        currentPage = response.data.data.pagination.current_page;
        renderAssignments(response.data.data);
        renderPagination(response.data.data);
        renderFormOptions(response.data.data);
    };

    document.querySelector('[data-task-dialog]')?.addEventListener('close', () => load().catch(error => notify('error', errorMessage(error))));

    selectAll.addEventListener('change', () => {
        list.querySelectorAll('[data-officer-checkbox]:not(:disabled)').forEach(input => { input.checked = selectAll.checked; });
        syncSelection();
    });
    document.querySelector('[data-clear-officer-selection]').addEventListener('click', () => {
        list.querySelectorAll('[data-officer-checkbox]').forEach(input => { input.checked = false; });
        syncSelection();
    });
    filters.addEventListener('change', async () => {
        currentPage = 1;
        await load();
        updateUrl();
    });
    filters.elements.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async () => {
            currentPage = 1;
            await load();
            updateUrl();
        }, 320);
    });
    listForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
        const ids = [...list.querySelectorAll('[data-officer-checkbox]:checked')].map(input => Number(input.value));
        if (!ids.length) return;
        const accepted = await confirmAction({
            title: 'Unassign selected officers?',
            message: 'Their officer logins will be disabled immediately. Student accounts and assignment history will remain unchanged.',
            action: 'Unassign selected',
        });
        if (!accepted) return;
        try {
            const response = await axios.post('api/officers.php', { action: 'batch_unassign', assignment_ids: ids }, { headers: { 'X-CSRF-Token': csrfToken } });
            notify('success', response.data.message);
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        }
    });
    document.querySelector('[data-dialog-open="assign-officer-dialog"]').addEventListener('click', () => {
        assignForm.reset();
        studentSearch.value = '';
        studentYear.value = '';
        filterStudents();
        dialog.showModal();
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => dialog.close()));
    detailsDialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => detailsDialog.close()));
    scannerForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
        if (!scannerForm.reportValidity()) return;
        const submit = submitEvent.submitter;
        window.Notifications?.setLoading(submit, true, 'Saving access…');
        try {
            const data = Object.fromEntries(new FormData(scannerForm));
            data.action = 'scanner_config';
            const response = await axios.post('api/officers.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            detailsDialog.close();
            notify('success', response.data.message);
            await load();
        } catch (error) { notify('error', errorMessage(error)); }
        finally { window.Notifications?.setLoading(submit, false); }
    });
    passwordDialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => passwordDialog.close()));
    passwordDialog.querySelectorAll('[data-password-toggle]').forEach(toggle => toggle.addEventListener('click', () => {
        const input = toggle.parentElement.querySelector('input');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        const icon = toggle.querySelector('svg');
        icon.querySelector('[data-eye-slash]')?.remove();
        if (!showing) {
            const slash = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            slash.setAttribute('d', 'M4 4 20 20');
            slash.dataset.eyeSlash = '';
            icon.append(slash);
        }
        toggle.setAttribute('aria-pressed', String(!showing));
        toggle.setAttribute('aria-label', `${showing ? 'Show' : 'Hide'} ${input.name === 'password_confirmation' ? 'password confirmation' : 'new password'}`);
    }));
    studentSearch.addEventListener('input', filterStudents);
    studentYear.addEventListener('change', filterStudents);
    studentList.addEventListener('change', showSelectedStudent);
    document.querySelector('[data-generate-officer-username]').addEventListener('click', () => {
        const selected = assignForm.querySelector('input[name="student_user_id"]:checked');
        if (selected?.dataset.suggestedUsername) username.value = selected.dataset.suggestedUsername;
    });
    assignForm.elements.password_confirmation.addEventListener('input', () => {
        const matches = assignForm.elements.password.value === assignForm.elements.password_confirmation.value;
        assignForm.elements.password_confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
    });
    assignForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
        if (!assignForm.reportValidity()) return;
        const submit = submitEvent.submitter;
        window.Notifications?.setLoading(submit, true, 'Creating officer…');
        const data = Object.fromEntries(new FormData(assignForm));
        data.action = 'assign';
        try {
            const response = await axios.post('api/officers.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            dialog.close();
            notify('success', response.data.message);
            currentPage = 1;
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        } finally {
            window.Notifications?.setLoading(submit, false);
        }
    });
    const validatePasswordConfirmation = () => {
        const matches = passwordForm.elements.password.value === passwordForm.elements.password_confirmation.value;
        passwordForm.elements.password_confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
    };
    passwordForm.elements.password.addEventListener('input', validatePasswordConfirmation);
    passwordForm.elements.password_confirmation.addEventListener('input', validatePasswordConfirmation);
    passwordForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
        if (!passwordForm.reportValidity()) return;
        const submit = submitEvent.submitter;
        window.Notifications?.setLoading(submit, true, 'Changing password…');
        const data = Object.fromEntries(new FormData(passwordForm));
        data.action = 'change_password';
        try {
            const response = await axios.post('api/officers.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            passwordDialog.close();
            notify('success', response.data.message);
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        } finally {
            window.Notifications?.setLoading(submit, false);
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-officer-actions-menu]') && !event.target.closest('[data-officer-menu-trigger]')) closeOfficerMenu();
    });
    addEventListener('resize', closeOfficerMenu);
    addEventListener('scroll', closeOfficerMenu, true);
    logoutForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
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

    const initialFilters = new URLSearchParams(location.search);
    filters.elements.search.value = initialFilters.get('search') || '';
    filters.elements.status.value = initialFilters.get('status') || '';

    axios.get('api/auth.php?action=session').then(response => {
        if (response.data.user?.role !== 'SBO Adviser') {
            location.replace('./');
            return;
        }
        csrfToken = response.data.csrf_token;
        const account = document.querySelector('[data-account-menu]');
        const user = response.data.user;
        account.querySelector('summary > span:first-child').childNodes[0].textContent = initials(user);
        account.querySelector('summary strong').textContent = user.full_name;
        account.querySelector('summary small').textContent = user.role;
        account.querySelector('div > div strong').textContent = user.full_name;
        account.querySelector('div > div span').textContent = user.email || user.username;
        return load();
    }).catch(() => location.replace('./'));
});
