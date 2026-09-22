(() => {
  "use strict";
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character]);
  const time = (value) => value ? new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }).format(new Date(value.replace(" ", "T"))) : "";
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

  function reactionMarkup(post) {
    const liked = Boolean(post.viewer_reaction);
    return `<button class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-xs font-black ${liked ? "bg-[#397565]/10 text-[#397565]" : "text-[#121017]/55 hover:bg-[#F3F0E9] hover:text-[#397565]"}" type="button" data-reaction="like" aria-pressed="${liked}"><span class="text-lg leading-none" aria-hidden="true">${liked ? "♥" : "♡"}</span><span>${liked ? "Liked" : "Like"}</span></button>`;
  }

  function create(post, context) {
    const { viewer, permissions, action, edit } = context;
    const article = document.createElement("article");
    article.dataset.postId = String(post.id);
    article.className = "overflow-visible rounded-xl border border-[#397565]/15 bg-white shadow-[0_14px_38px_rgba(18,16,23,.07)]";
    const role = CiteMediaPermissions.roleLabel(post.author_role);
    const tone = CiteMediaPermissions.roleTone(post.author_role);
    const authorAvatar = post.profile_photo_path
      ? `<img class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-[#397565]/15" src="${esc(post.profile_photo_path)}" alt="${esc(post.author_name)} profile picture">`
      : `<span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[#397565] text-xs font-black text-white ring-2 ring-[#397565]/10">${esc(post.author_initials)}</span>`;
    article.innerHTML = `<header class="flex items-center gap-3 p-4 sm:p-5">${authorAvatar}<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="truncate text-base">${esc(post.author_name)}</strong><span class="rounded px-2 py-0.5 text-[10px] font-black ${tone}">${esc(role)}</span>${post.is_official ? '<span class="rounded bg-[#C6F24E]/40 px-2 py-0.5 text-[10px] font-black text-[#397565]">Official</span>' : ""}</div><div class="mt-0.5 flex flex-wrap gap-2 text-[11px] text-[#121017]/50"><time>${esc(time(post.created_at))}</time>${post.event_title ? `<span>•</span><span class="font-bold text-[#397565]">${esc(post.event_title)}</span>` : ""}</div></div>${menuMarkup(post, viewer, permissions)}</header>`;

    article.insertAdjacentHTML("beforeend", `<div class="px-4 pb-4 sm:px-5 sm:pb-5"><p class="whitespace-pre-line text-sm leading-6">${esc(post.content)}</p></div>`);

    if (post.image_path) article.insertAdjacentHTML("beforeend", `<div class="cite-post-media"><button class="cite-post-media-open" type="button" data-open-post-image aria-label="View full-size image from ${esc(post.author_name)}"><img class="cite-post-media-image" src="${esc(post.image_path)}" alt="${esc(post.author_name)} post image" loading="lazy"></button></div>`);
    else if (post.video_path) article.insertAdjacentHTML("beforeend", `<div class="cite-post-media"><video class="cite-post-media-video" src="${esc(post.video_path)}" controls preload="metadata" playsinline></video></div>`);

    const engagement = document.createElement("div");
    engagement.className = "px-4 pb-4 pt-4 sm:px-5 sm:pb-5";
    engagement.innerHTML = `<div class="flex items-center justify-between text-xs text-[#121017]/50"><span>${post.reactions_count} like${post.reactions_count === 1 ? "" : "s"}</span><span>${post.comments_count} comment${post.comments_count === 1 ? "" : "s"}</span></div><div class="mt-2 flex items-center gap-2 border-y border-[#397565]/10 py-2">${reactionMarkup(post)}<button class="min-h-10 rounded-xl px-3 text-xs font-bold text-[#121017]/55 hover:bg-[#F3F0E9]" type="button" data-comment-focus aria-expanded="false">${post.comments_count ? `View comments (${post.comments_count})` : 'Comment'}</button></div><div class="hidden" data-comments-panel><div class="grid gap-3 pt-4" data-comments></div><button class="mt-3 hidden min-h-10 rounded-xl border border-[#397565]/20 px-4 text-xs font-black text-[#397565]" type="button" data-more-comments>Load more comments</button><form class="mt-4 flex items-start gap-2 border-t border-[#397565]/10 pt-4" data-comment-form><textarea class="min-h-10 min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-2 text-sm outline-none placeholder:text-[#121017]/45" name="body" rows="1" maxlength="1000" placeholder="Add a comment…" required></textarea><button class="min-h-10 rounded-lg bg-[#397565] px-4 text-xs font-bold text-white">Post</button></form></div>`;
    const comments = engagement.querySelector("[data-comments]");
    const panel = engagement.querySelector("[data-comments-panel]");
    const toggle = engagement.querySelector("[data-comment-focus]");
    const more = engagement.querySelector("[data-more-comments]");
    let loadedComments = [];
    let nextCursor = null;
    let loading = false;
    let opened = false;
    const renderComments = () => {
      comments.replaceChildren();
      loadedComments.forEach(comment => {
        const item = document.createElement("div");
        item.className = "flex gap-2";
        item.innerHTML = `<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#397565]/10 text-[10px] font-black text-[#397565]">${esc(comment.author_initials)}</span><div class="min-w-0 flex-1"><div class="rounded-lg bg-[#F7F4ED] px-3 py-2"><div class="flex flex-wrap items-center gap-2"><strong class="text-xs">${esc(comment.author_name)}</strong>${Number(comment.user_id) === Number(post.user_id) ? '<span class="rounded bg-[#397565]/10 px-1.5 py-0.5 text-[9px] font-black text-[#397565]">Author</span>' : ""}${comment.is_pinned ? '<span class="text-[9px] font-black text-[#397565]" aria-label="Pinned comment">📌 Pinned</span>' : ""}</div><p class="mt-1 whitespace-pre-wrap break-words text-xs leading-5">${esc(comment.body)}</p></div><div class="mt-1 flex gap-2">${Number(comment.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-edit-comment="${comment.id}">Edit</button><button class="px-2 text-[10px] font-bold text-[#FF6B2C]" type="button" data-delete-comment="${comment.id}">Delete</button>` : ""}${Number(post.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-pin-comment="${comment.id}" data-pin="${comment.is_pinned ? "0" : "1"}">${comment.is_pinned ? "Unpin" : "Pin"}</button>` : ""}</div></div>`;
        item.querySelector('[data-delete-comment]')?.addEventListener('click', () => action({action:'comment_delete',post_id:post.id,comment_id:comment.id}));
        item.querySelector('[data-edit-comment]')?.addEventListener('click', async () => { const body = await window.Notifications.prompt({title:'Edit comment',message:'Update your comment below.',label:'Comment',value:comment.body,action:'Save comment',required:true,maxLength:1000}); if(body) action({action:'comment_update',post_id:post.id,comment_id:comment.id,body}); });
        item.querySelector('[data-pin-comment]')?.addEventListener('click', () => action({action:'comment_pin',post_id:post.id,comment_id:comment.id,pin:!comment.is_pinned}));
        comments.append(item);
      });
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

    engagement.querySelector("[data-reaction]").onclick = (event) => action({ action: "reaction_toggle", post_id: post.id, type: event.currentTarget.dataset.reaction, active: !Boolean(post.viewer_reaction) });
    engagement.querySelector("form").onsubmit = (event) => { event.preventDefault(); const body = event.currentTarget.elements.body.value.trim(); if (body) action({ action: "comment_create", post_id: post.id, body }); };
    article.append(engagement);

    article.querySelector("[data-own-edit]")?.addEventListener("click", () => { article.querySelector('[data-post-menu]')?.removeAttribute('open'); edit(post, article); });
    article.querySelector('[data-open-post-image]')?.addEventListener('click', () => openImage(post.image_path, `${post.author_name} post image`));
    article.querySelector("[data-own-delete]")?.addEventListener("click", () => action({ action: "delete", id: post.id }, { confirm: "Delete this post?" }));
    article.querySelector("[data-hide]")?.addEventListener("click", async () => { const reason = await window.Notifications.prompt({ title: "Hide this post?", message: "Explain why this post is being hidden. This is retained for moderation records.", label: "Reason", placeholder: "Enter the moderation reason…", action: "Hide post", required: true, maxLength: 1000 }); if (reason) action({ action: "hide", post_id: post.id, reason }); });
    article.querySelectorAll("[data-post-menu] button").forEach((button) => button.addEventListener("click", () => button.closest("details").removeAttribute("open")));
    return article;
  }

  document.addEventListener("click", (event) => {
    document.querySelectorAll(".cite-post-menu[open]").forEach((menu) => {
      if (!menu.contains(event.target)) menu.removeAttribute("open");
    });
  });

  window.CiteMediaPostCard = { create, openImage };
})();
