(() => {
    'use strict';

    let csrfToken = '';
    let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);
    let students = [];

    const list = document.querySelector('[data-officer-list]');
    const listForm = document.querySelector('[data-officer-list-form]');
    const selectAll = document.querySelector('[data-select-all-officers]');
    const batchButton = document.querySelector('[data-batch-unassign]');
    const pagination = document.querySelector('[data-officer-pagination]');
    const dialog = document.querySelector('#assign-officer-dialog');
    const assignForm = document.querySelector('[data-assign-officer-form]');
    const detailsDialog = document.querySelector('#officer-details-dialog');
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
        if (currentPage > 1) url.searchParams.set('page', String(currentPage));
        else url.searchParams.delete('page');
        history.replaceState(null, '', url);
    };

    const syncSelection = () => {
        const checks = [...list.querySelectorAll('[data-officer-checkbox]:not(:disabled)')];
        const selected = checks.filter(input => input.checked).length;
        selectAll.checked = checks.length > 0 && selected === checks.length;
        batchButton.disabled = selected === 0;
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
        if (!data.assignments.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-14 text-center';
            empty.innerHTML = '<strong class="text-base font-black">No officer assignments yet</strong><p class="mt-1 text-sm text-[#121017]/45">Assign an existing student to create their separate officer login.</p>';
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
            identity.innerHTML = '<span class="text-[10px] font-black uppercase tracking-wider text-[#121017]/35">Assigned officer</span><strong class="mt-1 block text-sm font-black"></strong><span class="mt-1 block text-xs text-[#121017]/45">Student-linked SBO account</span>';
            identity.querySelector('strong').textContent = assignment.full_name;
            const term = document.createElement('div');
            term.innerHTML = '<span class="text-[10px] font-black uppercase tracking-wider text-[#397565]"></span><strong class="mt-1 block text-sm"></strong>';
            term.querySelector('span').textContent = assignment.position;
            term.querySelector('strong').textContent = assignment.term;
            const account = document.createElement('div');
            account.innerHTML = '<span class="text-[10px] font-black uppercase tracking-wider text-[#397565]">Account</span><strong class="mt-1 block text-sm">SBO Officer login</strong><span class="mt-1 block text-[10px] text-[#121017]/45">Open details to view credentials</span>';
            const actions = document.createElement('div');
            actions.className = 'flex flex-wrap items-center justify-end gap-2';
            const status = document.createElement('span');
            status.className = `rounded-full px-3 py-1.5 text-[10px] font-black uppercase ${active ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40'}`;
            status.textContent = assignment.status;
            actions.append(status);
            actions.append(button('View Details', 'min-h-9 rounded-lg border border-[#397565]/25 bg-[#397565]/8 px-3 text-xs font-black text-[#397565]', () => openDetailsDialog(assignment)));
            if (active) actions.append(button('Unassign', 'min-h-9 rounded-lg border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 px-3 text-xs font-black text-[#d9470a]', () => unassign(assignment)));
            term.append(document.createElement('small'));
            term.lastElementChild.className = 'mt-1 block text-[10px] text-[#121017]/40';
            term.lastElementChild.textContent = `Tribe: ${assignment.team_name || 'No tribe'}`;
            article.append(check, identity, term, account, actions);
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
        const response = await axios.get('api/officers.php', { params: { page: currentPage } });
        currentPage = response.data.data.pagination.current_page;
        renderAssignments(response.data.data);
        renderPagination(response.data.data);
        renderFormOptions(response.data.data);
    };

    selectAll.addEventListener('change', () => {
        list.querySelectorAll('[data-officer-checkbox]:not(:disabled)').forEach(input => { input.checked = selectAll.checked; });
        syncSelection();
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
})();
