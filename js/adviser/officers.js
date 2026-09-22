window.SharedNavigation.ready.then(() => {
    'use strict';

    let csrfToken = '';
    let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);
    let searchTimer;
    let candidateSearchTimer;
    let candidatePage = 1;
    let candidateRequest = 0;
    let selectedStudentIds = new Set();
    let currentCredentials = null;
    const pendingUnassign = new Set();

    const list = document.querySelector('[data-officer-list]');
    const filters = document.querySelector('[data-officer-filters]');
    const pagination = document.querySelector('[data-officer-pagination]');
    const dialog = document.querySelector('#assign-officer-dialog');
    const assignForm = document.querySelector('[data-assign-officer-form]');
    const detailsDialog = document.querySelector('#officer-details-dialog');
    const passwordDialog = document.querySelector('#change-officer-password-dialog');
    const passwordForm = document.querySelector('[data-change-officer-password-form]');
    const credentialsDialog = document.querySelector('#officer-credentials-dialog');
    const studentList = document.querySelector('[data-officer-student-list]');
    const studentSearch = document.querySelector('[data-officer-student-search]');
    const studentYear = document.querySelector('[data-officer-year-filter]');
    const selectedStudentLabel = document.querySelector('[data-selected-student-label]');
    const selectFilteredStudents = document.querySelector('[data-select-filtered-students]');
    const candidateCount = document.querySelector('[data-officer-candidate-count]');
    const candidatePagination = document.querySelector('[data-officer-candidate-pagination]');
    const logoutForm = document.querySelector('form[action="api/auth.php?action=logout"]');

    const initials = person => `${person.first_name?.[0] || ''}${person.last_name?.[0] || ''}`.toUpperCase();
    const visibleEmail = person => /@pending\.invalid$/i.test(String(person?.email || person?.student_email || ''))
        ? ''
        : String(person?.email || person?.student_email || '');
    const errorMessage = error => error.response?.data?.message || 'Unable to complete the request.';
    const notify = (type, message) => window.Notifications?.[type]?.(message);

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
        : false;

    const credentialSlip = credentials => [
        'CITE Events — SBO Officer Access',
        '',
        `Officer: ${credentials.officer_name}`,
        `Username: ${credentials.username}`,
        `One-time password: ${credentials.temporary_password}`,
        `Sign in: ${new URL('./', document.baseURI).href}`,
        '',
        'This is a separate Officer sign-in. The student account and password are unchanged.',
        'The officer must create a new private password at first sign-in.',
    ].join('\n');

    const credentialFeedback = message => {
        const feedback = credentialsDialog.querySelector('[data-credential-feedback]');
        feedback.textContent = message;
        feedback.classList.remove('hidden');
    };

    const showOfficerCredentials = created => {
        const credentials = Array.isArray(created) ? created : [created];
        const first = credentials[0];
        currentCredentials = credentials.map(item => ({ ...item }));
        credentialsDialog.querySelector('[data-credential-name]').textContent = credentials.length === 1 ? first.officer_name : `${credentials.length} SBO Officer accounts`;
        const officerNames = first.officer_name.trim().split(/\s+/).filter(Boolean);
        credentialsDialog.querySelector('[data-credential-initials]').textContent = officerNames.length > 1
            ? `${officerNames[0][0]}${officerNames[officerNames.length - 1][0]}`.toUpperCase()
            : (officerNames[0] || 'SO').slice(0, 2).toUpperCase();
        credentialsDialog.querySelector('[data-credential-username]').textContent = first.username;
        credentialsDialog.querySelector('[data-credential-password]').textContent = first.temporary_password;
        const list = credentialsDialog.querySelector('[data-credential-list]');
        list.replaceChildren();
        list.classList.toggle('hidden', credentials.length === 1);
        credentials.slice(1).forEach(item => {
            const row = document.createElement('div');
            row.className = 'rounded-xl bg-[#F3F0E9]/55 p-4 text-xs';
            row.innerHTML = '<strong class="block text-sm"></strong><span class="mt-2 block">Username: <code class="font-black"></code></span><span class="mt-1 block">One-time password: <code class="font-black"></code></span>';
            row.querySelector('strong').textContent = item.officer_name;
            const codes = row.querySelectorAll('code'); codes[0].textContent = item.username; codes[1].textContent = item.temporary_password;
            list.append(row);
        });
        credentialsDialog.querySelector('[data-credential-feedback]').classList.add('hidden');
        credentialsDialog.showModal();
    };

    const copyText = async text => {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const field = document.createElement('textarea');
        field.value = text;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Clipboard copy was not available.');
    };

    const updateUrl = () => {
        const url = new URL(location.href);
        url.search = '';
        new FormData(filters).forEach((value, key) => {
            if (value) url.searchParams.set(key, value);
        });
        if (currentPage > 1) url.searchParams.set('page', String(currentPage));
        history.replaceState(null, '', url);
    };


    const closeOfficerMenu = () => document.querySelector('[data-officer-actions-menu]')?.remove();

    const openEventAssignments = assignment => {
        if (assignment.status !== 'Active') return notify('error', 'Only active SBO Officers can be assigned to an event.');
        window.SboOfficerAssignments?.activate(assignment.id)
            ?? notify('error', 'Event responsibilities are unavailable. Reload this page and try again.');
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
        if (pendingUnassign.has(assignment.id)) return;
        const accepted = await confirmAction({
            title: 'Unassign officer?',
            message: 'Their officer login will be disabled immediately. The student account and assignment history will remain unchanged.',
            action: 'Unassign',
        });
        if (!accepted) return;
        pendingUnassign.add(assignment.id);
        try {
            const response = await axios.post('api/officers.php', { action: 'unassign', id: assignment.id }, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            notify('success', response.data.message);
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        } finally {
            pendingUnassign.delete(assignment.id);
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
        passwordForm.querySelector('[data-password-student-email]').textContent = visibleEmail(assignment)
            ? `Student email: ${visibleEmail(assignment)}`
            : `Student ID: ${assignment.student_id}`;
        passwordForm.querySelector('[data-password-officer-username]').textContent = `SBO username: ${assignment.username}`;
        passwordDialog.showModal();
        passwordForm.elements.password.focus();
    };

    const openDetailsDialog = assignment => {
        detailsDialog.querySelector('[data-details-student-name]').textContent = assignment.full_name;
        detailsDialog.querySelector('[data-details-student-id]').textContent = `Student ID: ${assignment.student_id}`;
        const email = visibleEmail(assignment);
        const emailDetail = detailsDialog.querySelector('[data-details-student-email]');
        emailDetail.textContent = email ? `Student email: ${email}` : '';
        emailDetail.classList.toggle('hidden', !email);
        detailsDialog.querySelector('[data-details-officer-username]').textContent = assignment.username;
        const status = detailsDialog.querySelector('[data-details-status]');
        status.textContent = assignment.status;
        status.className = `rounded-full px-3 py-1.5 text-[10px] font-black uppercase ${assignment.status === 'Active' ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40'}`;
        detailsDialog.querySelector('[data-details-password-state]').textContent = assignment.must_change_password
            ? 'A temporary password is active. The officer must replace it after signing in.'
            : 'The officer has completed their required password change.';
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
        document.querySelector('[data-officer-result-count]').textContent = `${data.pagination.total} ${data.pagination.total === 1 ? 'officer' : 'officers'} found`;
        if (!data.assignments.length) {
            const row = document.createElement('tr');
            row.innerHTML = `<td class="px-5 py-12 text-center text-sm text-[#121017]/45" colspan="6">${[...new FormData(filters).values()].some(Boolean) ? 'No officers match these filters.' : 'No SBO Officer accounts have been created yet.'}</td>`;
            list.append(row);
            return;
        }
        data.assignments.forEach(assignment => {
            const active = assignment.status === 'Active';
            const row = document.createElement('tr');
            row.innerHTML = `<td class="px-5 py-4"><strong class="block whitespace-nowrap font-black"></strong><span class="mt-1 block text-xs text-[#121017]/50"></span></td><td class="whitespace-nowrap px-4 py-4 text-[#121017]/65"></td><td class="whitespace-nowrap px-4 py-4 font-bold text-[#121017]/65"></td><td class="whitespace-nowrap px-4 py-4 text-[#121017]/65"></td><td class="px-4 py-4"></td><td class="px-5 py-4 text-right"></td>`;
            const cells = row.querySelectorAll('td');
            cells[0].querySelector('strong').textContent = assignment.full_name;
            cells[0].querySelector('span').textContent = visibleEmail(assignment) || `Student ID: ${assignment.student_id}`;
            cells[1].textContent = assignment.student_id;
            cells[2].textContent = assignment.username;
            cells[3].textContent = new Date(assignment.assigned_at).toLocaleDateString();
            const status = document.createElement('span');
            status.className = `rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${active ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40'}`;
            status.textContent = assignment.status;
            cells[4].append(status);
            const edit = button('Edit status', 'min-h-9 rounded-lg border border-[#397565]/25 px-3 text-xs font-black text-[#397565] hover:bg-[#397565]/5', () => openStatusDialog(assignment));
            cells[5].append(edit);
            list.append(row);
        });
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

    const openStatusDialog = async assignment => {
        const nextStatus = assignment.status === 'Active' ? 'inactive' : 'active';
        const accepted = await confirmAction({
            title: `${nextStatus === 'active' ? 'Activate' : 'Deactivate'} SBO Officer account?`,
            message: nextStatus === 'active'
                ? 'This restores the officer’s separate SBO login and its existing event responsibilities.'
                : 'This disables the officer’s separate SBO login. The student account will not be changed.',
            action: nextStatus === 'active' ? 'Activate account' : 'Deactivate account',
        });
        if (!accepted) return;
        try {
            const response = await axios.post('api/officers.php', { action: 'update_status', id: assignment.id, status: nextStatus }, { headers: { 'X-CSRF-Token': csrfToken } });
            notify('success', response.data.message);
            await load();
        } catch (error) {
            notify('error', errorMessage(error));
        }
    };

    const showSelectedStudent = () => {
        const count = selectedStudentIds.size;
        selectedStudentLabel.textContent = count ? `${count} student${count === 1 ? '' : 's'} selected.` : 'Select one or more students to continue.';
    };

    const updatePageSelectionAction = () => {
        const pageChecks = [...studentList.querySelectorAll('input[name="student_user_ids[]"]')];
        selectFilteredStudents.disabled = !pageChecks.length;
        selectFilteredStudents.textContent = pageChecks.length && pageChecks.every(input => selectedStudentIds.has(Number(input.value)))
            ? 'Clear page'
            : 'Select page';
        showSelectedStudent();
    };

    const renderCandidatePagination = paginationData => {
        const visible = paginationData.last_page > 1;
        candidatePagination.classList.toggle('hidden', !visible);
        candidatePagination.classList.toggle('flex', visible);
        candidatePagination.replaceChildren();
        if (!visible) return;
        const position = document.createElement('span');
        position.textContent = `Page ${paginationData.current_page} of ${paginationData.last_page}`;
        const controls = document.createElement('span');
        controls.className = 'flex gap-2';
        const addButton = (label, target, disabled) => {
            const control = document.createElement('button');
            control.type = 'button';
            control.className = 'min-h-9 rounded-lg border border-[#121017]/12 px-3 font-bold disabled:opacity-30';
            control.textContent = label;
            control.disabled = disabled;
            control.addEventListener('click', () => loadCandidateStudents(target));
            controls.append(control);
        };
        addButton('Previous', paginationData.current_page - 1, paginationData.current_page === 1);
        addButton('Next', paginationData.current_page + 1, paginationData.current_page === paginationData.last_page);
        candidatePagination.append(position, controls);
    };

    const renderCandidateStudents = data => {
        studentList.replaceChildren();
        data.students.forEach(student => {
            const option = document.createElement('label');
            option.className = 'group flex cursor-pointer items-center gap-3 rounded-xl border border-transparent bg-white px-3 py-2.5 transition hover:border-[#397565]/25 hover:bg-[#397565]/5 has-[:checked]:border-[#397565] has-[:checked]:bg-[#397565]/8';
            option.dataset.officerStudentOption = '';
            const checkbox = document.createElement('input');
            checkbox.className = 'h-4 w-4 shrink-0 accent-[#397565]';
            checkbox.name = 'student_user_ids[]';
            checkbox.value = student.id;
            checkbox.type = 'checkbox';
            checkbox.checked = selectedStudentIds.has(Number(student.id));
            const avatar = document.createElement('span');
            avatar.className = 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[10px] font-black text-white';
            avatar.textContent = initials(student);
            const details = document.createElement('span');
            details.className = 'min-w-0 flex-1';
            details.innerHTML = '<strong class="block truncate text-xs font-black"></strong><small class="mt-0.5 block truncate text-[10px] text-[#121017]/45"></small>';
            details.querySelector('strong').textContent = student.full_name;
            details.querySelector('small').textContent = visibleEmail(student)
                ? `${student.id_number} · ${visibleEmail(student)}`
                : student.id_number;
            const year = document.createElement('span');
            year.className = 'shrink-0 rounded-full bg-[#2F3AE0]/8 px-2.5 py-1 text-[9px] font-black text-[#2F3AE0]';
            year.textContent = student.year_level_label || 'No year';
            option.append(checkbox, avatar, details, year);
            studentList.append(option);
        });
        const empty = document.createElement('p');
        empty.className = `${data.students.length ? 'hidden ' : ''}px-4 py-8 text-center text-sm text-[#121017]/45`;
        empty.dataset.officerStudentEmpty = '';
        empty.textContent = 'No eligible students match this search and year level.';
        studentList.append(empty);
        const page = data.pagination;
        candidatePage = page.current_page;
        candidateCount.textContent = page.total
            ? `Showing ${page.from.toLocaleString()}–${page.to.toLocaleString()} of ${page.total.toLocaleString()} eligible students`
            : 'No eligible students found.';
        renderCandidatePagination(page);
        updatePageSelectionAction();
    };

    const loadCandidateStudents = async (targetPage = candidatePage) => {
        const request = ++candidateRequest;
        candidateCount.textContent = 'Loading eligible students…';
        selectFilteredStudents.disabled = true;
        try {
            const response = await axios.get('api/officers.php', { params: {
                action: 'eligible_students',
                search: studentSearch.value.trim(),
                year_level: studentYear.value,
                page: targetPage,
            }});
            if (request !== candidateRequest) return;
            renderCandidateStudents(response.data.data);
        } catch (error) {
            if (request !== candidateRequest) return;
            studentList.replaceChildren();
            candidatePagination.classList.add('hidden');
            candidateCount.textContent = errorMessage(error);
        }
    };

    const renderCandidateFilters = data => {
        if (studentYear.options.length > 1) return;
        data.year_levels.forEach(level => studentYear.add(new Option(level.label, level.id)));
        studentYear.add(new Option('No year level', 'none'));
    };

    const load = async () => {
        const params = Object.fromEntries(new FormData(filters));
        params.page = currentPage;
        const response = await axios.get('api/officers.php', { params });
        currentPage = response.data.data.pagination.current_page;
        renderAssignments(response.data.data);
        renderPagination(response.data.data);
        renderCandidateFilters(response.data.data);
    };

    document.querySelector('[data-officer-tab="officers"]')?.addEventListener('click', () => load().catch(error => notify('error', errorMessage(error))));

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
    document.querySelector('[data-dialog-open="assign-officer-dialog"]').addEventListener('click', () => {
        assignForm.reset();
        selectedStudentIds = new Set();
        candidatePage = 1;
        candidateRequest++;
        studentSearch.value = '';
        studentYear.value = '';
        studentList.innerHTML = '<p class="px-4 py-8 text-center text-sm text-[#121017]/45">Loading eligible students…</p>';
        candidatePagination.classList.add('hidden');
        selectFilteredStudents.disabled = true;
        showSelectedStudent();
        dialog.showModal();
        loadCandidateStudents(1);
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => dialog.close()));
    detailsDialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => detailsDialog.close()));
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
    studentSearch.addEventListener('input', () => {
        clearTimeout(candidateSearchTimer);
        candidateSearchTimer = setTimeout(() => loadCandidateStudents(1), 300);
    });
    studentYear.addEventListener('change', () => loadCandidateStudents(1));
    studentList.addEventListener('change', event => {
        const input = event.target.closest('input[name="student_user_ids[]"]');
        if (!input) return;
        input.checked ? selectedStudentIds.add(Number(input.value)) : selectedStudentIds.delete(Number(input.value));
        updatePageSelectionAction();
    });
    selectFilteredStudents.addEventListener('click', () => {
        const pageChecks = [...studentList.querySelectorAll('input[name="student_user_ids[]"]')];
        const shouldSelect = pageChecks.some(input => !selectedStudentIds.has(Number(input.value)));
        pageChecks.forEach(input => {
            input.checked = shouldSelect;
            shouldSelect ? selectedStudentIds.add(Number(input.value)) : selectedStudentIds.delete(Number(input.value));
        });
        updatePageSelectionAction();
    });
    credentialsDialog.addEventListener('cancel', event => event.preventDefault());
    credentialsDialog.querySelector('[data-copy-officer-credentials]').addEventListener('click', async () => {
        if (!currentCredentials) return;
        try {
            await copyText(currentCredentials.map(credentialSlip).join('\n\n'));
            credentialFeedback('Credentials copied. Share them privately with the officer.');
        } catch (error) {
            credentialFeedback(error.message || 'Copy was unavailable. Use Download credential slip instead.');
        }
    });
    credentialsDialog.querySelector('[data-download-officer-credentials]').addEventListener('click', () => {
        if (!currentCredentials) return;
        const blob = new Blob([currentCredentials.map(credentialSlip).join('\n\n')], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `officer-access-${currentCredentials.length === 1 ? currentCredentials[0].username.replace(/[^a-z0-9._-]/gi, '-') : 'batch'}.txt`;
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        credentialFeedback('Credential slip downloaded. Store and share it securely.');
    });
    credentialsDialog.querySelector('[data-credential-done]').addEventListener('click', () => credentialsDialog.close('done'));
    credentialsDialog.addEventListener('close', () => {
        currentCredentials = null;
        credentialsDialog.querySelector('[data-credential-name]').textContent = '';
        credentialsDialog.querySelector('[data-credential-username]').textContent = '';
        credentialsDialog.querySelector('[data-credential-password]').textContent = '';
    });
    assignForm.addEventListener('submit', async submitEvent => {
        submitEvent.preventDefault();
        if (!assignForm.reportValidity()) return;
        const data = { student_user_ids: [...selectedStudentIds] };
        if (!data.student_user_ids.length) { notify('error', 'Select at least one student.'); return; }
        const submit = submitEvent.submitter;
        window.Notifications?.setLoading(submit, true, 'Creating access…');
        data.action = 'assign';
        try {
            const response = await axios.post('api/officers.php', data, { headers: { 'X-CSRF-Token': csrfToken } });
            dialog.close();
            const created = Array.isArray(response.data.data) ? response.data.data : [response.data.data];
            if (created.length > 1) {
                const blob = new Blob([created.map(credentialSlip).join('\n\n')], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob), link = document.createElement('a');
                link.href = url; link.download = 'officer-access-batch.txt'; document.body.append(link); link.click(); link.remove(); URL.revokeObjectURL(url);
                notify('success', 'A credential slip for every new Officer account was downloaded.');
            }
            showOfficerCredentials(created);
            currentPage = 1;
            load().catch(() => notify('error', 'Officer access was created, but the officer list could not refresh. Reload the page to see it.'));
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
