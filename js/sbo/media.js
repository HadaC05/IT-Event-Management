(() => {
  'use strict';

  const API = 'api/sbo-media.php';
  const form = document.querySelector('[data-media-form]');
  const select = document.querySelector('[data-assignment]');
  const posts = document.querySelector('[data-posts]');
  const cancel = document.querySelector('[data-cancel]');
  const submitButton = document.querySelector('[data-submit]');
  let csrf = '', state = null;
  const esc = value => SboPortal.escapeHtml(value);

  function reset() {
    form.reset();
    form.elements.action.value = 'create';
    form.elements.id.value = '';
    if (state?.selected) select.value = state.selected.id;
    cancel.classList.add('hidden');
    submitButton.textContent = 'Submit post';
  }

  function render() {
    select.innerHTML = state.assignments.length
      ? state.assignments.map(assignment => `<option value="${assignment.id}">${esc(assignment.event_name)} · ${esc(assignment.activity_name)}</option>`).join('')
      : '<option value="">No media assignment</option>';
    if (state.selected) select.value = state.selected.id;
    posts.innerHTML = state.posts.length
      ? state.posts.map(post => `<article class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-sm">
          ${post.image_path ? `<img class="aspect-video w-full object-cover" src="${esc(post.image_path)}" alt="Event post">` : ''}
          <div class="p-4">
            <span class="rounded-full bg-[#F3F0E9] px-2 py-1 text-[10px] font-black uppercase text-[#397565]">${esc(post.status)}</span>
            <p class="mt-3 text-sm leading-6">${esc(post.content)}</p>
            <div class="mt-3 flex gap-2">
              <button class="text-xs font-black text-[#2F3AE0]" type="button" data-edit="${post.id}">Edit</button>
              <button class="text-xs font-black text-[#d9470a]" type="button" data-delete="${post.id}">Delete</button>
            </div>
          </div>
        </article>`).join('')
      : `<p class="rounded-2xl border border-dashed p-10 text-center text-sm text-[#121017]/45">${state.assignments.length ? 'No event posts yet.' : 'You currently have no active media assignment.'}</p>`;
  }

  async function load(assignmentId) {
    state = (await axios.get(API, {params: assignmentId ? {assignment_id: assignmentId} : {}})).data.data;
    render();
  }

  select.addEventListener('change', async () => {
    const assignmentId = Number(select.value);
    reset();
    await load(assignmentId);
  });
  cancel.addEventListener('click', reset);

  form.addEventListener('submit', async event => {
    event.preventDefault();
    submitButton.disabled = true;
    try {
      const response = await axios.post(API, new FormData(form), {headers: {'X-CSRF-Token': csrf}});
      Notifications.success(response.data.message);
      const assignmentId = Number(select.value);
      reset();
      await load(assignmentId);
    } catch (error) {
      Notifications.error(error.response?.data?.message || 'Media request failed.');
    } finally {
      submitButton.disabled = false;
    }
  });

  posts.addEventListener('click', async event => {
    const edit = event.target.closest('[data-edit]');
    const del = event.target.closest('[data-delete]');
    if (edit) {
      const post = state.posts.find(item => item.id === Number(edit.dataset.edit));
      if (!post) return;
      form.elements.action.value = 'update';
      form.elements.id.value = post.id;
      form.elements.content.value = post.content;
      cancel.classList.remove('hidden');
      submitButton.textContent = 'Save changes';
      form.scrollIntoView({behavior: 'smooth', block: 'start'});
      form.elements.content.focus({preventScroll: true});
    }
    if (del) {
      const confirmed = await Notifications.confirm({
        title: 'Delete post?',
        message: 'This event post will be removed.',
        action: 'Delete',
      });
      if (!confirmed) return;
      const data = new FormData();
      data.set('action', 'delete');
      data.set('id', del.dataset.delete);
      try {
        const response = await axios.post(API, data, {headers: {'X-CSRF-Token': csrf}});
        Notifications.success(response.data.message);
        await load(Number(select.value));
      } catch (error) {
        Notifications.error(error.response?.data?.message || 'Delete failed.');
      }
    }
  });

  SboPortal.initialize('media').then(context => {
    csrf = context.csrfToken;
    return load();
  });
})();
