<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IT Event Management</title>
    @vite('resources/css/app.css')
</head>
<body class="grid min-h-screen place-items-center bg-[#fff5e0] p-6 font-sans text-[#141e46] antialiased">
    <main class="w-full max-w-xl rounded-3xl bg-white p-10 text-center shadow-2xl shadow-[#141e46]/10">
        <span class="mx-auto mb-6 flex h-12 w-12 items-end justify-center gap-1 rounded-2xl bg-[#fff5e0] p-2.5" aria-hidden="true"><i class="h-3 w-1.5 rounded-t bg-[#8decb4]"></i><i class="h-5 w-1.5 rounded-t bg-[#41b06e]"></i><i class="h-7 w-1.5 rounded-t bg-[#141e46]"></i></span>
        <h1 class="text-3xl font-black tracking-tight">IT Event Management</h1><p class="mt-3 text-sm leading-6 text-slate-500">Plan, manage, and attend campus events in one organized place.</p>
        <a class="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-6 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700" href="{{ route('login') }}">Continue to sign in</a>
    </main>
</body>
</html>
