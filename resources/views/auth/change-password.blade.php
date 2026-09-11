<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password | CITE Events</title>
    <x-favicon />
    <script>try{const t=localStorage.getItem('cite-theme')||'light';document.documentElement.classList.toggle('dark',t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))}catch(e){}</script>
    @vite('resources/css/app.css')
</head>
<body class="grid min-h-screen place-items-center bg-white p-5 font-sans text-[#121017] dark:bg-[#0d1714] dark:text-[#eef5f2]">
    <main class="w-full max-w-lg rounded-2xl border border-[#121017]/10 bg-white p-6 shadow-[0_24px_70px_rgba(18,16,23,.12)] dark:border-white/10 dark:bg-[#17231f] sm:p-8">
        <div class="flex items-center gap-3">
            <img class="h-12 w-12 rounded-full" src="{{ asset('images/cite-logo.png') }}" alt="CITE">
            <div><p class="text-xs font-black uppercase tracking-wider text-[#397565]">Account security</p><h1 class="text-2xl font-black">Change password</h1></div>
        </div>
        <p class="mt-5 text-sm leading-6 text-[#121017]/55 dark:text-[#dce8e3]/60">Create a private password for your account.</p>
        <form class="mt-6 grid gap-4" method="POST" action="{{ route('password.update') }}">
            @csrf @method('PUT')
            @foreach([['current_password','Current password'],['password','New password'],['password_confirmation','Confirm new password']] as [$name,$label])
                <label class="grid gap-2"><span class="text-sm font-bold">{{ $label }}</span><input class="h-12 rounded-xl border border-[#121017]/12 bg-white px-3 text-sm outline-none dark:border-white/10 dark:bg-[#101b17] dark:text-[#eef5f2] focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="{{ $name }}" type="password" required autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}"><x-form-error :name="$name" /></label>
            @endforeach
            <button class="mt-2 min-h-12 rounded-xl bg-[#397565] px-5 font-black text-white" type="submit">Save password and continue</button>
        </form>
    </main>
</body>
</html>
