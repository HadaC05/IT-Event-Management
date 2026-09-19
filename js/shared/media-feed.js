(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const statusTone = status => ({pending:'bg-[#C6F24E]/35 text-[#397565]',approved:'bg-[#397565]/10 text-[#397565]',rejected:'bg-[#FF6B2C]/12 text-[#c84510]',hidden:'bg-[#121017]/10 text-[#121017]/60'}[status]||'bg-[#F3F0E9]');
  const formatDate = value => value ? new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value.replace(' ','T'))) : '';
  const state = {data:null,eventId:null,form:null,root:null,carouselTimer:null};

  const notify = (message,type='success') => {
    if(window.Notifications?.[type]){window.Notifications[type](message);return;}
    let toast=document.querySelector('[data-shared-media-toast]');
    if(!toast){toast=document.createElement('div');toast.dataset.sharedMediaToast='';toast.className='fixed bottom-24 right-4 z-[100] max-w-sm rounded-xl px-4 py-3 text-sm font-bold text-white shadow-2xl lg:bottom-5';document.body.append(toast);}
    toast.textContent=message;toast.classList.toggle('bg-[#c84510]',type==='error');toast.classList.toggle('bg-[#397565]',type!=='error');toast.classList.remove('hidden');clearTimeout(notify.timer);notify.timer=setTimeout(()=>toast.classList.add('hidden'),4000);
  };

  async function execute(payload, options={}) {
    if(options.confirm && !confirm(options.confirm)) return;
    try{const response=await CiteMediaApi.send(payload);notify(response.message);await load();}catch(error){notify(error.response?.data?.message||'The media request failed.','error');}
  }

  function featuredMarkup(events) {
    if(!events.length)return '';
    return `<section class="relative overflow-hidden rounded-xl border border-[#397565]/25 bg-[#121017] text-white shadow-[0_18px_50px_rgba(18,16,23,.14)]" data-event-carousel><div class="cite-carousel-stage relative min-h-64" data-carousel-slides>${events.map((event,index)=>`<article class="absolute inset-0 ${index?'hidden':''}" data-carousel-slide>${event.poster_path?`<img class="absolute inset-0 h-full w-full object-cover" src="${esc(event.poster_path)}" alt="${esc(event.title)} carousel image">`:''}<div class="cite-carousel-overlay absolute inset-0"></div><div class="cite-carousel-content relative flex min-h-64 max-w-2xl flex-col justify-end p-6 sm:p-9"><span class="self-start rounded-full bg-[#C6F24E] px-3 py-1 text-[9px] font-black uppercase text-[#121017]">${event.is_featured?'Featured event':'Current event'}</span><h2 class="mt-4 text-2xl font-black sm:text-4xl">${esc(event.title)}</h2>${event.description?`<p class="mt-2 line-clamp-2 text-sm leading-6 text-white/70">${esc(event.description)}</p>`:''}<p class="mt-3 text-xs font-bold text-white/65">${esc(formatDate(event.start_at))}${event.location?` · ${esc(event.location)}`:''}</p></div></article>`).join('')}</div>${events.length>1?`<button class="absolute left-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-lg font-black text-[#121017] shadow-lg" type="button" data-carousel-prev aria-label="Previous event">‹</button><button class="absolute right-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-lg font-black text-[#121017] shadow-lg" type="button" data-carousel-next aria-label="Next event">›</button><div class="absolute bottom-4 right-5 z-10 flex gap-1.5">${events.map((_,index)=>`<button class="h-2 rounded-full ${index?'w-2 bg-white/45':'w-6 bg-[#C6F24E]'}" type="button" data-carousel-dot="${index}" aria-label="Show event ${index+1}"></button>`).join('')}</div>`:''}</section>`;
  }

  function eventProgramMarkup(events) {
    return `<section class="rounded-xl border border-[#397565]/15 bg-white p-4"><div><p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">What's happening</p><h2 class="mt-1 text-lg font-black">Event activities</h2></div><div class="mt-4 grid gap-3">${events.length?events.map(event=>`<article class="rounded-xl border border-[#121017]/8 bg-[#F7F4ED]/55 p-3"><h3 class="text-sm font-black">${esc(event.title)}</h3><p class="mt-1 text-[9px] text-[#121017]/45">${esc(formatDate(event.start_at))}${event.location?` · ${esc(event.location)}`:''}</p><div class="mt-3 grid gap-2">${event.activities.length?event.activities.map(activity=>`<div class="border-l-2 border-[#C6F24E] pl-3"><strong class="block text-xs">${esc(activity.name)}</strong>${activity.description?`<p class="mt-0.5 line-clamp-2 text-[10px] leading-4 text-[#121017]/50">${esc(activity.description)}</p>`:''}</div>`).join(''):'<p class="text-[10px] text-[#121017]/40">Activities will be announced soon.</p>'}</div></article>`).join(''):'<p class="py-6 text-center text-xs text-[#121017]/45">No active or upcoming events.</p>'}</div></section>`;
  }

  function bindCarousel(root) {
    clearInterval(state.carouselTimer);state.carouselTimer=null;
    const carousel=root.querySelector('[data-event-carousel]');if(!carousel)return;
    const slides=[...carousel.querySelectorAll('[data-carousel-slide]')],dots=[...carousel.querySelectorAll('[data-carousel-dot]')];if(slides.length<2)return;
    let current=0;
    const show=index=>{current=(index+slides.length)%slides.length;slides.forEach((slide,item)=>slide.classList.toggle('hidden',item!==current));dots.forEach((dot,item)=>{dot.classList.toggle('w-6',item===current);dot.classList.toggle('bg-[#C6F24E]',item===current);dot.classList.toggle('w-2',item!==current);dot.classList.toggle('bg-white/45',item!==current);});};
    const start=()=>{clearInterval(state.carouselTimer);state.carouselTimer=setInterval(()=>show(current+1),6000);};
    carousel.querySelector('[data-carousel-prev]').onclick=()=>{show(current-1);start();};carousel.querySelector('[data-carousel-next]').onclick=()=>{show(current+1);start();};dots.forEach(dot=>dot.onclick=()=>{show(Number(dot.dataset.carouselDot));start();});carousel.onmouseenter=()=>clearInterval(state.carouselTimer);carousel.onmouseleave=start;start();
  }

  function ownPostsMarkup(posts, viewer) {
    if(!posts.length)return '<p class="py-7 text-center text-xs text-[#121017]/45">You have not created any posts yet.</p>';
    return posts.map(post=>{
      const canEdit=post.status!=='hidden', canDelete=post.status!=='hidden';
      const menu=canEdit||canDelete?`<details class="cite-post-menu relative shrink-0"><summary class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg text-xs font-black tracking-wider hover:bg-[#397565]/8" aria-label="Post options">•••</summary><div class="absolute right-0 top-9 z-30 min-w-36 rounded-xl border border-[#121017]/10 bg-white p-1.5 shadow-xl">${canEdit?`<button class="flex min-h-9 w-full items-center rounded-lg px-3 text-left text-[10px] font-black text-[#397565]" type="button" data-own-edit="${post.id}">Edit post</button>`:''}${canDelete?`<button class="flex min-h-9 w-full items-center rounded-lg px-3 text-left text-[10px] font-black text-[#FF6B2C]" type="button" data-own-delete="${post.id}">Delete post</button>`:''}</div></details>`:'';
      return `<article class="relative rounded-xl border border-[#121017]/9 bg-white p-3"><div class="flex items-start justify-between gap-2"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${statusTone(post.status)}">${esc(post.status)}</span><span class="text-[9px] text-[#121017]/35">${esc(formatDate(post.updated_at||post.created_at))}</span></div>${menu}</div><p class="mt-2 line-clamp-3 text-xs leading-5">${esc(post.content)}</p>${post.event_title?`<p class="mt-2 text-[10px] font-bold text-[#397565]">${esc(post.event_title)}</p>`:''}${post.rejection_reason?`<div class="mt-2 rounded-lg bg-[#FF6B2C]/8 p-2 text-[10px] leading-4 text-[#a33b0e]"><strong>Reason:</strong> ${esc(post.rejection_reason)}</div>`:''}</article>`;
    }).join('');
  }

  function moderationMarkup(data) {
    if(!data.permissions.moderate)return '';
    const pending=data.moderation.filter(post=>post.status==='pending');
    return `<section class="rounded-xl border border-[#397565]/15 bg-white p-4"><div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">Student post queue</p><h2 class="mt-1 font-black">Pending review</h2></div><span class="rounded-full bg-[#C6F24E] px-3 py-1 text-xs font-black">${data.moderation_counts.pending||0}</span></div><div class="mt-4 grid gap-3" data-moderation-list>${pending.length?pending.map(post=>`<article class="rounded-xl border border-[#121017]/9 bg-[#F7F4ED]/50 p-4"><div class="flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-full bg-[#C6F24E] text-[10px] font-black">${esc(post.author_initials)}</span><div><strong class="text-xs">${esc(post.author_name)}</strong><p class="text-[9px] text-[#121017]/40">${esc(post.event_title||'General')} · ${esc(formatDate(post.created_at))}</p></div></div><p class="mt-3 whitespace-pre-line text-sm leading-6">${esc(post.content)}</p>${post.image_path?`<img class="mt-3 max-h-72 w-full rounded-xl bg-[#121017] object-contain" src="${esc(post.image_path)}" alt="Pending post image">`:''}<div class="mt-4 flex gap-2"><button class="min-h-10 flex-1 rounded-xl bg-[#397565] px-3 text-xs font-black text-white" type="button" data-approve="${post.id}">Approve</button><button class="min-h-10 flex-1 rounded-xl border border-[#FF6B2C]/30 px-3 text-xs font-black text-[#c84510]" type="button" data-reject="${post.id}">Reject</button></div></article>`).join(''):'<p class="py-8 text-center text-xs text-[#121017]/45">All caught up. No student posts are waiting.</p>'}</div></section>`;
  }

  function carouselManagerMarkup(data) {
    if(!data.permissions.manage_carousel)return '';
    return `<details class="rounded-xl border border-[#397565]/15 bg-white p-4"><summary class="cursor-pointer text-sm font-black text-[#397565]">Manage event carousel</summary><form class="mt-4 grid gap-3" data-carousel-form><label class="grid gap-1 text-xs font-bold">Event<select class="h-11 rounded-xl border px-3" name="event_id" required>${data.carousel_events.map(event=>`<option value="${event.id}" data-featured="${event.is_featured?'1':'0'}">${esc(event.title)}</option>`).join('')}</select></label><label class="grid gap-1 text-xs font-bold">Carousel image<input class="rounded-xl border p-2 text-xs" type="file" name="carousel_image" accept="image/jpeg,image/png,image/webp"></label><label class="flex items-center gap-2 text-xs font-bold"><input type="checkbox" name="is_featured" value="1"> Show this active event in the carousel</label><button class="min-h-11 rounded-xl bg-[#397565] px-4 text-xs font-black text-white" type="submit">Save carousel</button>${data.carousel_events.length?'':'<p class="text-xs text-[#121017]/45">No eligible assigned events.</p>'}</form></details>`;
  }

  function render() {
    const data=state.data, root=state.root;
    root.innerHTML=`<div class="cite-media-shell mx-auto w-full space-y-5"><header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">CITE community</p><h1 class="mt-1 text-3xl font-black tracking-tight">Media feed</h1><p class="mt-2 text-sm text-[#121017]/50">Stories and updates from active and completed CITE events.</p></div><span class="self-start rounded-full bg-[#C6F24E]/35 px-3 py-1.5 text-[10px] font-black text-[#397565]">Approved posts only</span></header>${featuredMarkup(data.featured_events)}<div class="cite-media-layout grid items-start gap-6"><div class="min-w-0 space-y-5"><div data-shared-post-form-host></div>${data.own_posts.length?`<details class="rounded-xl border border-[#397565]/15 bg-white p-4" data-post-status><summary class="cursor-pointer text-sm font-black text-[#397565]">Pending and rejected posts (${data.own_posts.length})</summary><div class="mt-3 grid gap-2 sm:grid-cols-2" data-own-posts>${ownPostsMarkup(data.own_posts,data.viewer)}</div></details>`:''}<section><div class="mb-4 flex gap-2 overflow-x-auto pb-1" data-event-filters><button class="shrink-0 rounded-xl px-4 py-2 text-xs font-black ${state.eventId?'bg-white text-[#397565]':'bg-[#397565] text-white'}" type="button" data-event-filter="">Mixed feed</button>${data.active_events.map(event=>`<button class="shrink-0 rounded-xl px-4 py-2 text-xs font-black ${Number(state.eventId)===event.id?'bg-[#397565] text-white':'bg-white text-[#397565]'}" type="button" data-event-filter="${event.id}">${esc(event.title)}</button>`).join('')}</div><div class="w-full space-y-5" data-public-feed></div></section></div><aside class="space-y-5 lg:sticky lg:top-24">${moderationMarkup(data)}${carouselManagerMarkup(data)}${eventProgramMarkup(data.event_program||[])}</aside></div></div>`;
    bindCarousel(root);
    state.form=CiteMediaPostForm.mount(root.querySelector('[data-shared-post-form-host]'),{events:data.post_events,viewer:data.viewer,onSaved:load,notify});
    const feed=root.querySelector('[data-public-feed]');
    if(data.posts.length)data.posts.forEach(post=>feed.append(CiteMediaPostCard.create(post,{viewer:data.viewer,permissions:data.permissions,action:execute,edit:own=>state.form.edit(own)})));
    else feed.innerHTML='<div class="rounded-xl border border-dashed border-[#397565]/25 bg-[#397565]/5 px-6 py-16 text-center"><span class="text-3xl">🌿</span><h3 class="mt-3 font-black">The feed is ready for its first story</h3><p class="mt-1 text-sm text-[#121017]/45">No approved posts match this event.</p></div>';
    root.querySelectorAll('[data-event-filter]').forEach(button=>button.onclick=()=>{state.eventId=Number(button.dataset.eventFilter)||null;load();});
    root.querySelectorAll('[data-own-posts] [data-own-edit]').forEach(button=>button.onclick=()=>{button.closest('details')?.removeAttribute('open');const post=data.own_posts.find(item=>item.id===Number(button.dataset.ownEdit));if(post)state.form.edit(post);});
    root.querySelectorAll('[data-own-posts] [data-own-delete]').forEach(button=>button.onclick=()=>execute({action:'delete',id:button.dataset.ownDelete},{confirm:'Delete this post?'}));
    root.querySelectorAll('[data-approve]').forEach(button=>button.onclick=()=>execute({action:'approve',post_id:button.dataset.approve}));
    root.querySelectorAll('[data-reject]').forEach(button=>button.onclick=()=>{const reason=prompt('Enter the rejection reason shown to the student:');if(reason)execute({action:'reject',post_id:button.dataset.reject,reason});});
    const carouselForm=root.querySelector('[data-carousel-form]');
    if(carouselForm){const select=carouselForm.elements.event_id,check=carouselForm.elements.is_featured;const sync=()=>{check.checked=select.selectedOptions[0]?.dataset.featured==='1';};select.onchange=sync;sync();carouselForm.onsubmit=async event=>{event.preventDefault();if(!select.value)return;const data=new FormData(carouselForm);data.set('action','carousel_update');if(!check.checked)data.set('is_featured','0');await execute(data);};}
  }

  async function load() {
    try{state.data=await CiteMediaApi.load(state.eventId);render();}catch(error){state.root.innerHTML=`<div class="rounded-xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/7 p-6 text-center text-sm font-bold text-[#c84510]">${esc(error.response?.data?.message||'The media feed could not be loaded.')}</div>`;}
  }

  async function initialize(root, session) {
    if(!root)return;
    state.root=root;CiteMediaApi.configure(session.csrfToken);await load();
  }
  window.CiteMediaFeed={initialize};
})();
