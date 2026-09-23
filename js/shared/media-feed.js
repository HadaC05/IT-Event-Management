(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const statusTone = status => ({pending:'bg-[#C6F24E]/35 text-[#397565]',approved:'bg-[#397565]/10 text-[#397565]',rejected:'bg-[#FF6B2C]/12 text-[#c84510]',hidden:'bg-[#121017]/10 text-[#121017]/60'}[status]||'bg-[#F3F0E9]');
  const formatDate = value => value ? new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value.replace(' ','T'))) : '';
  const state = {data:null,eventId:null,form:null,root:null,carouselTimer:null,pending:new Set(),queuePage:1,queueRequest:0,feedRequest:0,nextCursor:null,reactionToast:null};

  const notify = (type,message) => {
    if(window.Notifications?.[type]) return window.Notifications[type](message);
    let toast=document.querySelector('[data-shared-media-toast]');
    if(!toast){toast=document.createElement('div');toast.dataset.sharedMediaToast='';toast.className='fixed bottom-24 right-4 z-[100] max-w-sm rounded-xl px-4 py-3 text-sm font-bold text-white shadow-2xl lg:bottom-5';document.body.append(toast);}
    toast.textContent=message;toast.classList.toggle('bg-[#c84510]',type==='error');toast.classList.toggle('bg-[#397565]',type!=='error');toast.classList.remove('hidden');clearTimeout(notify.timer);notify.timer=setTimeout(()=>toast.classList.add('hidden'),4000);
    return toast;
  };

  async function execute(payload, options={}) {
    if(options.confirm && !(await window.Notifications.confirm({title:'Delete post?',message:options.confirm,action:'Delete'}))) return;
    const value=payload instanceof FormData?Object.fromEntries(payload.entries()):payload;
    const key=[value.action,value.post_id||value.id||'',value.comment_id||'',value.event_id||'',value.type||'',value.active??value.pin??''].join(':');
    if(state.pending.has(key))return;
    state.pending.add(key);
    let saved=false;
    try{
      const response=await CiteMediaApi.send(payload);
      saved=true;
      if(['reaction_toggle','comment_reaction_toggle'].includes(value.action)){
        state.reactionToast?.remove();
        state.reactionToast=notify('success',response.message||'Reaction updated.');
      }else notify('success',response.message||'Media action completed.');
      if(value.action==='approve'||value.action==='reject'){
        await loadModeration(state.queuePage);
        if(value.action==='approve'){
          try{await refreshPublicFeed();}catch{notify('error','Post approved, but the public feed could not refresh. Reload the page to see it.');}
        }
      }else if(['reaction_toggle','comment_reaction_toggle','comment_create','comment_update','comment_delete','comment_pin'].includes(value.action)) {
        const oldCard=state.root.querySelector(`[data-public-feed] [data-post-id="${Number(value.post_id)}"]`);
        const commentsOpen=oldCard?.querySelector('[data-comment-focus]')?.getAttribute('aria-expanded')==='true';
        const fresh=await CiteMediaApi.post(Number(value.post_id));
        const index=state.data.posts.findIndex(post=>post.id===fresh.id);
        if(index>=0)state.data.posts[index]=fresh;
        if(oldCard){const newCard=makePostCard(fresh);oldCard.replaceWith(newCard);if(commentsOpen)await newCard.openComments();}
      }else await load();
    }catch(error){
      notify('error',saved?'Your action was saved, but the post could not refresh. Reload the page.':error.response?.data?.message||'The media request failed.');
      if(value.action==='approve'||value.action==='reject')await loadModeration(state.queuePage);
    }finally{state.pending.delete(key);}
  }

  function featuredMarkup(events) {
    if (window.StudentHomeCarousel) return '';
    if(!events.length)return '';
    return `<section class="relative overflow-hidden rounded-xl border border-[#397565]/25 bg-[#121017] text-white shadow-[0_18px_50px_rgba(18,16,23,.14)]" data-event-carousel><div class="cite-carousel-stage relative min-h-64" data-carousel-slides>${events.map((event,index)=>`<article class="absolute inset-0 ${index?'hidden':''}" data-carousel-slide>${event.poster_path?`<img class="absolute inset-0 h-full w-full object-cover" src="${esc(event.poster_path)}" alt="${esc(event.title)} carousel image">`:''}<div class="cite-carousel-overlay absolute inset-0"></div><div class="cite-carousel-content relative flex min-h-64 max-w-2xl flex-col justify-end p-6 sm:p-9"><span class="self-start rounded-full bg-[#C6F24E] px-3 py-1 text-[9px] font-black uppercase text-[#121017]">${event.is_featured?'Featured event':'Current event'}</span><h2 class="mt-4 text-2xl font-black sm:text-4xl">${esc(event.title)}</h2>${event.description?`<p class="mt-2 line-clamp-2 text-sm leading-6 text-white/70">${esc(event.description)}</p>`:''}<p class="mt-3 text-xs font-bold text-white/65">${esc(formatDate(event.start_at))}${event.location?` · ${esc(event.location)}`:''}</p></div></article>`).join('')}</div>${events.length>1?`<button class="absolute left-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-lg font-black text-[#121017] shadow-lg" type="button" data-carousel-prev aria-label="Previous event">‹</button><button class="absolute right-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-lg font-black text-[#121017] shadow-lg" type="button" data-carousel-next aria-label="Next event">›</button><div class="absolute bottom-4 right-5 z-10 flex gap-1.5">${events.map((_,index)=>`<button class="h-2 rounded-full ${index?'w-2 bg-white/45':'w-6 bg-[#C6F24E]'}" type="button" data-carousel-dot="${index}" aria-label="Show event ${index+1}"></button>`).join('')}</div>`:''}</section>`;
  }

  function eventProgramCardsMarkup(events, emptyMessage = 'No active or upcoming events.') {
    return `<div class="grid gap-3">${events.length?events.map(event=>`<article class="rounded-xl border border-[#121017]/8 bg-[#F7F4ED]/55 p-3"><h3 class="text-sm font-black">${esc(event.title)}</h3><p class="mt-1 text-[9px] text-[#121017]/45">${esc(formatDate(event.start_at))}${event.location?` · ${esc(event.location)}`:''}</p><div class="mt-3 grid gap-2">${event.activities.length?event.activities.map(activity=>`<div class="border-l-2 border-[#C6F24E] pl-3"><strong class="block text-xs">${esc(activity.name)}</strong>${activity.description?`<p class="mt-0.5 line-clamp-2 text-[10px] leading-4 text-[#121017]/50">${esc(activity.description)}</p>`:''}</div>`).join(''):'<p class="text-[10px] text-[#121017]/40">Activities will be announced soon.</p>'}</div></article>`).join(''):`<p class="py-6 text-center text-xs text-[#121017]/45">${esc(emptyMessage)}</p>`}</div>`;
  }

  function eventProgramMarkup(events) {
    return `<section class="hidden rounded-xl border border-[#397565]/15 bg-white p-4 lg:block"><div><p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">What's happening</p><h2 class="mt-1 text-lg font-black">Event activities</h2></div><div class="mt-4">${eventProgramCardsMarkup(events)}</div></section>`;
  }

  function eventActivitiesDialogMarkup(events) {
    const selectedEvent = state.eventId ? events.filter(event => Number(event.id) === Number(state.eventId)) : events;
    const emptyMessage = state.eventId ? 'No activities listed for this event yet.' : 'No active or upcoming events.';
    const heading = state.eventId ? (selectedEvent[0]?.title || 'Event activities') : 'Event activities';
    return `<dialog class="cite-activities-dialog" data-event-activities-dialog aria-labelledby="event-activities-title"><section class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl bg-[#F7F4ED] text-[#121017] shadow-2xl"><header class="flex shrink-0 items-center justify-between border-b border-[#121017]/10 bg-white px-5 py-4"><div><p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">What's happening</p><h2 class="mt-1 text-lg font-black" id="event-activities-title">${esc(heading)}</h2></div><button class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xl text-[#121017]/55 hover:bg-[#121017]/5" type="button" data-close-event-activities aria-label="Close activities">×</button></header><div class="min-h-0 overflow-y-auto p-4">${eventProgramCardsMarkup(selectedEvent, emptyMessage)}</div></section></dialog>`;
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
    return `<section class="cite-review-desk" aria-labelledby="cite-review-title" data-moderation-workspace><div class="cite-review-intro"><div><p class="cite-review-eyebrow">Moderation workspace</p><h2 id="cite-review-title">Pending review <span class="cite-review-count" data-moderation-count>…</span></h2><p>Review posts before they appear in the community feed.</p></div><button class="cite-review-refresh" type="button" data-review-refresh>Refresh queue</button></div><div class="cite-review-body" data-moderation-list role="status">Loading posts…</div><div class="cite-review-pagination" data-moderation-pagination></div></section>`;
  }

  function queueCard(post) {
    const avatar = post.profile_photo_path
      ? `<img class="cite-review-avatar object-cover" src="${esc(post.profile_photo_path)}" alt="${esc(post.author_name)} profile picture" loading="lazy">`
      : `<span class="cite-review-avatar" aria-hidden="true">${esc(post.author_initials)}</span>`;
    const images=post.images?.length?post.images:post.image_path?[post.image_path]:[];
    const media=images.length
      ? `<div class="cite-review-gallery">${images.map((path,index)=>`<img class="cite-review-media" src="${esc(path)}" alt="Photo ${index+1} submitted by ${esc(post.author_name)}" loading="lazy">`).join('')}</div>`
      : post.video_path
        ? `<video class="cite-review-media" src="${esc(post.video_path)}" controls preload="none" playsinline aria-label="Video submitted by ${esc(post.author_name)}"></video>`
        : '';
    return `<article class="cite-review-card"><div class="cite-review-card-meta">${avatar}<div class="min-w-0"><strong>${esc(post.author_name)}</strong> ${CiteMediaPermissions.specialTagMarkup(post.special_tag)}<p>${esc(post.event_title||'General post')} · ${esc(formatDate(post.created_at))}</p></div></div><p class="cite-review-content">${esc(post.content)}</p>${media}<div class="cite-review-actions"><button type="button" data-approve="${post.id}">Approve</button><button type="button" data-reject="${post.id}">Reject</button></div></article>`;
  }

  function renderQueue(queue) {
    const workspace=state.root.querySelector('[data-moderation-workspace]');
    if(!workspace)return;
    workspace.querySelector('[data-moderation-count]').textContent=queue.total.toLocaleString('en-PH');
    const list=workspace.querySelector('[data-moderation-list]');
    list.removeAttribute('role');
    list.innerHTML=queue.posts.length?queue.posts.map(queueCard).join(''):'<div class="cite-review-empty"><span aria-hidden="true">✓</span><strong>All caught up</strong><p>No posts are waiting for review.</p></div>';
    const pagination=workspace.querySelector('[data-moderation-pagination]');
    pagination.innerHTML=queue.total?`<p>Showing ${(queue.page-1)*queue.per_page+1}–${Math.min(queue.page*queue.per_page,queue.total)} of ${queue.total} pending</p><div><button type="button" data-review-page="${queue.page-1}" ${queue.page===1?'disabled':''}>Previous</button><span>Page ${queue.page} of ${queue.page_count}</span><button type="button" data-review-page="${queue.page+1}" ${queue.page===queue.page_count?'disabled':''}>Next</button></div>`:'';
    workspace.querySelector('[data-review-refresh]').onclick=()=>loadModeration(state.queuePage);
    workspace.querySelectorAll('[data-review-page]').forEach(button=>button.onclick=()=>loadModeration(Number(button.dataset.reviewPage)));
    workspace.querySelectorAll('[data-approve]').forEach(button=>button.onclick=()=>execute({action:'approve',post_id:button.dataset.approve}));
    workspace.querySelectorAll('[data-reject]').forEach(button=>button.onclick=async()=>{const reason=await window.Notifications.prompt({title:'Reject post?',message:'Explain why this post was not approved. The author will see this message.',label:'Rejection reason',placeholder:'Enter a clear reason…',action:'Reject post',required:true,maxLength:1000});if(reason)execute({action:'reject',post_id:button.dataset.reject,reason});});
  }

  async function loadModeration(page=1) {
    if(!state.data?.permissions.moderate)return;
    const request=++state.queueRequest;
    const workspace=state.root.querySelector('[data-moderation-workspace]');
    if(!workspace)return;
    workspace.setAttribute('aria-busy','true');
    try{
      const queue=await CiteMediaApi.moderation(page);
      if(request!==state.queueRequest)return;
      state.queuePage=queue.page;
      renderQueue(queue);
    }catch(error){
      if(request!==state.queueRequest)return;
      workspace.querySelector('[data-moderation-list]').innerHTML=`<p class="cite-review-error">${esc(error.response?.data?.message||'The review queue could not be loaded.')} <button type="button" data-review-retry>Try again</button></p>`;
      workspace.querySelector('[data-review-retry]').onclick=()=>loadModeration(page);
    }finally{if(request===state.queueRequest)workspace.removeAttribute('aria-busy');}
  }

  function carouselManagerMarkup(data) {
    if(!data.permissions.manage_carousel)return '';
    return `<details class="rounded-xl border border-[#397565]/15 bg-white p-4"><summary class="cursor-pointer text-sm font-black text-[#397565]">Manage event carousel</summary><form class="mt-4 grid gap-3" data-carousel-form><label class="grid gap-1 text-xs font-bold">Event<select class="h-11 rounded-xl border px-3" name="event_id" required>${data.carousel_events.map(event=>`<option value="${event.id}" data-featured="${event.is_featured?'1':'0'}">${esc(event.title)}</option>`).join('')}</select></label><label class="grid gap-1 text-xs font-bold">Carousel image<input class="rounded-xl border p-2 text-xs" type="file" name="carousel_image" accept="image/jpeg,image/png,image/webp"></label><label class="flex items-center gap-2 text-xs font-bold"><input type="checkbox" name="is_featured" value="1"> Show this active event in the carousel</label><button class="min-h-11 rounded-xl bg-[#397565] px-4 text-xs font-black text-white" type="submit">Save carousel</button>${data.carousel_events.length?'':'<p class="text-xs text-[#121017]/45">No eligible assigned events.</p>'}</form></details>`;
  }

  function render() {
    const data=state.data, root=state.root;
    root.innerHTML=`<div class="cite-media-shell mx-auto w-full space-y-5"><header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">CITE community</p><h1 class="mt-1 text-3xl font-black tracking-tight">Media feed</h1><p class="mt-2 text-sm text-[#121017]/50">Stories and updates from active and completed CITE events.</p></div><span class="self-start rounded-full bg-[#C6F24E]/35 px-3 py-1.5 text-[10px] font-black text-[#397565]">Approved posts only</span></header>${moderationMarkup(data)}${featuredMarkup(data.featured_events)}<div class="cite-media-layout grid items-start gap-6"><div class="min-w-0 space-y-5"><div data-shared-post-form-host></div>${data.own_posts.length?`<details class="rounded-xl border border-[#397565]/15 bg-white p-4" data-post-status><summary class="cursor-pointer text-sm font-black text-[#397565]">Pending and rejected posts (${data.own_posts.length})</summary><div class="mt-3 grid gap-2 sm:grid-cols-2" data-own-posts>${ownPostsMarkup(data.own_posts,data.viewer)}</div></details>`:''}<section><div class="mb-4 flex gap-2 overflow-x-auto pb-1" data-event-filters><button class="shrink-0 rounded-xl px-4 py-2 text-xs font-black ${state.eventId?'bg-white text-[#397565]':'bg-[#397565] text-white'}" type="button" data-event-filter="">Mixed feed</button><button class="shrink-0 rounded-xl border border-[#397565]/20 bg-white px-4 py-2 text-xs font-black text-[#397565] lg:hidden" type="button" data-open-event-activities>Activities</button>${data.active_events.map(event=>`<button class="shrink-0 rounded-xl px-4 py-2 text-xs font-black ${Number(state.eventId)===event.id?'bg-[#397565] text-white':'bg-white text-[#397565]'}" type="button" data-event-filter="${event.id}">${esc(event.title)}</button>`).join('')}</div><div class="w-full space-y-5" data-public-feed></div><div class="mt-5 text-center"><button class="hidden min-h-11 rounded-xl border border-[#397565]/25 bg-white px-6 text-sm font-black text-[#397565] shadow-sm hover:bg-[#397565]/5 disabled:opacity-60" type="button" data-more-posts>Load more posts</button></div></section></div><aside class="space-y-5 lg:sticky lg:top-24">${carouselManagerMarkup(data)}${eventProgramMarkup(data.event_program||[])}</aside></div></div>${eventActivitiesDialogMarkup(data.event_program||[])}`;
    window.StudentHomeCarousel?.render(data.featured_events);
    bindCarousel(root);
    state.form=CiteMediaPostForm.mount(root.querySelector('[data-shared-post-form-host]'),{events:data.post_events,viewer:data.viewer,onSaved:load,notify});
    const activitiesDialog=root.querySelector('[data-event-activities-dialog]');
    root.querySelector('[data-open-event-activities]').onclick=()=>activitiesDialog.showModal();
    root.querySelector('[data-close-event-activities]').onclick=()=>activitiesDialog.close();
    activitiesDialog.addEventListener('click',event=>{if(event.target===activitiesDialog)activitiesDialog.close();});
    renderPublicFeed();
    root.querySelector('[data-more-posts]').onclick=loadMorePosts;
    root.querySelectorAll('[data-event-filter]').forEach(button=>button.onclick=()=>{state.eventId=Number(button.dataset.eventFilter)||null;load();});
    root.querySelectorAll('[data-own-posts] [data-own-edit]').forEach(button=>button.onclick=()=>{button.closest('details')?.removeAttribute('open');const post=data.own_posts.find(item=>item.id===Number(button.dataset.ownEdit));if(post)state.form.edit(post,button.closest('article'));});
    root.querySelectorAll('[data-own-posts] [data-own-delete]').forEach(button=>button.onclick=()=>execute({action:'delete',id:button.dataset.ownDelete},{confirm:'Delete this post?'}));
    const carouselForm=root.querySelector('[data-carousel-form]');
    if(carouselForm){const select=carouselForm.elements.event_id,check=carouselForm.elements.is_featured;const sync=()=>{check.checked=select.selectedOptions[0]?.dataset.featured==='1';};select.onchange=sync;sync();carouselForm.onsubmit=async event=>{event.preventDefault();if(!select.value)return;const data=new FormData(carouselForm);data.set('action','carousel_update');if(!check.checked)data.set('is_featured','0');await execute(data);};}
  }

  function makePostCard(post) {
    return CiteMediaPostCard.create(post,{viewer:state.data.viewer,permissions:state.data.permissions,action:execute,edit:(own,card)=>state.form.edit(own,card)});
  }

  function renderPublicFeed() {
    const feed=state.root.querySelector('[data-public-feed]'),data=state.data;
    if(!feed)return;
    feed.replaceChildren();
    if(data.posts.length)data.posts.forEach(post=>feed.append(makePostCard(post)));
    else feed.innerHTML='<div class="rounded-xl border border-dashed border-[#397565]/25 bg-[#397565]/5 px-6 py-16 text-center"><span class="text-3xl">🌿</span><h3 class="mt-3 font-black">The feed is ready for its first story</h3><p class="mt-1 text-sm text-[#121017]/45">No approved posts match this event.</p></div>';
    state.nextCursor=data.next_cursor;
    state.root.querySelector('[data-more-posts]').classList.toggle('hidden',!state.nextCursor);
  }

  async function loadMorePosts() {
    const button=state.root.querySelector('[data-more-posts]');
    if(!button || button.disabled || !state.nextCursor)return;
    const request=state.feedRequest,cursor=state.nextCursor,eventId=state.eventId;
    button.disabled=true;button.textContent='Loading posts…';
    try{
      const page=await CiteMediaApi.posts({eventId,cursor});
      if(request!==state.feedRequest)return;
      const feed=state.root.querySelector('[data-public-feed]');
      const seen=new Set(state.data.posts.map(post=>post.id));
      page.posts.filter(post=>!seen.has(post.id)).forEach(post=>{state.data.posts.push(post);feed.append(makePostCard(post));});
      state.nextCursor=page.next_cursor;
      button.classList.toggle('hidden',!state.nextCursor);
    }catch(error){notify('error',error.response?.data?.message||'Older posts could not be loaded. Please try again.');}
    finally{if(request===state.feedRequest){button.disabled=false;button.textContent='Load more posts';}}
  }

  async function refreshPublicFeed() {
    const request=++state.feedRequest;
    const updated=await CiteMediaApi.load(state.eventId);
    if(request!==state.feedRequest)return;
    state.data.posts=updated.posts;
    state.data.next_cursor=updated.next_cursor;
    renderPublicFeed();
  }

  async function load() {
    const request=++state.feedRequest;
    try{const data=await CiteMediaApi.load(state.eventId);if(request!==state.feedRequest)return;state.data=data;state.queueRequest++;render();await loadModeration(state.queuePage);}catch(error){if(request===state.feedRequest)state.root.innerHTML=`<div class="rounded-xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/7 p-6 text-center text-sm font-bold text-[#c84510]">${esc(error.response?.data?.message||'The media feed could not be loaded.')}</div>`;}
  }

  async function initialize(root, session) {
    if(!root)return;
    state.root=root;CiteMediaApi.configure(session.csrfToken);await load();
  }
  window.CiteMediaFeed={initialize};
})();
