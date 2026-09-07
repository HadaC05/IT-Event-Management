<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in | CITE Events</title>
    @vite('resources/css/app.css')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
</head>
<body class="app-shell min-h-screen bg-white font-sans text-[#121017] antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(380px,.9fr)_minmax(520px,1.1fr)]">
        <section class="relative hidden min-h-screen flex-col justify-between overflow-hidden bg-[#121017] px-10 py-12 text-[#F3F0E9] lg:flex xl:px-20" aria-label="CITE Events introduction">
            <div class="absolute -right-36 bottom-24 h-96 w-96 rounded-full border-[80px] border-[#C6F24E]/10"></div><div class="absolute -left-20 top-1/4 h-44 w-44 rounded-full bg-[#397565]/10"></div>
            <div class="relative z-10">
                <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="{{ route('home') }}"><img class="h-11 w-11 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo"><span>CITE<span class="text-[#C6F24E]">.</span></span></a>
                <div class="mt-[clamp(7rem,18vh,12rem)] max-w-xl"><p class="mb-4 text-xs font-extrabold uppercase tracking-[.14em] text-[#C6F24E]">Plan. Connect. Celebrate.</p><h1 class="text-5xl font-black leading-[1.03] tracking-[-.055em] xl:text-6xl">Bringing every campus event together.</h1><p class="mt-6 max-w-lg text-base leading-7 text-[#F3F0E9]/65">One organized space for the people who make our IT community thrive.</p></div>
            </div>
            <div class="relative z-10 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wide text-[#F3F0E9]/55"><span>SBO Adviser</span><i class="h-px w-4 bg-[#397565]"></i><span>SBO</span><i class="h-px w-4 bg-[#397565]"></i><span>Faculty</span><i class="h-px w-4 bg-[#397565]"></i><span>Students</span></div>
        </section>

        <section class="grid min-h-screen place-items-center bg-white px-5 py-9 sm:px-8">
            <div class="w-full max-w-md">
                <a class="mb-12 inline-flex items-center gap-3 text-xl font-extrabold tracking-tight lg:hidden" href="{{ route('home') }}"><img class="h-11 w-11 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo"><span>CITE<span class="text-[#397565]">.</span></span></a>
                <header class="mb-8"><p class="mb-3 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Welcome back</p><h2 class="text-3xl font-black tracking-tight sm:text-4xl">Sign in to your account</h2><p class="mt-3 text-sm leading-6 text-slate-500">Enter your credentials to continue to the event portal.</p></header>

                <form class="grid gap-5" method="POST" action="{{ route('login') }}">@csrf
                    <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Username or email</span><span class="relative"><svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg><input class="h-13 w-full rounded-xl border bg-white pl-12 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:ring-4 {{ $errors->has('login') ? 'border-red-400 bg-red-50/40 focus:border-red-400 focus:ring-red-500/10' : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10' }}" id="login" name="login" value="{{ old('login') }}" placeholder="Enter your username or email" autocomplete="username" required autofocus></span><x-form-error name="login" /></label>
                    <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Password</span><span class="relative"><svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5z"/></svg><input class="h-13 w-full rounded-xl border bg-white pl-12 pr-16 text-sm outline-none transition placeholder:text-slate-400 focus:ring-4 {{ $errors->has('password') ? 'border-red-400 bg-red-50/40 focus:border-red-400 focus:ring-red-500/10' : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10' }}" id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-xs font-extrabold text-emerald-700" type="button" data-password-toggle>Show</button></span><x-form-error name="password" /></label>
                    <label class="inline-flex w-fit cursor-pointer items-center gap-2 text-sm text-slate-600"><input class="h-4 w-4 accent-emerald-600" name="remember" type="checkbox" value="1"><span>Keep me signed in</span></label>
                    <button class="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-extrabold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700" type="submit" data-loading-text="Signing in…">Sign in <span class="text-xl" aria-hidden="true">→</span></button>
                </form>
                <p class="mt-7 text-center text-xs text-slate-400">Having trouble signing in? Contact your SBO Adviser.</p>
            </div>
        </section>
    </main>
    <script>const toggle=document.querySelector('[data-password-toggle]'),password=document.querySelector('#password');toggle.addEventListener('click',()=>{const showing=password.type==='text';password.type=showing?'password':'text';toggle.textContent=showing?'Show':'Hide';toggle.setAttribute('aria-pressed',String(!showing));});</script>
</body>
</html>
