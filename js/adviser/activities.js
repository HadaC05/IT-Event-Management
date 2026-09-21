document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  const API = 'api/adviser-activities.php';
  const filters = document.querySelector('[data-activity-filters]'), rows = document.querySelector('[data-activity-rows]'), count = document.querySelector('[data-activity-count]'), pagination = document.querySelector('[data-activity-pagination]'), clear = document.querySelector('[data-clear-activity-filters]'), dialog = document.querySelector('[data-activity-dialog]'), form = document.querySelector('[data-activity-form]'), save = document.querySelector('[data-save-activity]');
  let csrfToken = '', page = 1, eventTypes = [], activities = [], searchDelay;
  const escapeHtml = value => window.SharedNavigation?.escapeHtml(value) || String(value ?? '');
  const notify = (type, message) => window.Notifications?.show(type, message) || alert(message);
  const options = (selected = '') => '<option value="">All event types</option>' + eventTypes.map(type => `<option value="${type.id}" ${String(type.id) === String(selected) ? 'selected' : ''}>${escapeHtml(type.label)}</option>`).join('');
  const render = data => {
    activities = data.activities || []; eventTypes = data.event_types || eventTypes;
    const selected = filters.elements.event_type_id.value || filters.dataset.eventTypeId || '';
    filters.elements.event_type_id.innerHTML = options(selected); delete filters.dataset.eventTypeId;
    form.elements.event_type_id.innerHTML = '<option value="">Select event type</option>' + eventTypes.map(type => `<option value="${type.id}">${escapeHtml(type.label)}</option>`).join('');
    count.textContent = `${data.pagination.total} ${data.pagination.total === 1 ? 'activity' : 'activities'} found`;
    clear.classList.toggle('hidden', !filters.elements.search.value && !filters.elements.event_type_id.value);
    rows.innerHTML = activities.length ? activities.map(activity => `<tr class="hover:bg-[#397565]/[.025]"><td class="px-5 py-4"><strong class="block text-sm">${escapeHtml(activity.label)}</strong>${activity.description ? `<small class="mt-1 block max-w-xl text-xs leading-5 text-[#121017]/50">${escapeHtml(activity.description)}</small>` : '<small class="mt-1 block text-xs text-slate-400">No description</small>'}</td><td class="px-4 py-4"><span class="rounded-full bg-[#397565]/10 px-2.5 py-1 text-[10px] font-black uppercase text-[#397565]">${escapeHtml(activity.event_type)}</span></td><td class="px-5 py-4 text-right"><button class="min-h-10 rounded-xl border border-[#397565]/20 bg-[#397565]/5 px-4 text-xs font-black text-[#397565]" type="button" data-edit-activity="${activity.id}">Edit</button></td></tr>`).join('') : `<tr><td class="px-5 py-12 text-center text-sm text-[#121017]/45" colspan="3">${filters.elements.search.value || filters.elements.event_type_id.value ? 'No activities match the selected filters.' : 'No activities have been created yet.'}</td></tr>`;
    const p = data.pagination; pagination.classList.toggle('hidden', p.last_page <= 1);
    pagination.innerHTML = `<span class="text-slate-400">Showing ${p.from}-${p.to} of ${p.total}</span><div class="flex gap-2"><button class="min-h-10 rounded-xl border border-slate-200 px-4 font-bold disabled:text-slate-300" type="button" data-activity-page="${p.current_page - 1}" ${p.current_page <= 1 ? 'disabled' : ''}>Previous</button><button class="min-h-10 rounded-xl border border-slate-200 px-4 font-bold disabled:text-slate-300" type="button" data-activity-page="${p.current_page + 1}" ${p.current_page >= p.last_page ? 'disabled' : ''}>Next</button></div>`;
  };
  const load = async () => {
    const params = Object.fromEntries(new FormData(filters)); params.page = page;
    const response = await axios.get(API, { params }); render(response.data.data);
    const url = new URL(location.href); ['search', 'event_type_id'].forEach(key => params[key] ? url.searchParams.set(key, params[key]) : url.searchParams.delete(key)); page > 1 ? url.searchParams.set('page', page) : url.searchParams.delete('page'); history.replaceState(null, '', url);
  };
  const refresh = () => load().catch(error => notify('error', error.response?.data?.message || 'Unable to filter activities.'));
  try {
    const nav = await window.SharedNavigation.ready; csrfToken = nav.csrfToken;
    const query = new URLSearchParams(location.search); filters.elements.search.value = query.get('search') || ''; filters.dataset.eventTypeId = query.get('event_type_id') || ''; page = Number(query.get('page')) || 1; await load();
  } catch (error) { notify('error', error.response?.data?.message || 'Unable to load activities.'); }
  finally { window.SharedNavigation?.finishPageLoad(); }
  const openCreate = () => {
    form.reset(); form.elements.action.value = 'create'; form.elements.id.value = ''; form.elements.event_type_id.disabled = false;
    document.querySelector('[data-event-type-locked]').classList.add('hidden'); document.querySelector('[data-activity-dialog-title]').textContent = 'Add Activity'; document.querySelector('[data-activity-dialog-description]').textContent = 'This activity will be available under its event type.'; save.textContent = 'Create Activity'; dialog.showModal(); form.elements.event_type_id.focus();
  };
  const openEdit = activity => {
    form.reset(); form.elements.action.value = 'update'; form.elements.id.value = activity.id; form.elements.event_type_id.value = activity.event_type_id; form.elements.event_type_id.disabled = true; form.elements.label.value = activity.label; form.elements.description.value = activity.description || '';
    document.querySelector('[data-event-type-locked]').classList.remove('hidden'); document.querySelector('[data-activity-dialog-title]').textContent = 'Edit Activity'; document.querySelector('[data-activity-dialog-description]').textContent = 'Update the activity name or description.'; save.textContent = 'Save Changes'; dialog.showModal(); form.elements.label.focus();
  };
  document.querySelector('[data-open-activity]').onclick = openCreate;
  document.querySelectorAll('[data-close-activity]').forEach(button => button.onclick = () => dialog.close());
  filters.onsubmit = event => event.preventDefault();
  filters.elements.search.oninput = () => { clearTimeout(searchDelay); searchDelay = setTimeout(() => { page = 1; refresh(); }, 300); };
  filters.elements.event_type_id.onchange = () => { page = 1; refresh(); };
  clear.onclick = () => { filters.reset(); page = 1; refresh(); };
  pagination.onclick = event => { const button = event.target.closest('[data-activity-page]'); if (!button || button.disabled) return; page = Number(button.dataset.activityPage); refresh(); };
  rows.onclick = event => { const button = event.target.closest('[data-edit-activity]'); if (!button) return; const activity = activities.find(item => item.id === Number(button.dataset.editActivity)); if (activity) openEdit(activity); };
  form.onsubmit = async event => {
    event.preventDefault(); if (!form.reportValidity()) return;
    const editing = form.elements.action.value === 'update'; save.disabled = true; save.textContent = editing ? 'Saving...' : 'Creating...';
    try { const response = await axios.post(API, Object.fromEntries(new FormData(form)), { headers: { 'X-CSRF-Token': csrfToken } }); dialog.close(); notify('success', response.data.message); location.reload(); }
    catch (error) { notify('error', error.response?.data?.message || 'Unable to save activity details.'); }
    finally { save.disabled = false; save.textContent = editing ? 'Save Changes' : 'Create Activity'; }
  };
});
