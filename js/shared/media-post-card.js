(() => {
  "use strict";
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character]);
  const time = (value) => value ? new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }).format(new Date(value.replace(" ", "T"))) : "";

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
    article.className = "overflow-visible rounded-xl border border-[#397565]/15 bg-white shadow-[0_14px_38px_rgba(18,16,23,.07)]";
    const role = CiteMediaPermissions.roleLabel(post.author_role);
    const tone = CiteMediaPermissions.roleTone(post.author_role);
    article.innerHTML = `<header class="flex items-center gap-3 p-4 sm:p-5"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[#397565] text-xs font-black text-white ring-2 ring-[#397565]/10">${esc(post.author_initials)}</span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="truncate text-base">${esc(post.author_name)}</strong><span class="rounded px-2 py-0.5 text-[10px] font-black ${tone}">${esc(role)}</span>${post.is_official ? '<span class="rounded bg-[#C6F24E]/40 px-2 py-0.5 text-[10px] font-black text-[#397565]">Official</span>' : ""}</div><div class="mt-0.5 flex flex-wrap gap-2 text-[11px] text-[#121017]/50"><time>${esc(time(post.created_at))}</time>${post.event_title ? `<span>•</span><span class="font-bold text-[#397565]">${esc(post.event_title)}</span>` : ""}</div></div>${menuMarkup(post, viewer, permissions)}</header>`;

    if (post.image_path) article.insertAdjacentHTML("beforeend", `<div class="w-full overflow-hidden bg-[#121017]"><img class="w-full object-contain" style="max-height:720px" src="${esc(post.image_path)}" alt="${esc(post.author_name)} post image"></div>`);
    else if (post.video_path) article.insertAdjacentHTML("beforeend", `<div class="w-full overflow-hidden bg-[#121017]"><video class="w-full" style="max-height:720px" src="${esc(post.video_path)}" controls preload="metadata" playsinline></video></div>`);

    const engagement = document.createElement("div");
    engagement.className = "px-4 pb-4 sm:px-5 sm:pb-5";
    engagement.innerHTML = `<div class="pt-4"><p class="whitespace-pre-line text-sm leading-6">${esc(post.content)}</p></div><div class="mt-3 flex items-center justify-between text-xs text-[#121017]/50"><span>${post.reactions_count} like${post.reactions_count === 1 ? "" : "s"}</span><span>${post.comments_count} comment${post.comments_count === 1 ? "" : "s"}</span></div><div class="mt-2 flex items-center gap-2 border-y border-[#397565]/10 py-2">${reactionMarkup(post)}<button class="min-h-10 rounded-xl px-3 text-xs font-bold text-[#121017]/55 hover:bg-[#F3F0E9]" type="button" data-comment-focus>Comment</button></div><div class="grid gap-3 pt-4" data-comments></div><form class="mt-4 flex items-start gap-2 border-t border-[#397565]/10 pt-4" data-comment-form><textarea class="min-h-10 min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-2 text-sm outline-none placeholder:text-[#121017]/45" name="body" rows="1" maxlength="1000" placeholder="Add a comment…" required></textarea><button class="min-h-10 rounded-lg bg-[#397565] px-4 text-xs font-bold text-white">Post</button></form>`;
    const comments = engagement.querySelector("[data-comments]");
    (post.comments || []).forEach((comment) => {
      const item = document.createElement("div");
      item.className = "flex gap-2";
      item.innerHTML = `<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#397565]/10 text-[10px] font-black text-[#397565]">${esc(comment.author_initials)}</span><div class="min-w-0 flex-1"><div class="rounded-lg bg-[#F7F4ED] px-3 py-2"><div class="flex flex-wrap items-center gap-2"><strong class="text-xs">${esc(comment.author_name)}</strong>${Number(comment.user_id) === Number(post.user_id) ? '<span class="rounded bg-[#397565]/10 px-1.5 py-0.5 text-[9px] font-black text-[#397565]">Author</span>' : ""}${comment.is_pinned ? '<span class="text-[9px] font-black text-[#397565]" aria-label="Pinned comment">📌 Pinned</span>' : ""}</div><p class="mt-1 whitespace-pre-wrap break-words text-xs leading-5">${esc(comment.body)}</p></div><div class="mt-1 flex gap-2">${Number(comment.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-edit-comment="${comment.id}">Edit</button><button class="px-2 text-[10px] font-bold text-[#FF6B2C]" type="button" data-delete-comment="${comment.id}">Delete</button>` : ""}${Number(post.user_id) === Number(viewer.id) ? `<button class="px-2 text-[10px] font-bold text-[#397565]" type="button" data-pin-comment="${comment.id}" data-pin="${comment.is_pinned ? "0" : "1"}">${comment.is_pinned ? "Unpin" : "Pin"}</button>` : ""}</div></div>`;
      comments.append(item);
    });

    engagement.querySelector("[data-reaction]").onclick = (event) => action({ action: "reaction_toggle", post_id: post.id, type: event.currentTarget.dataset.reaction, active: !Boolean(post.viewer_reaction) });
    engagement.querySelector("[data-comment-focus]").onclick = () => engagement.querySelector("textarea").focus();
    engagement.querySelector("form").onsubmit = (event) => { event.preventDefault(); const body = event.currentTarget.elements.body.value.trim(); if (body) action({ action: "comment_create", post_id: post.id, body }); };
    engagement.querySelectorAll("[data-delete-comment]").forEach((button) => button.onclick = () => action({ action: "comment_delete", post_id: post.id, comment_id: button.dataset.deleteComment }));
    engagement.querySelectorAll("[data-edit-comment]").forEach((button) => button.onclick = () => { const comment = (post.comments || []).find((item) => item.id === Number(button.dataset.editComment)); const body = prompt("Edit comment:", comment?.body || ""); if (body?.trim()) action({ action: "comment_update", post_id: post.id, comment_id: button.dataset.editComment, body: body.trim() }); });
    engagement.querySelectorAll("[data-pin-comment]").forEach((button) => button.onclick = () => action({ action: "comment_pin", post_id: post.id, comment_id: button.dataset.pinComment, pin: button.dataset.pin === "1" }));
    article.append(engagement);

    article.querySelector("[data-own-edit]")?.addEventListener("click", () => edit(post));
    article.querySelector("[data-own-delete]")?.addEventListener("click", () => action({ action: "delete", id: post.id }, { confirm: "Delete this post?" }));
    article.querySelector("[data-hide]")?.addEventListener("click", () => { const reason = prompt("Reason for hiding this post:"); if (reason) action({ action: "hide", post_id: post.id, reason }); });
    article.querySelectorAll("[data-post-menu] button").forEach((button) => button.addEventListener("click", () => button.closest("details").removeAttribute("open")));
    return article;
  }
  window.CiteMediaPostCard = { create };
})();
