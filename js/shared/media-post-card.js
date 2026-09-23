(() => {
  "use strict";
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character]);
  const time = (value) => value ? new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }).format(new Date(value.replace(" ", "T"))) : "";
  const relativeTime = value => {
    if (!value) return '';
    const date = new Date(value.replace(' ', 'T'));
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;
    return new Intl.DateTimeFormat('en-PH', {month:'short',day:'numeric'}).format(date);
  };
  let imageDialog;

  function openImage(src, alt) {
    if (!src) return;
    if (!imageDialog) {
      imageDialog = document.createElement('dialog');
      imageDialog.className = 'cite-image-dialog';
      imageDialog.setAttribute('aria-label', 'Full-size photo');
      imageDialog.innerHTML = '<button type="button" aria-label="Close full-size photo">Close photo ×</button><img alt="">';
      imageDialog.querySelector('button').onclick = () => imageDialog.close();
      imageDialog.onclick = event => { if (event.target === imageDialog) imageDialog.close(); };
      imageDialog.addEventListener('close', () => imageDialog.querySelector('img').removeAttribute('src'));
      document.body.append(imageDialog);
    }
    const image = imageDialog.querySelector('img');
    image.src = src;
    image.alt = alt;
    if (!imageDialog.open) imageDialog.showModal();
  }

  function menuMarkup(post, viewer, permissions) {
    const canEdit = CiteMediaPermissions.canEdit(post, viewer);
    const canDelete = CiteMediaPermissions.canDelete(post, viewer);
    if (!canEdit && !canDelete && !permissions.hide) return "";
    return `<details class="cite-post-menu relative shrink-0" data-post-menu>
      <summary class="grid h-11 w-11 cursor-pointer place-items-center rounded-lg text-lg font-black tracking-[.12em] text-[#121017] hover:bg-[#397565]/8" aria-label="Post options" title="Post options">•••</summary>
      <div class="absolute right-0 top-12 z-30 min-w-40 overflow-hidden rounded-xl border border-[#121017]/10 bg-white p-1.5 shadow-2xl">
        ${canEdit ? '<button class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-xs font-black text-[#397565] hover:bg-[#397565]/8" type="button" data-own-edit>Edit post</button>' : ""}
        ${canDelete ? '<button class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-xs font-black text-[#c84510] hover:bg-[#FF6B2C]/8" type="button" data-own-delete>Delete post</button>' : ""}
        ${permissions.hide ? '<button class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-xs font-black text-[#c84510] hover:bg-[#FF6B2C]/8" type="button" data-hide>Hide post</button>' : ""}
      </div>
    </details>`;
  }

  const reactionDetails = {
    like: { label: "Like", icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.5 10.5v10H4a1.5 1.5 0 0 1-1.5-1.5v-7A1.5 1.5 0 0 1 4 10.5h3.5Zm0 0 4.2-7.2a2.2 2.2 0 0 1 2.1 2.1v3.1h4.3a2.4 2.4 0 0 1 2.3 2.9l-1.4 7a2.6 2.6 0 0 1-2.5 2.1H7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>' },
    love: { label: "Love", icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.8 8.8c0 5.2-8.8 11-8.8 11s-8.8-5.8-8.8-11A4.8 4.8 0 0 1 12 6.1a4.8 4.8 0 0 1 8.8 2.7Z" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7.8 9.1c.5-1.1 1.4-1.7 2.5-1.8" stroke="white" stroke-width="1.4" stroke-linecap="round"/></svg>' },
    laugh: { label: "Laugh", icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9.2" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 10h.1m6.8 0h.1M7.8 14.1c.8 2.1 2.2 3.2 4.2 3.2s3.4-1.1 4.2-3.2H7.8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>' }
  };
  let reactionDialog = null;
  let reactionDialogState = null;

  function ensureReactionDialog() {
    if (reactionDialog) return reactionDialog;
    reactionDialog = document.createElement('dialog');
    reactionDialog.className = 'cite-reactions-dialog';
    reactionDialog.setAttribute('aria-labelledby', 'cite-reactions-title');
    reactionDialog.innerHTML = `<section class="cite-reactions-panel"><header class="cite-reactions-header"><h2 id="cite-reactions-title">Reactions</h2><button type="button" data-close-reactions aria-label="Close reactions">×</button></header><nav class="cite-reactions-tabs" data-reaction-tabs aria-label="Filter reactions"></nav><div class="cite-reactions-list" data-reaction-list><p class="cite-reactions-empty">Loading reactions…</p></div><button class="cite-reactions-more" type="button" data-reactions-more>Show more</button></section>`;
    reactionDialog.querySelector('[data-close-reactions]').onclick = () => reactionDialog.close();
    reactionDialog.querySelector('[data-reactions-more]').onclick = () => loadReactionList(true);
    reactionDialog.addEventListener('click', event => { if (event.target === reactionDialog) reactionDialog.close(); });
    reactionDialog.querySelector('[data-reaction-tabs]').addEventListener('click', event => {
      const button = event.target.closest('[data-reaction-filter]');
      if (!button || !reactionDialogState) return;
      reactionDialogState.type = button.dataset.reactionFilter;
      reactionDialogState.page = 1;
      reactionDialogState.items = [];
      loadReactionList(false);
    });
    document.body.append(reactionDialog);
    return reactionDialog;
  }

  function renderReactionDialog(data) {
    const tabs = reactionDialog.querySelector('[data-reaction-tabs]');
    const allCount = Object.values(data.counts).reduce((sum, count) => sum + Number(count), 0);
    const options = [['all', 'All', allCount], ...Object.entries(reactionDetails).map(([type, detail]) => [type, detail.label, Number(data.counts[type] || 0)])];
    tabs.innerHTML = options.map(([type, label, count]) => `<button type="button" data-reaction-filter="${type}" aria-pressed="${reactionDialogState.type === type}" class="${reactionDialogState.type === type ? 'is-active' : ''}">${type === 'all' ? '' : `<span aria-hidden="true">${reactionDetails[type].icon}</span>`}${label}<span>${count}</span></button>`).join('');
    const list = reactionDialog.querySelector('[data-reaction-list]');
    if (!reactionDialogState.items.length) {
      list.innerHTML = '<p class="cite-reactions-empty">No reactions yet.</p>';
    } else {
      list.innerHTML = reactionDialogState.items.map(reactor => `<article class="cite-reaction-person">${reactor.profile_photo_path ? `<img src="${esc(reactor.profile_photo_path)}" alt="" loading="lazy">` : `<span class="cite-reaction-avatar">${esc(reactor.initials)}</span>`}<span class="cite-reaction-person-name"><strong>${esc(reactor.full_name)}</strong><small>${esc(CiteMediaPermissions.roleLabel(reactor.role_name))} · ${esc(time(reactor.created_at))}</small></span><span class="cite-reaction-person-type" data-reaction-icon="${esc(reactor.type)}" title="${esc(reactionDetails[reactor.type]?.label || 'Reaction')}">${reactionDetails[reactor.type]?.icon || ''}</span></article>`).join('');
    }
    const more = reactionDialog.querySelector('[data-reactions-more]');
    more.classList.toggle('is-visible', data.has_more);
    more.disabled = false;
  }

  async function loadReactionList(append) {
    if (!reactionDialogState || reactionDialogState.loading) return;
    reactionDialogState.loading = true;
    const list = reactionDialog.querySelector('[data-reaction-list]');
    const more = reactionDialog.querySelector('[data-reactions-more]');
    if (!append) list.innerHTML = '<p class="cite-reactions-empty">Loading reactions…</p>';
    else more.disabled = true;
    try {
      const data = await CiteMediaApi.reactions(reactionDialogState.postId, {commentId: reactionDialogState.commentId, type: reactionDialogState.type, page: reactionDialogState.page});
      reactionDialogState.items = append ? [...reactionDialogState.items, ...data.reactors] : data.reactors;
      renderReactionDialog(data);
      reactionDialogState.page = append ? reactionDialogState.page + 1 : 2;
    } catch (error) {
      if (!append) list.innerHTML = `<p class="cite-reactions-empty">${esc(error.response?.data?.message || 'Reactions could not be loaded.')}</p>`;
      more.classList.remove('is-visible');
    } finally { reactionDialogState.loading = false; }
  }

  async function openReactionList(postId, commentId = null) {
    ensureReactionDialog();
    reactionDialogState = {postId: Number(postId), commentId: commentId === null ? null : Number(commentId), type: 'all', page: 1, items: [], loading: false};
    reactionDialog.querySelector('#cite-reactions-title').textContent = commentId === null ? 'Post reactions' : 'Comment reactions';
    reactionDialog.querySelector('[data-reaction-list]').innerHTML = '<p class="cite-reactions-empty">Loading reactions…</p>';
    reactionDialog.querySelector('[data-reactions-more]').classList.remove('is-visible');
    if (!reactionDialog.open) reactionDialog.showModal();
    await loadReactionList(false);
  }

  function reactionPickerMarkup(scope, current, buttonClass = "") {
    const selected = reactionDetails[current] || reactionDetails.like;
    const active = Boolean(current);
    return `<div class="cite-reaction-picker ${buttonClass}" data-reaction-picker data-reaction-scope="${scope}">
      <button class="cite-reaction-current ${active ? "is-reacted" : ""}" type="button" data-reaction-current data-reaction-type="${current || 'like'}" aria-label="Choose a reaction${active ? `, currently ${selected.label}` : ''}" aria-pressed="${active}" aria-expanded="false"><span class="cite-reaction-icon" aria-hidden="true">${active ? selected.icon : reactionDetails.like.icon}</span>${scope === "post" ? `<span>${active ? selected.label : "React"}</span>` : ""}</button>
      <div class="cite-reaction-menu" role="group" aria-label="Choose a reaction">${Object.entries(reactionDetails).map(([type, detail]) => `<button type="button" data-reaction-choice="${type}" aria-label="${detail.label}" title="${detail.label}" class="${current === type ? "is-selected" : ""}"><span aria-hidden="true">${detail.icon}</span><span>${detail.label}</span></button>`).join("")}</div>
    </div>`;
  }
  function reactionSummary(counts = {}, total = 0, scope = 'post', entityId = '') {
    if (!Number(total)) return '';
    const active = Object.entries(reactionDetails).filter(([type]) => Number(counts[type]) > 0);
    return `<button class="cite-reaction-summary" type="button" data-open-reactions data-reaction-kind="${scope}" data-reaction-entity="${entityId}" aria-label="View ${total} reactions">${active.map(([type, detail]) => `<span data-reaction-icon="${type}" aria-hidden="true">${detail.icon}</span>`).join('')}<span>${Number(total)}</span></button>`;
  }

  function create(post, context) {
    const { viewer, permissions, action, edit } = context;
    const article = document.createElement("article");
    article.dataset.postId = String(post.id);
    article.className = "overflow-visible rounded-xl border border-[#397565]/15 bg-white shadow-[0_14px_38px_rgba(18,16,23,.07)]";
    const role = CiteMediaPermissions.roleLabel(post.author_role);
    const tone = CiteMediaPermissions.roleTone(post.author_role);
    const devBadge = CiteMediaPermissions.specialTagMarkup(post.special_tag);
    const roleBadge = post.author_role === 'Student' ? '' : `<span class="rounded px-2 py-0.5 text-[10px] font-black ${tone}">${esc(role)}</span>`;
    const authorAvatar = post.profile_photo_path
      ? `<img class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-[#397565]/15" src="${esc(post.profile_photo_path)}" alt="${esc(post.author_name)} profile picture">`
      : `<span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[#397565] text-xs font-black text-white ring-2 ring-[#397565]/10">${esc(post.author_initials)}</span>`;
    article.innerHTML = `<header class="flex items-center gap-3 p-4 sm:p-5"><a class="flex min-w-0 flex-1 items-center gap-3 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-[#397565]" href="pages/shared/public-profile.html?user_id=${encodeURIComponent(post.user_id)}&v=20260924-profile-search-3" aria-label="View ${esc(post.author_name)} profile">${authorAvatar}<span class="min-w-0 flex-1"><span class="flex flex-wrap items-center gap-2"><strong class="truncate text-base">${esc(post.author_name)}</strong>${roleBadge}${post.is_official ? '<span class="rounded bg-[#C6F24E]/40 px-2 py-0.5 text-[10px] font-black text-[#397565]">Official</span>' : ""}</span><span class="mt-0.5 flex flex-wrap gap-2 text-[11px] text-[#121017]/50"><time>${esc(time(post.created_at))}</time>${post.event_title ? `<span>•</span><span class="font-bold text-[#397565]">${esc(post.event_title)}</span>` : ""}</span></span></a>${menuMarkup(post, viewer, permissions)}</header>`;

    article.insertAdjacentHTML("beforeend", `<div class="px-4 pb-4 sm:px-5 sm:pb-5"><p class="whitespace-pre-line text-sm leading-6">${esc(post.content)}</p></div>`);

    const images = post.images?.length ? post.images : post.image_path ? [post.image_path] : [];
    if (images.length) article.insertAdjacentHTML("beforeend", `<div class="cite-post-gallery ${images.length === 1 ? 'is-single' : ''}" data-post-gallery>${images.map((path,index) => `<button class="cite-post-gallery-item" type="button" data-open-post-image="${index}" aria-label="View photo ${index + 1} of ${images.length} from ${esc(post.author_name)}"><img src="${esc(path)}" alt="${esc(post.author_name)} post photo ${index + 1}" loading="lazy"></button>`).join('')}</div>`);
    else if (post.video_path) article.insertAdjacentHTML("beforeend", `<div class="cite-post-media"><video class="cite-post-media-video" src="${esc(post.video_path)}" controls preload="metadata" playsinline></video></div>`);

    const engagement = document.createElement("div");
    engagement.className = "px-4 pb-4 pt-4 sm:px-5 sm:pb-5";
    engagement.innerHTML = `<div class="flex items-center justify-between text-xs text-[#121017]/50"><div>${reactionSummary(post.reaction_counts, post.reactions_count, 'post', post.id)}</div><span>${post.comments_count} comment${post.comments_count === 1 ? "" : "s"}</span></div><div class="mt-2 flex items-center gap-2 border-y border-[#397565]/10 py-2">${reactionPickerMarkup("post", post.viewer_reaction)}<button class="min-h-10 rounded-xl px-3 text-xs font-bold text-[#121017]/55 hover:bg-[#F3F0E9]" type="button" data-comment-focus aria-expanded="false">${post.comments_count ? `View comments (${post.comments_count})` : 'Comment'}</button></div><div class="hidden" data-comments-panel><div class="grid gap-3 pt-4" data-comments></div><button class="mt-3 hidden min-h-10 rounded-xl border border-[#397565]/20 px-4 text-xs font-black text-[#397565]" type="button" data-more-comments>Load more comments</button><form class="mt-4 flex items-start gap-2 border-t border-[#397565]/10 pt-4" data-comment-form><textarea class="min-h-10 min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-2 text-sm outline-none placeholder:text-[#121017]/45" name="body" rows="1" maxlength="1000" placeholder="Add a comment…" required></textarea><button class="min-h-10 rounded-lg bg-[#397565] px-4 text-xs font-bold text-white">Comment</button></form></div>`;
    const comments = engagement.querySelector("[data-comments]");
    const panel = engagement.querySelector("[data-comments-panel]");
    const toggle = engagement.querySelector("[data-comment-focus]");
    const more = engagement.querySelector("[data-more-comments]");
    let loadedComments = [];
    let nextCursor = null;
    let loading = false;
    let opened = false;
    const commentAvatar = comment => comment.profile_photo_path
      ? `<img class="h-8 w-8 shrink-0 rounded-full object-cover" src="${esc(comment.profile_photo_path)}" alt="${esc(comment.author_name)} profile picture" loading="lazy">`
      : `<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#397565]/10 text-[10px] font-black text-[#397565]">${esc(comment.author_initials)}</span>`;
    const bindReactionPicker = (picker, comment = null) => {
      const scope = comment ? 'comment' : 'post';
      picker.querySelector('[data-reaction-current]').onclick = () => {
        const open = !picker.classList.contains('is-open');
        picker.classList.toggle('is-open', open);
        picker.querySelector('[data-reaction-current]').setAttribute('aria-expanded', String(open));
      };
      picker.querySelectorAll('[data-reaction-choice]').forEach(choice => choice.onclick = () => {
        picker.classList.remove('is-open');
        picker.querySelector('[data-reaction-current]').setAttribute('aria-expanded', 'false');
        const selected = comment ? comment.viewer_reaction : post.viewer_reaction;
        submitReaction(picker, choice.dataset.reactionChoice, selected !== choice.dataset.reactionChoice);
      });
      picker.dataset.reactionScope = scope;
    };
    const renderComment = (comment, isReply = false) => {
      const item = document.createElement('div');
      item.className = 'flex min-w-0 gap-2';
      item.dataset.commentId = String(comment.id);
      item.innerHTML = `${commentAvatar(comment)}<div class="min-w-0 flex-1"><div class="rounded-lg bg-[#F7F4ED] px-3 py-2"><div class="flex flex-wrap items-center gap-2"><strong class="text-xs">${esc(comment.author_name)}</strong>${CiteMediaPermissions.specialTagMarkup(comment.special_tag)}${Number(comment.user_id) === Number(post.user_id) ? '<span class="rounded bg-[#397565]/10 px-1.5 py-0.5 text-[9px] font-black text-[#397565]">Author</span>' : ''}${comment.is_pinned ? '<span class="text-[9px] font-black text-[#397565]" aria-label="Pinned comment">📌 Pinned</span>' : ''}<time class="ml-auto text-[10px] text-[#121017]/45" datetime="${esc(comment.created_at?.replace(" ", "T"))}" title="${esc(time(comment.created_at))}">${esc(time(comment.created_at))}</time></div><p class="mt-1 whitespace-pre-wrap break-words text-xs leading-5">${esc(comment.body)}</p></div><div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">${reactionSummary(comment.reaction_counts, comment.reactions_count, 'comment', comment.id)}${reactionPickerMarkup("comment", comment.viewer_reaction)}${!isReply ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-reply-comment="${comment.id}" aria-expanded="false">Reply</button>` : ''}${Number(comment.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-edit-comment="${comment.id}">Edit</button><button class="px-2 text-[10px] font-bold text-[#FF6B2C]" type="button" data-delete-comment="${comment.id}">Delete</button>` : ''}${!isReply && Number(post.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-pin-comment="${comment.id}" data-pin="${comment.is_pinned ? '0' : '1'}">${comment.is_pinned ? 'Unpin' : 'Pin'}</button>` : ''}</div><div data-reply-form-host></div><div data-replies></div></div>`;
      item.querySelector('[data-open-reactions]')?.addEventListener('click', () => openReactionList(post.id, comment.id));
      bindReactionPicker(item.querySelector('[data-reaction-picker]'), comment);
      item.querySelector('[data-delete-comment]')?.addEventListener('click', () => action({action:'comment_delete',post_id:post.id,comment_id:comment.id}));
      item.querySelector('[data-edit-comment]')?.addEventListener('click', async () => { const body = await window.Notifications.prompt({title:'Edit comment',message:'Update your comment below.',label:'Comment',value:comment.body,action:'Save comment',required:true,maxLength:1000}); if(body) action({action:'comment_update',post_id:post.id,comment_id:comment.id,body}); });
      item.querySelector('[data-pin-comment]')?.addEventListener('click', () => action({action:'comment_pin',post_id:post.id,comment_id:comment.id,pin:!comment.is_pinned}));
      const commentTime = item.querySelector('time');
      if (commentTime) {
        commentTime.textContent = relativeTime(comment.created_at);
        commentTime.title = time(comment.created_at);
        commentTime.className = 'cite-comment-time';
        commentTime.parentElement.after(commentTime);
      }
      const manageButtons = [...item.querySelectorAll('[data-edit-comment],[data-delete-comment],[data-pin-comment]')];
      if (manageButtons.length) {
        const menu = document.createElement('details');
        menu.className = 'cite-comment-menu';
        menu.innerHTML = '<summary aria-label="Comment options" title="Comment options"><span aria-hidden="true">•••</span></summary><div role="group" aria-label="Comment actions"></div>';
        const menuItems = menu.querySelector('[role="group"]');
        manageButtons.forEach(button => menuItems.append(button));
        item.querySelector('.rounded-lg > div:first-child')?.append(menu);
        menuItems.querySelectorAll('button').forEach(button => button.addEventListener('click', () => menu.removeAttribute('open')));
      }
      item.querySelector('[data-reply-comment]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        const host = item.querySelector('[data-reply-form-host]');
        if (host.childElementCount) { host.replaceChildren(); button.setAttribute('aria-expanded', 'false'); return; }
        const form = document.createElement('form');
        form.className = 'mt-2 grid gap-2';
        form.innerHTML = `<label class="text-[11px] font-bold text-[#397565]" for="reply-${comment.id}">Reply to ${esc(comment.author_name)}</label><textarea class="min-h-16 w-full min-w-0 rounded-lg border border-[#397565]/20 bg-white px-3 py-2 text-sm outline-none" id="reply-${comment.id}" name="body" rows="2" maxlength="1000" placeholder="Write a reply…" required></textarea><div class="flex flex-wrap gap-2"><button class="min-h-9 rounded-lg bg-[#397565] px-4 text-xs font-bold text-white" type="submit">Reply</button><button class="min-h-9 rounded-lg px-3 text-xs font-bold text-[#121017]/55" type="button" data-cancel-reply>Cancel</button></div>`;
        form.querySelector('[data-cancel-reply]').onclick = () => { host.replaceChildren(); button.setAttribute('aria-expanded', 'false'); };
        form.onsubmit = async submitEvent => { submitEvent.preventDefault(); const body = form.elements.body.value.trim(); if (!body) return; const submit = form.querySelector('[type="submit"]'); submit.disabled = true; try { await action({action:'comment_create',post_id:post.id,parent_comment_id:comment.id,body}); } finally { if (submit.isConnected) submit.disabled = false; } };
        host.append(form); button.setAttribute('aria-expanded', 'true'); form.elements.body.focus();
      });
      if (!isReply && comment.replies?.length) {
        const replies = item.querySelector('[data-replies]');
        replies.className = 'mt-2 grid gap-2 border-l-2 border-[#397565]/15 pl-3';
        comment.replies.forEach(reply => replies.append(renderComment(reply, true)));
      }
      return item;
    };
    const renderComments = () => {
      comments.replaceChildren();
      loadedComments.forEach(comment => comments.append(renderComment(comment)));
    };
    const loadComments = async (cursor = null) => {
      if (loading) return;
      loading = true;
      more.disabled = true;
      if (!cursor) comments.innerHTML = '<p class="text-xs text-[#121017]/45">Loading comments…</p>';
      try {
        const result = await CiteMediaApi.comments(post.id, cursor);
        const seen = new Set(cursor ? loadedComments.map(comment => comment.id) : []);
        loadedComments = cursor ? [...loadedComments, ...result.comments.filter(comment => !seen.has(comment.id))] : result.comments;
        nextCursor = result.next_cursor;
        renderComments();
        more.classList.toggle('hidden', !nextCursor);
      } catch (error) {
        if (!cursor) comments.innerHTML = '<p class="text-xs text-[#c84510]">Comments could not be loaded. Close and reopen to retry.</p>';
        else more.textContent = 'Retry loading comments';
      } finally { loading = false; more.disabled = false; }
    };
    article.openComments = async () => {
      if (opened) return;
      opened = true;
      panel.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.textContent = 'Hide comments';
      await loadComments();
    };
    toggle.onclick = async () => {
      if (opened) { opened = false; panel.classList.add('hidden'); toggle.setAttribute('aria-expanded', 'false'); toggle.textContent = post.comments_count ? `View comments (${post.comments_count})` : 'Comment'; }
      else { await article.openComments(); if (!post.comments_count) engagement.querySelector('textarea').focus(); }
    };
    more.onclick = () => { more.textContent = 'Load more comments'; if (nextCursor) loadComments(nextCursor); };

    bindReactionPicker(engagement.querySelector('[data-reaction-picker]'));
    engagement.querySelector('[data-open-reactions]')?.addEventListener('click', () => openReactionList(post.id));
    async function submitReaction(picker, type, active) {
      const button = picker.querySelector('[data-reaction-current]');
      if (button.disabled) return;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      try {
        const commentId = picker.closest('[data-comment-id]')?.dataset.commentId;
        await action(commentId
          ? { action: "comment_reaction_toggle", post_id: post.id, comment_id: Number(commentId), type, active }
          : { action: "reaction_toggle", post_id: post.id, type, active });
      } finally {
        if (button.isConnected) { button.disabled = false; button.removeAttribute('aria-busy'); }
      }
    }
    engagement.querySelector("form").onsubmit = (event) => { event.preventDefault(); const body = event.currentTarget.elements.body.value.trim(); if (body) action({ action: "comment_create", post_id: post.id, body }); };
    article.append(engagement);

    article.querySelector("[data-own-edit]")?.addEventListener("click", () => { article.querySelector('[data-post-menu]')?.removeAttribute('open'); edit(post, article); });
    article.querySelectorAll('[data-open-post-image]').forEach(button => button.addEventListener('click', () => openImage(images[Number(button.dataset.openPostImage)], `${post.author_name} post photo`)));
    article.querySelector("[data-own-delete]")?.addEventListener("click", () => action({ action: "delete", id: post.id }, { confirm: "Delete this post?" }));
    article.querySelector("[data-hide]")?.addEventListener("click", async () => { const reason = await window.Notifications.prompt({ title: "Hide this post?", message: "Explain why this post is being hidden. This is retained for moderation records.", label: "Reason", placeholder: "Enter the moderation reason…", action: "Hide post", required: true, maxLength: 1000 }); if (reason) action({ action: "hide", post_id: post.id, reason }); });
    article.querySelectorAll("[data-post-menu] button").forEach((button) => button.addEventListener("click", () => button.closest("details").removeAttribute("open")));
    return article;
  }

  document.addEventListener("click", (event) => {
    document.querySelectorAll(".cite-post-menu[open], .cite-comment-menu[open]").forEach((menu) => {
      if (!menu.contains(event.target)) menu.removeAttribute("open");
    });
  });

  window.CiteMediaPostCard = { create, openImage };
})();
