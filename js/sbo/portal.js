(() => {
    'use strict';

    if (!document.querySelector('link[href^="css/sbo.css"]')) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = 'css/sbo.css?v=20260915-4';
        document.head.appendChild(stylesheet);
    }

    const pages = [
        ['attendance','Attendance','pages/sbo/attendance.html','<path d="M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>'],
        ['students','Students','pages/sbo/students.html','<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87"/>'],
        ['scores','Scores','pages/sbo/scores.html','<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v1a4 4 0 0 0 4 4m9-5h3v1a4 4 0 0 1-4 4"/>'],
        ['media','Media','pages/sbo/media.html','<path d="M4 5h16v14H4V5Zm3 10 3-3 2 2 3-4 3 5M8 9h.01"/>'],
    ];
    let csrfToken='';
    const esc=value=>String(value??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const icon=path=>`<svg class="h-5 w-5 shrink-0 fill-none stroke-current stroke-2" viewBox="0 0 24 24">${path}</svg>`;

    const render=(active,user)=>{
        const initials=`${user.first_name?.[0]||''}${user.last_name?.[0]||''}`.toUpperCase()||'SO';
        const name=user.full_name||`${user.first_name||''} ${user.last_name||''}`.trim();
        document.querySelector('[data-sbo-sidebar-host]').outerHTML=`<aside class="fixed inset-y-0 left-0 z-50 hidden w-20 flex-col border-r border-[#397565]/35 bg-[#121017] text-[#F3F0E9] shadow-[8px_0_30px_rgba(18,16,23,.16)] transition-[width] duration-200 lg:flex" data-sbo-sidebar aria-label="Desktop SBO navigation">
          <div class="flex h-[68px] shrink-0 items-center gap-3 border-b border-white/10 px-3" data-sbo-sidebar-header><span class="hidden min-h-11 min-w-0 flex-1 items-center gap-2 overflow-hidden whitespace-nowrap rounded-xl border border-white/10 bg-white/[.06] px-2 text-xs font-black text-[#F3F0E9]" data-sbo-sidebar-label data-sbo-brand><img class="h-7 w-7 shrink-0 rounded-full object-contain" src="assets/images/cite-logo.png" alt=""><span>CITE SBO</span></span><button class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#C6F24E]/25 bg-[#C6F24E]/12 text-[#C6F24E] shadow-sm transition hover:bg-[#C6F24E]/20 focus-visible:ring-2 focus-visible:ring-[#C6F24E]" type="button" data-sbo-sidebar-toggle aria-controls="sbo-desktop-nav" aria-label="Expand navigation" aria-expanded="false">${icon('<path d="M4 6h16M4 12h16M4 18h16"/>').replace('h-5 w-5','h-6 w-6')}</button></div>
          <nav class="grid gap-2 px-3 py-5" id="sbo-desktop-nav" aria-label="Main navigation">${pages.map(([key,label,href,path])=>`<a href="${href}" title="${label}" class="group flex min-h-14 items-center gap-4 overflow-hidden rounded-2xl border px-3 text-sm font-black transition ${key===active?'border-[#C6F24E]/30 bg-[#397565]/55 text-[#C6F24E] shadow-lg shadow-black/20':'border-white/5 bg-white/[.06] text-[#F3F0E9]/75 hover:border-[#C6F24E]/20 hover:bg-white/[.1] hover:text-[#C6F24E]'}" aria-current="${key===active?'page':'false'}">${icon(path).replace('h-5 w-5','h-6 w-6')}<span class="hidden whitespace-nowrap" data-sbo-sidebar-label>${label}</span></a>`).join('')}</nav>
          <div class="mx-3 mb-5 mt-auto hidden rounded-2xl border border-white/10 bg-white/[.06] p-3" data-sbo-sidebar-label><span class="block text-[9px] font-black uppercase tracking-wider text-[#C6F24E]">Signed in as</span><strong class="mt-1 block truncate text-xs text-[#F3F0E9]">${esc(name)}</strong><span class="mt-1 block truncate text-[10px] text-[#F3F0E9]/45">@${esc(user.username)}</span></div></aside>`;
        document.querySelector('[data-sbo-header-host]').outerHTML=`<header class="sticky top-0 z-40 border-b border-[#121017]/8 bg-white/90 backdrop-blur-xl transition-[margin] duration-200 lg:ml-20" data-sbo-sidebar-content><nav class="flex h-[68px] w-full items-center pl-3 pr-2 sm:pl-4 sm:pr-3 lg:px-4" aria-label="SBO header"><a class="flex items-center gap-2.5 text-lg font-black tracking-tight" href="pages/sbo/attendance.html"><img class="h-9 w-9 rounded-full object-contain" src="assets/images/cite-logo.png" alt="CITE logo"><span>CITE<span class="text-[#397565]">.</span></span></a><details class="group relative ml-auto" data-sbo-account-menu><summary class="flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-full p-1 transition hover:bg-[#397565]/7"><span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10">${esc(initials)}<i class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-white bg-[#397565]"></i></span><span class="hidden min-w-0 px-1 text-left sm:grid"><strong class="max-w-48 truncate text-xs font-black leading-tight">${esc(name)}</strong><small class="mt-0.5 max-w-48 truncate text-[9px] font-bold uppercase tracking-wider text-[#397565]">SBO Officer</small></span><svg class="h-4 w-4 shrink-0 fill-none stroke-current stroke-2 transition group-open:rotate-180" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></summary><div class="absolute right-0 top-[calc(100%+.6rem)] w-72 overflow-hidden rounded-2xl border border-[#121017]/10 bg-white p-2 shadow-[0_22px_60px_rgba(18,16,23,.18)]"><div class="border-b border-[#121017]/8 px-3 py-3"><span class="text-[9px] font-black uppercase tracking-wider text-[#397565]">SBO Officer account</span><strong class="mt-1 block truncate text-sm">${esc(name)}</strong><span class="mt-1 block truncate text-xs text-[#121017]/45">Login: ${esc(user.username)}</span><span class="mt-0.5 block truncate text-xs text-[#121017]/45">${esc(user.email||'No email provided')}</span></div><a class="mt-2 flex min-h-10 items-center rounded-xl px-3 text-xs font-bold text-[#121017]/60 hover:bg-[#397565]/8" href="./">View public homepage</a><button class="flex min-h-10 w-full items-center rounded-xl px-3 text-left text-xs font-bold text-[#FF6B2C] hover:bg-[#FF6B2C]/8" type="button" data-sbo-logout>Sign out</button></div></details></nav></header>`;
        document.body.insertAdjacentHTML('beforeend',`<nav class="fixed inset-x-0 bottom-0 z-50 grid h-[72px] grid-cols-4 gap-1 border-t border-white/10 bg-[#121017]/95 px-2 py-1.5 text-white lg:hidden" style="grid-template-columns:repeat(4,minmax(0,1fr));padding-bottom:max(.375rem,env(safe-area-inset-bottom));z-index:50">${pages.map(([key,label,href,path])=>`<a class="flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-black ${key===active?'bg-[#397565] text-[#C6F24E]':'text-white/55'}" href="${href}" aria-current="${key===active?'page':'false'}">${icon(path)}<span class="max-w-full truncate">${label}</span></a>`).join('')}</nav>`);
    };

    const initializeSidebar=()=>{
        const sidebar=document.querySelector('[data-sbo-sidebar]');
        const toggle=document.querySelector('[data-sbo-sidebar-toggle]');
        const content=[document.querySelector('[data-sbo-sidebar-content]'),document.querySelector('main')].filter(Boolean);
        const setExpanded=expanded=>{
            sidebar.classList.toggle('w-64',expanded);sidebar.classList.toggle('w-20',!expanded);
            document.querySelectorAll('[data-sbo-sidebar-label]').forEach(label=>label.classList.toggle('hidden',!expanded));
            document.querySelector('[data-sbo-brand]')?.classList.toggle('flex',expanded);
            const sidebarHeader=document.querySelector('[data-sbo-sidebar-header]');sidebarHeader?.classList.toggle('justify-between',expanded);sidebarHeader?.classList.toggle('justify-center',!expanded);
            content.forEach(element=>{element.classList.toggle('lg:ml-64',expanded);element.classList.toggle('lg:ml-20',!expanded);});
            toggle.setAttribute('aria-expanded',String(expanded));toggle.setAttribute('aria-label',expanded?'Collapse navigation':'Expand navigation');
        };
        setExpanded(false);toggle?.addEventListener('click',()=>setExpanded(toggle.getAttribute('aria-expanded')!=='true'));
    };

    const passwordGate=user=>{
        if(!user.must_change_password)return;
        document.body.insertAdjacentHTML('beforeend',`<dialog class="m-auto w-[min(560px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/70" data-required-password><form class="p-6" data-required-password-form><p class="text-xs font-black uppercase tracking-wider text-[#397565]">Required security step</p><h2 class="mt-1 text-2xl font-black">Create your SBO password</h2><p class="mt-2 text-sm leading-6 text-[#121017]/55">Replace the temporary password before using your assigned modules.</p><div class="mt-6 grid gap-4">${['password','password_confirmation'].map((name,index)=>`<label class="grid gap-2"><span class="text-sm font-bold">${index?'Confirm new password':'New SBO password'}</span><span class="relative"><input class="h-12 w-full rounded-xl border border-[#121017]/12 px-3 pr-12 outline-none focus:border-[#397565]" name="${name}" type="password" minlength="8" required autocomplete="new-password"><button class="absolute inset-y-0 right-0 grid w-12 place-items-center text-[#397565]" type="button" data-password-eye aria-label="Show password">${icon('<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>')}</button></span></label>`).join('')}</div><button class="mt-6 min-h-12 w-full rounded-xl bg-[#397565] px-5 font-black text-white" type="submit">Save password and continue</button></form></dialog>`);
        const dialog=document.querySelector('[data-required-password]');dialog.showModal();
        dialog.querySelectorAll('[data-password-eye]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');input.type=input.type==='password'?'text':'password';button.setAttribute('aria-label',input.type==='password'?'Show password':'Hide password');}));
        dialog.querySelector('form').addEventListener('submit',async event=>{event.preventDefault();const data=Object.fromEntries(new FormData(event.currentTarget));if(data.password!==data.password_confirmation){window.Notifications?.error('The password confirmation does not match.');return;}try{await axios.post('api/auth.php?action=change_password',data,{headers:{'X-CSRF-Token':csrfToken}});dialog.close();window.Notifications?.success('Your SBO password has been changed.');}catch(error){window.Notifications?.error(error.response?.data?.message||'Password change failed.');}});
    };

    const initialize=async active=>{
        const response=await axios.get('api/auth.php?action=session');
        if(!response.data.authenticated||response.data.user?.role!=='SBO Officer'){location.href='./';throw new Error('SBO Officer authentication required.');}
        csrfToken=response.data.csrf_token;document.body.style.overflowX='hidden';render(active,response.data.user);initializeSidebar();passwordGate(response.data.user);
        const notificationRegion=document.querySelector('[data-notification-region]');if(notificationRegion)notificationRegion.style.zIndex='100';
        const accountMenu=document.querySelector('[data-sbo-account-menu]');document.addEventListener('click',event=>{if(accountMenu?.open&&!accountMenu.contains(event.target))accountMenu.removeAttribute('open');});
        document.querySelector('[data-sbo-logout]')?.addEventListener('click',async()=>{try{await axios.post('api/auth.php?action=logout',{}, {headers:{'X-CSRF-Token':csrfToken}});}finally{location.href='./';}});
        return {user:response.data.user,csrfToken};
    };
    window.SboPortal={initialize,escapeHtml:esc,get csrfToken(){return csrfToken;}};
})();
