@php
    $editing = isset($editingUser);
    $formKey = $editing ? 'edit-'.$editingUser->id : 'create';
    $showErrors = $errors->any() && old('_form') === $formKey;
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">

<section>
    <div class="mb-4"><h3 class="text-sm font-extrabold text-[#121017]">Personal Information</h3><p class="mt-1 text-xs text-slate-400">Basic identity and school details.</p></div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">First name</span><input class="{{ $field }} {{ $showErrors && $errors->has('first_name') ? $invalid : '' }}" name="first_name" value="{{ old('first_name', $editing ? $editingUser->first_name : '') }}" autocomplete="given-name" required>@if($showErrors)<x-form-error name="first_name" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Middle name <small class="font-medium text-slate-400">Optional</small></span><input class="{{ $field }} {{ $showErrors && $errors->has('middle_name') ? $invalid : '' }}" name="middle_name" value="{{ old('middle_name', $editing ? $editingUser->middle_name : '') }}" autocomplete="additional-name">@if($showErrors)<x-form-error name="middle_name" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Last name</span><input class="{{ $field }} {{ $showErrors && $errors->has('last_name') ? $invalid : '' }}" name="last_name" value="{{ old('last_name', $editing ? $editingUser->last_name : '') }}" autocomplete="family-name" required>@if($showErrors)<x-form-error name="last_name" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">ID number <small class="font-medium text-slate-400">Optional</small></span><input class="{{ $field }} {{ $showErrors && $errors->has('id_number') ? $invalid : '' }}" name="id_number" value="{{ old('id_number', $editing ? $editingUser->id_number : '') }}">@if($showErrors)<x-form-error name="id_number" />@endif</label>
    </div>
</section>

<section class="mt-6 border-t border-slate-100 pt-6">
    <div class="mb-4"><h3 class="text-sm font-extrabold text-[#121017]">Account and Access</h3><p class="mt-1 text-xs text-slate-400">Credentials, role, and system access.</p></div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Username</span><input class="{{ $field }} {{ $showErrors && $errors->has('username') ? $invalid : '' }}" name="username" value="{{ old('username', $editing ? $editingUser->username : '') }}" autocomplete="username" required>@if($showErrors)<x-form-error name="username" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Email address</span><input class="{{ $field }} {{ $showErrors && $errors->has('email') ? $invalid : '' }}" name="email" type="email" value="{{ old('email', $editing ? $editingUser->email : '') }}" autocomplete="email" required>@if($showErrors)<x-form-error name="email" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Role</span><select class="{{ $field }} {{ $showErrors && $errors->has('role_id') ? $invalid : '' }}" name="role_id" required><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) old('role_id', $editing ? $editingUser->role_id : '') === (string) $role->id)>{{ $role->name }}</option>@endforeach</select>@if($showErrors)<x-form-error name="role_id" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Year level <small class="font-medium text-slate-400">Students only</small></span><select class="{{ $field }} {{ $showErrors && $errors->has('year_level') ? $invalid : '' }}" name="year_level"><option value="">Not applicable</option>@foreach($yearLevels as $level)<option value="{{ $level->id }}" @selected((string) old('year_level', $editing ? $editingUser->year_level : '') === (string) $level->id)>{{ $level->label }}</option>@endforeach</select>@if($showErrors)<x-form-error name="year_level" />@endif</label>
        <label class="grid gap-2"><span class="flex justify-between text-sm font-bold text-slate-700">Password @if($editing)<small class="font-medium text-slate-400">Leave blank to keep</small>@endif</span><input class="{{ $field }} {{ $showErrors && $errors->has('password') ? $invalid : '' }}" name="password" type="password" @required(!$editing) autocomplete="new-password">@if($showErrors)<x-form-error name="password" />@endif</label>
        <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Confirm password</span><input class="{{ $field }}" name="password_confirmation" type="password" @required(!$editing) autocomplete="new-password"></label>
    </div>
</section>
