@php
    $editing = isset($editingUser);
    $formKey = $editing ? 'edit-'.$editingUser->id : 'create';
    $showErrors = $errors->any() && old('_form') === $formKey;
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
    $filteredRoleId = $roles->firstWhere('name', request('role'))?->id;
    $selectedRoleId = (string) old('role_id', $editing ? $editingUser->role_id : $filteredRoleId);
    $formRoles = $editing ? $roles : $creatableRoles;
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">

<section>
    <div class="mb-4"><h3 class="text-sm font-extrabold text-[#121017]">Personal Information</h3><p class="mt-1 text-xs text-slate-400">Basic identity and school details.</p></div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">First name</span><input class="{{ $field }} {{ $showErrors && $errors->has('first_name') ? $invalid : '' }}" name="first_name" value="{{ old('first_name', $editing ? $editingUser->first_name : '') }}" autocomplete="given-name" required>@if($showErrors)<x-form-error name="first_name" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Middle name <small class="font-medium text-slate-400">Optional</small></span><input class="{{ $field }} {{ $showErrors && $errors->has('middle_name') ? $invalid : '' }}" name="middle_name" value="{{ old('middle_name', $editing ? $editingUser->middle_name : '') }}" autocomplete="additional-name">@if($showErrors)<x-form-error name="middle_name" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Last name</span><input class="{{ $field }} {{ $showErrors && $errors->has('last_name') ? $invalid : '' }}" name="last_name" value="{{ old('last_name', $editing ? $editingUser->last_name : '') }}" autocomplete="family-name" required>@if($showErrors)<x-form-error name="last_name" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">ID number <small class="font-medium text-slate-400">Optional</small></span><input class="{{ $field }} {{ $showErrors && $errors->has('id_number') ? $invalid : '' }}" name="id_number" value="{{ old('id_number', $editing ? $editingUser->id_number : '') }}" placeholder="02-xxxx-xxxxxx" data-id-number>@if($showErrors)<x-form-error name="id_number" />@endif</label>
    </div>
</section>

<section class="mt-6 border-t border-slate-100 pt-6">
    <div class="mb-4"><h3 class="text-sm font-extrabold text-[#121017]">Account and Access</h3><p class="mt-1 text-xs text-slate-400">Credentials, role, and system access.</p></div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Username</span><input class="{{ $field }} {{ $showErrors && $errors->has('username') ? $invalid : '' }}" name="username" value="{{ old('username', $editing ? $editingUser->username : '') }}" autocomplete="username" required>@if($showErrors)<x-form-error name="username" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Email address</span><input class="{{ $field }} {{ $showErrors && $errors->has('email') ? $invalid : '' }}" name="email" type="email" value="{{ old('email', $editing ? $editingUser->email : '') }}" autocomplete="email" required>@if($showErrors)<x-form-error name="email" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Role</span><select class="{{ $field }} {{ $showErrors && $errors->has('role_id') ? $invalid : '' }}" name="role_id" data-role-select required><option value="">Select role</option>@foreach($formRoles as $role)<option value="{{ $role->id }}" data-role-name="{{ $role->name }}" @selected($selectedRoleId === (string) $role->id)>{{ $role->name }}</option>@endforeach</select>@if($showErrors)<x-form-error name="role_id" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Year level <small class="font-medium text-slate-400">Students only</small></span><select class="{{ $field }} {{ $showErrors && $errors->has('year_level') ? $invalid : '' }}" name="year_level"><option value="">Not applicable</option>@foreach($yearLevels as $level)<option value="{{ $level->id }}" @selected((string) old('year_level', $editing ? $editingUser->year_level : '') === (string) $level->id)>{{ $level->label }}</option>@endforeach</select>@if($showErrors)<x-form-error name="year_level" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Password @if($editing)<small class="font-medium text-slate-400">Leave blank to keep</small>@endif</span><span class="relative"><input class="{{ $field }} pr-12 {{ $showErrors && $errors->has('password') ? $invalid : '' }}" name="password" type="password" @required(!$editing) autocomplete="new-password" data-password-strength-source><button class="absolute right-1.5 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-[#397565] hover:bg-[#397565]/9" type="button" data-user-password-toggle aria-label="Show password" aria-pressed="false"><svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true" data-eye-visible><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg><svg class="hidden h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true" data-eye-hidden><path d="m3 3 18 18M10.6 6.15A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.7 15.7 0 0 1-2.1 2.75M6.1 6.1C3.8 7.75 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.05-.48M10.05 10.05A2.75 2.75 0 0 0 13.95 13.95"/></svg></button></span>@if($showErrors)<x-form-error name="password" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Confirm password</span><span class="relative"><input class="{{ $field }} pr-12" name="password_confirmation" type="password" @required(!$editing) autocomplete="new-password" data-password-confirmation><button class="absolute right-1.5 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-[#397565] hover:bg-[#397565]/9" type="button" data-user-password-toggle aria-label="Show confirmed password" aria-pressed="false"><svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true" data-eye-visible><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg><svg class="hidden h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true" data-eye-hidden><path d="m3 3 18 18M10.6 6.15A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.7 15.7 0 0 1-2.1 2.75M6.1 6.1C3.8 7.75 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.05-.48M10.05 10.05A2.75 2.75 0 0 0 13.95 13.95"/></svg></button></span></label>
        <div class="rounded-xl border border-[#121017]/8 bg-[#F3F0E9]/45 p-3.5 sm:col-span-2" data-password-strength>
            <div class="grid grid-cols-5 gap-1.5" aria-hidden="true">@for($segment = 0; $segment < 5; $segment++)<span class="h-1.5 rounded-full bg-[#121017]/10 transition-all duration-300" data-strength-bar></span>@endfor</div>
            <div class="mt-2 flex items-center justify-between gap-3"><span class="text-xs font-semibold text-[#121017]/50">Password strength</span><strong class="text-xs font-extrabold text-[#121017]/45" data-strength-label>Not entered</strong></div>
            <div class="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[10px] font-semibold text-[#121017]/45 sm:grid-cols-5">
                @foreach([['length', '8+ characters'], ['uppercase', 'Uppercase'], ['lowercase', 'Lowercase'], ['number', 'Number'], ['special', 'Special character']] as [$rule, $label])
                    <span class="flex items-center gap-1.5 transition" data-password-rule="{{ $rule }}"><i class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#121017]/15 transition"></i>{{ $label }}</span>
                @endforeach
            </div>
            <p class="mt-2 hidden text-[10px] font-bold" data-password-match aria-live="polite"></p>
        </div>
    </div>
</section>

@once
    @push('scripts')
        <script>
            const refreshRoleFields = () => document.querySelectorAll('form').forEach(form => {
                const role = form.querySelector('[data-role-select]');
                const idNumber = form.querySelector('[data-id-number]');
                if (!role) return;
                const roleName = role.selectedOptions[0]?.dataset.roleName;
                if (idNumber) {
                    if (roleName === 'Student') {
                        idNumber.maxLength = 14;
                        idNumber.inputMode = 'numeric';
                        idNumber.pattern = '02-[0-9]{4}-[0-9]{6}';
                        idNumber.title = 'Use 02-xxxx-xxxxxx (12 digits).';
                    } else {
                        idNumber.removeAttribute('maxlength');
                        idNumber.removeAttribute('inputmode');
                        idNumber.removeAttribute('pattern');
                        idNumber.removeAttribute('title');
                    }
                }
            });
            document.addEventListener('change', event => { if (event.target.matches('[data-role-select]')) refreshRoleFields(); });
            document.addEventListener('input', event => {
                if (!event.target.matches('[data-id-number]')) return;
                const form = event.target.closest('form');
                if (form?.querySelector('[data-role-select]')?.selectedOptions[0]?.dataset.roleName !== 'Student') return;
                const digits = event.target.value.replace(/\D/g, '').slice(0, 12);
                event.target.value = digits.length <= 2
                    ? digits
                    : digits.length <= 6
                        ? `${digits.slice(0, 2)}-${digits.slice(2)}`
                        : `${digits.slice(0, 2)}-${digits.slice(2, 6)}-${digits.slice(6)}`;
            });
            document.addEventListener('click', event => {
                const toggle = event.target.closest('[data-user-password-toggle]');
                if (!toggle) return;
                const input = toggle.parentElement.querySelector('input');
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                toggle.setAttribute('aria-pressed', String(!showing));
                toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                toggle.querySelector('[data-eye-visible]').classList.toggle('hidden', !showing);
                toggle.querySelector('[data-eye-hidden]').classList.toggle('hidden', showing);
            });

            const refreshPasswordStrength = form => {
                const password = form.querySelector('[data-password-strength-source]');
                const confirmation = form.querySelector('[data-password-confirmation]');
                const meter = form.querySelector('[data-password-strength]');
                if (!password || !meter) return;

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
                    bar.style.transform = value && index < score ? 'scaleY(1.15)' : 'scaleY(1)';
                });
                const strengthLabel = meter.querySelector('[data-strength-label]');
                strengthLabel.textContent = value ? labels[score] : labels[0];
                strengthLabel.style.color = value ? colors[score] : '#12101773';

                Object.entries(rules).forEach(([rule, passed]) => {
                    const criterion = meter.querySelector(`[data-password-rule="${rule}"]`);
                    criterion.classList.toggle('text-[#397565]', passed);
                    criterion.classList.toggle('text-[#121017]/45', !passed);
                    criterion.querySelector('i').style.backgroundColor = passed ? '#C6F24E' : '#12101726';
                });

                const match = meter.querySelector('[data-password-match]');
                const hasConfirmation = confirmation?.value.length > 0;
                match.classList.toggle('hidden', !hasConfirmation);
                if (hasConfirmation) {
                    const matches = value === confirmation.value;
                    match.textContent = matches ? '✓ Passwords match' : 'Passwords do not match yet';
                    match.style.color = matches ? '#397565' : '#FF6B2C';
                }
            };
            document.addEventListener('input', event => {
                if (!event.target.matches('[data-password-strength-source], [data-password-confirmation]')) return;
                refreshPasswordStrength(event.target.closest('form'));
            });
            document.querySelectorAll('form').forEach(refreshPasswordStrength);
            refreshRoleFields();
        </script>
    @endpush
@endonce
