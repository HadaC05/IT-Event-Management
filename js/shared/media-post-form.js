(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const initials = viewer => {
    if (viewer.initials) return String(viewer.initials).toUpperCase();
    const words = String(viewer.full_name || viewer.role || '').trim().split(/\s+/).filter(Boolean);
    return (words.length > 1 ? `${words[0][0]}${words[words.length - 1][0]}` : (words[0] || 'CU').slice(0, 2)).toUpperCase();
  };
  const avatar = viewer => viewer.profile_photo_path
    ? `<img class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-[#397565]/15" src="${esc(viewer.profile_photo_path)}" alt="${esc(viewer.full_name || 'Your')} profile picture">`
    : `<span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017]" aria-hidden="true">${esc(initials(viewer))}</span>`;

  function mount(host, context) {
    const {events, viewer, onSaved, notify} = context;
    host.innerHTML = `<section class="rounded-xl border border-[#397565]/15 bg-white p-4 shadow-[0_12px_35px_rgba(18,16,23,.05)] sm:p-5">
      <form enctype="multipart/form-data" data-shared-post-form>
        <input type="hidden" name="action" value="create"><input type="hidden" name="id"><input type="hidden" name="remove_media" value="0">
        <div class="mb-3 hidden rounded-lg bg-[#397565]/7 px-3 py-2" data-edit-context><p class="text-sm font-black text-[#397565]">Editing your post</p><p class="mt-1 text-xs text-[#121017]/65" data-edit-review-note></p></div>
        <div class="flex items-start gap-4">${avatar(viewer)}<div class="min-w-0 flex-1"><h2 class="sr-only" data-form-title>Create a post</h2><select class="sr-only" name="event_id" aria-label="Post event"><option value="">General community</option>${events.map(event=>`<option value="${event.id}">${esc(event.title)}</option>`).join('')}</select><textarea class="min-h-14 w-full resize-none border-0 bg-transparent py-2 text-base leading-6 outline-none placeholder:text-[#121017]/55" name="content" maxlength="3000" placeholder="Share a photo, video or update…"></textarea></div></div>
        <div class="cite-media-preview mt-3 hidden rounded-xl border border-[#121017]/10 bg-white p-2" data-media-preview><div class="cite-compose-photo-grid" data-image-preview-list></div><video class="hidden" controls playsinline></video></div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#121017]/10 pt-3"><label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-3 text-xs font-bold text-[#397565] hover:bg-[#397565]/10" title="Add up to 30 photos"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg><span>Photos</span><input class="sr-only" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label><label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-3 text-xs font-bold text-[#2F3AE0] hover:bg-[#2F3AE0]/10" title="Add video"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="12" height="12" rx="2"></rect><path d="m15 10 5-2.5v9L15 14"></path></svg><span>Video</span><input class="sr-only" type="file" name="video" accept="video/mp4,video/webm,video/quicktime"></label><button class="hidden min-h-10 px-3 text-xs font-black text-[#FF6B2C]" type="button" data-remove-media>Remove all</button><span class="min-w-0 flex-1 truncate text-xs text-[#121017]/40" data-file-name></span><button class="min-h-11 rounded-xl bg-[#397565] px-6 text-xs font-black text-white disabled:opacity-50" type="submit" data-submit>Post</button><button class="hidden min-h-11 px-4 text-xs font-black text-[#121017]/50" type="button" data-cancel-edit>Cancel</button></div>
      </form></section>`;
    const homeMarker = document.createComment('Post composer home');
    host.before(homeMarker);
    const form = host.querySelector('form'), imageInput = form.querySelector('[name="images[]"]'), videoInput = form.elements.video;
    const preview = host.querySelector('[data-media-preview]'), imageList = preview.querySelector('[data-image-preview-list]'), video = preview.querySelector('video'), fileName = host.querySelector('[data-file-name]');
    let editingPost = null;
    let existingImages = [], existingVideo = null, selectedImages = [], selectedVideo = null, removedImages = [], removeAll = false;
    let videoUrl = null;
    const removeButton = host.querySelector('[data-remove-media]');
    const releaseImages = () => { selectedImages.forEach(item => URL.revokeObjectURL(item.url)); selectedImages = []; };
    const releaseVideo = () => { if (videoUrl) URL.revokeObjectURL(videoUrl); videoUrl = null; selectedVideo = null; videoInput.value = ''; };
    const render = () => {
      imageList.replaceChildren();
      const photos = [...existingImages.map(path => ({path, url: path})), ...selectedImages];
      const sources = photos.map(item => item.url);
      photos.forEach((item, index) => {
        const tile = document.createElement('div');
        tile.className = 'cite-compose-photo';
        const open = document.createElement('button');
        open.type = 'button'; open.className = 'cite-media-preview-zoom';
        open.setAttribute('aria-label', `View photo ${index + 1} full size`);
        const img = document.createElement('img');
        img.src = item.url; img.alt = `Post photo ${index + 1}`;
        open.append(img);
        open.onclick = () => window.CiteMediaPostCard?.openImage(sources, 'Post photo', index);
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'cite-compose-photo-remove';
        remove.setAttribute('aria-label', `Remove photo ${index + 1}`);
        remove.title = `Remove photo ${index + 1}`;
        remove.textContent = '×';
        remove.onclick = () => {
          if (item.path) { removedImages.push(item.path); existingImages = existingImages.filter(path => path !== item.path); }
          else { selectedImages = selectedImages.filter(photo => photo !== item); URL.revokeObjectURL(item.url); }
          render();
        };
        tile.append(open, remove); imageList.append(tile);
      });
      const videoSource = selectedVideo ? videoUrl : existingVideo;
      if (videoSource) { if (video.getAttribute('src') !== videoSource) video.src = videoSource; video.classList.remove('hidden'); }
      else { video.pause(); video.removeAttribute('src'); video.classList.add('hidden'); }
      const hasMedia = photos.length > 0 || Boolean(videoSource);
      preview.classList.toggle('hidden', !hasMedia);
      imageList.classList.toggle('hidden', !photos.length);
      removeButton.classList.toggle('hidden', !hasMedia);
      fileName.textContent = photos.length ? `${photos.length} photo${photos.length === 1 ? '' : 's'} · Add more or remove individually` : videoSource ? (selectedVideo?.name || 'Current video') : '';
      form.elements.remove_media.value = removeAll ? '1' : '0';
    };
    imageInput.onchange = () => {
      const files = [...(imageInput.files || [])];
      imageInput.value = '';
      if (!files.length) return;
      const candidates = files.filter(file => !selectedImages.some(item => item.file.name === file.name && item.file.size === file.size && item.file.lastModified === file.lastModified));
      const total = selectedImages.reduce((size, item) => size + item.file.size, 0) + candidates.reduce((size, file) => size + file.size, 0);
      const message = existingImages.length + selectedImages.length + candidates.length > 30 ? 'Choose up to 30 photos per post.' : candidates.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) ? 'Choose JPEG, PNG or WebP photos.' : candidates.some(file => file.size > 5 * 1024 * 1024) ? 'Each photo must be no larger than 5 MB.' : total > 25 * 1024 * 1024 ? 'The combined photo upload may not exceed 25 MB.' : '';
      if (message) { notify('error', message); return; }
      if (!candidates.length) return;
      if (existingVideo) removeAll = true;
      releaseVideo(); existingVideo = null;
      candidates.forEach(file => selectedImages.push({file, url: URL.createObjectURL(file)}));
      render();
    };
    videoInput.onchange = () => {
      const file = videoInput.files?.[0];
      if (!file) return;
      if (file.size > 25 * 1024 * 1024 || !['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type)) {
        videoInput.value = ''; notify('error', 'Choose an MP4, WebM or QuickTime video no larger than 25 MB.'); return;
      }
      releaseImages(); existingImages = []; removedImages = []; imageInput.value = '';
      removeAll = true; releaseVideo(); selectedVideo = file; videoUrl = URL.createObjectURL(file);
      render();
    };
    removeButton.onclick = () => { releaseImages(); releaseVideo(); existingImages = []; existingVideo = null; removedImages = []; removeAll = true; render(); };
    const reset = () => { releaseImages(); releaseVideo(); existingImages = []; existingVideo = null; removedImages = []; removeAll = false; form.reset(); editingPost=null; render(); form.elements.action.value='create'; form.elements.id.value=''; host.querySelector('[data-form-title]').textContent='Create a post'; host.querySelector('[data-submit]').textContent='Post'; host.querySelector('[data-cancel-edit]').classList.add('hidden'); host.querySelector('[data-edit-context]').classList.add('hidden'); if(homeMarker.isConnected)homeMarker.after(host); host.classList.remove('col-span-full','scroll-mt-24'); };
    host.querySelector('[data-cancel-edit]').onclick=reset;
    form.onsubmit=async event => { event.preventDefault(); if(!form.reportValidity())return; if(!form.elements.content.value.trim() && !existingImages.length && !selectedImages.length && !existingVideo && !selectedVideo){notify('error','Write a post or attach a photo or video.');return;} const button=host.querySelector('[data-submit]');button.disabled=true;try{const data=new FormData(form);data.delete('images[]');data.delete('video');selectedImages.forEach(item=>data.append('images[]',item.file,item.file.name));if(selectedVideo)data.append('video',selectedVideo,selectedVideo.name);data.set('remove_image_paths',JSON.stringify(removedImages));const response=await CiteMediaApi.send(data);notify('success',response.message||'Post saved successfully.');reset();await onSaved();}catch(error){notify('error',error.response?.data?.message||'The post could not be saved.');}finally{button.disabled=false;} };
    return {edit(post, anchor){reset();editingPost=post;form.elements.action.value='update';form.elements.id.value=post.id;form.elements.content.value=post.content;form.elements.event_id.value=post.event_id||'';existingImages=[...(post.images?.length?post.images:post.image_path?[post.image_path]:[])];existingVideo=post.video_path||null;render();host.querySelector('[data-form-title]').textContent='Edit your post';host.querySelector('[data-submit]').textContent=viewer.role==='Student'?'Save and resubmit':'Save changes';host.querySelector('[data-cancel-edit]').classList.remove('hidden');host.querySelector('[data-edit-context]').classList.remove('hidden');host.querySelector('[data-edit-review-note]').textContent=viewer.role==='Student'?'Your changes will return to Pending Review and will not be public until approved.':'Your changes will be published immediately.';if(anchor?.isConnected){host.classList.toggle('col-span-full',anchor.parentElement?.hasAttribute('data-own-posts'));anchor.after(host);}host.classList.add('scroll-mt-24');host.scrollIntoView({behavior:'smooth',block:'nearest'});form.elements.content.focus({preventScroll:true});},reset};
  }
  window.CiteMediaPostForm = {mount};
})();
