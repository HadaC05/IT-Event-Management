window.SharedNavigation.ready.then(() => {
  "use strict";
  const $ = (selector, root = document) => root.querySelector(selector);
  const eventId = Number(new URLSearchParams(location.search).get("event_id"));
  let activityId = Number(new URLSearchParams(location.search).get("activity_id")) || null;
  let csrfToken = "", state, loadRequest = 0;
  const host = $("[data-configuration]"), activitySearch = $("[data-activity-search]"), activitySchedule = $("[data-activity-schedule]"), activityResults = $("[data-activity-results]");
  const escapeHtml = value => String(value ?? "").replace(/[&<>'"]/g, character => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"})[character]);
  const score = value => Number(value).toFixed(2).replace(/\.00$/, "");
  async function authenticate() {
    const response = await axios.get("api/auth.php?action=session");
    if (!response.data.authenticated || response.data.user?.role !== "SBO Adviser") { location.replace("./"); throw new Error("Unauthorized"); }
    csrfToken = response.data.csrf_token;
  }
  async function load() {
    const requestedActivity = activityId, requestId = ++loadRequest;
    const response = await axios.get("api/activity-scoring.php", {params: {event_id: eventId, ...(requestedActivity ? {activity_id: requestedActivity} : {})}});
    if (requestId !== loadRequest) return;
    state = response.data.data;
    activityId = state.selected_activity?.id || null;
    const url = new URL(location.href);
    if (activityId) url.searchParams.set("activity_id", activityId);
    else url.searchParams.delete("activity_id");
    history.replaceState(null, "", url);
    render();
  }
  function scheduleLabel(value) {
    if (!value) return "Unscheduled";
    const date = new Date(`${value}T12:00:00`);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat("en-PH", {month: "short", day: "numeric", year: "numeric"}).format(date);
  }
  function renderActivityResults() {
    const search = activitySearch.value.trim().toLocaleLowerCase();
    const matches = state.activities.filter(item => item.name.toLocaleLowerCase().includes(search) && (!activitySchedule.value || (item.schedule_date || "unscheduled") === activitySchedule.value));
    activityResults.innerHTML = matches.length ? matches.map(item => `<button class="min-h-11 rounded-xl border px-4 py-3 text-left text-xs font-black transition ${item.id === state.selected_activity.id ? "border-[#397565] bg-[#397565] text-white" : "border-[#121017]/10 bg-white text-[#121017] hover:border-[#397565]/35"}" type="button" data-activity-id="${item.id}"><span class="block">${escapeHtml(item.name)}</span><span class="mt-1 block text-[10px] opacity-70">${escapeHtml(scheduleLabel(item.schedule_date))}</span></button>`).join("") : '<p class="py-3 text-xs text-[#121017]/45">No activities match these filters.</p>';
  }
  const ruleRow = (placement = "", points = "") => `<div class="grid gap-3" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr) 90px" data-rule-row><label><span class="sr-only">Place</span><input class="h-10 w-full rounded-lg border border-[#121017]/12 px-3 text-sm font-bold" type="text" min="1" max="255" maxlength="3" inputmode="numeric" autocomplete="off" data-rule-placement value="${placement}" placeholder="Place" required></label><label><span class="sr-only">Points</span><input class="h-10 w-full rounded-lg border border-[#121017]/12 px-3 text-sm font-bold" type="text" min="0" max="255" maxlength="3" inputmode="numeric" autocomplete="off" data-rule-points value="${points}" placeholder="Points" required></label><button class="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#FF6B2C]/25 text-xs font-black text-[#D64A12]" type="button" data-remove-rule aria-label="Remove place" title="Remove place"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M9 7l1-2h4l1 2m-8 0 1 13h8l1-13"/></svg><span>Remove</span></button></div>`;
  function placementSummary() { return state.placement_rules.length ? state.placement_rules.map(rule => `${rule.placement}${rule.placement === 1 ? "st" : rule.placement === 2 ? "nd" : rule.placement === 3 ? "rd" : "th"} · ${rule.points} pt${rule.points === 1 ? "" : "s"}`).join(", ") : "No placement points configured."; }
  function render() {
    document.title = `${state.event.title} Competition Scoring | CITE Events`;
    $("[data-event-title]").textContent = state.event.title;
    $("[data-leaderboard-link]").href = `pages/adviser/leaderboard.html?event_id=${eventId}`;
    if (!state.activities.length) { activitySearch.value = ""; activitySearch.disabled = true; activitySchedule.disabled = true; activityResults.innerHTML = ""; $("[data-activity-description]").textContent = "Add an activity to this event before recording scores."; host.innerHTML = `<div class="rounded-2xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/[.06] p-6"><h2 class="font-black">A competition is required</h2><a class="mt-4 inline-flex rounded-xl bg-[#397565] px-4 py-3 text-xs font-black text-white" href="pages/adviser/event-details.html?id=${eventId}#event-activities">Manage activities</a></div>`; return; }
    activitySearch.disabled = false;
    activitySchedule.disabled = false;
    const selectedSchedule = activitySchedule.value;
    const schedules = [...new Set(state.activities.map(item => item.schedule_date || "unscheduled"))].sort((a, b) => a === "unscheduled" ? 1 : b === "unscheduled" ? -1 : a.localeCompare(b));
    activitySchedule.replaceChildren(new Option("All scheduled days", ""), ...schedules.map(value => new Option(scheduleLabel(value), value)));
    activitySchedule.value = schedules.includes(selectedSchedule) ? selectedSchedule : "";
    renderActivityResults();
    $("[data-activity-description]").textContent = state.selected_activity.description || "Scores are ranked highest to lowest when the competition is finalized.";
    if (state.finalized) { renderFinal(); return; }
    const rows = state.teams.map(team => `<tr><td class="px-4 py-3 sm:px-6"><span class="inline-block h-3 w-3 rounded-full" style="background:${escapeHtml(team.color || "#397565")}"></span><strong class="ml-3 text-sm">${escapeHtml(team.name)}</strong></td><td class="px-4 py-3 sm:px-6"><input class="h-11 w-full min-w-32 rounded-xl border border-[#121017]/12 px-3 text-right text-sm font-black focus:border-[#397565]" type="text" inputmode="numeric" maxlength="3" autocomplete="off" data-score data-team="${team.id}" value="${state.raw_scores[team.id] ?? ""}" placeholder="Raw score" aria-label="Raw score for ${escapeHtml(team.name)}"></td></tr>`).join("");
    host.innerHTML = `<div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white"><header class="flex flex-col gap-4 border-b border-[#121017]/8 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6"><h2 class="text-xl font-black">${escapeHtml(state.selected_activity.name)}</h2><button class="min-h-10 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565]" type="button" data-configure-awards>Set points</button></header><form data-score-form data-axios-form><div class="overflow-x-auto"><table class="w-full min-w-[420px]"><thead class="bg-[#F3F0E9]/55 text-left text-[10px] font-black uppercase tracking-wide text-[#121017]/40"><tr><th class="px-4 py-3 sm:px-6">Tribe</th><th class="px-4 py-3 text-right sm:px-6">Raw score</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${rows}</tbody></table></div><footer class="flex flex-col gap-3 border-t bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"><p class="text-xs text-[#121017]/45">Awards: <strong>${escapeHtml(placementSummary())}</strong></p><div class="flex gap-2"><button class="min-h-11 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565]" type="submit">Save draft</button><button class="min-h-11 rounded-xl bg-[#397565] px-5 text-xs font-black text-white" type="button" data-finalize>Finalize competition</button></div></footer></form></div>`;
    bindPlacementRules();
    bindDraft();
  }
  function renderFinal() {
    const rows = state.results.map(result => `<li class="flex items-center justify-between gap-3 border-b border-[#121017]/8 py-4 last:border-0"><div class="flex items-center gap-3"><strong class="grid h-8 w-8 place-items-center rounded-full bg-[#397565]/10 text-xs text-[#397565]">${result.placement}</strong><span><strong class="block text-sm">${escapeHtml(result.name)}</strong><small class="text-xs text-[#121017]/45">Raw score: ${score(result.raw_score)}</small></span></div><strong class="text-sm text-[#397565]">+${result.overall_points} pt${result.overall_points === 1 ? "" : "s"}</strong></li>`).join("");
    host.innerHTML = `<div class="overflow-hidden rounded-2xl border border-[#397565]/20 bg-white"><header class="border-b border-[#397565]/15 bg-[#397565]/[.06] px-5 py-5 sm:px-6"><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Finalized result</p><h2 class="mt-1 text-xl font-black">${escapeHtml(state.selected_activity.name)}</h2><p class="mt-1 text-xs text-[#121017]/50">These placement points are included in the overall tribe leaderboard.</p></header><ol class="px-5 sm:px-6">${rows}</ol></div><div class="mt-6 flex flex-wrap justify-end gap-3"><a class="inline-flex min-h-11 items-center rounded-xl border border-[#397565]/25 px-5 text-xs font-black text-[#397565]" href="pages/adviser/leaderboard.html?event_id=${eventId}&activity_id=${state.selected_activity.id}">View competition leaderboard</a><a class="inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" href="pages/adviser/leaderboard.html">View overall standings</a></div>`;
  }
  function values() { const scores = {}; document.querySelectorAll("[data-score]").forEach(input => scores[input.dataset.team] = input.value); return scores; }
  async function request(action, payload) {
    const body = {event_id: eventId, activity_id: state.selected_activity.id};
    if (payload) Object.assign(body, action === "placement-rules-save" ? payload : {scores: payload});
    return axios.post(`api/activity-scoring.php?action=${action}`, body, {headers:{"X-CSRF-Token": csrfToken}});
  }
  function bindPlacementRules() {
    const form = $("[data-placement-rules]");
    const dialog = $("[data-awards-dialog]");
    $("[data-rule-list]", form).innerHTML = state.placement_rules.length ? state.placement_rules.map(rule => ruleRow(rule.placement, rule.points)).join("") : ruleRow(1, "");
    const validateRuleInput = input => {
      input.value = input.value.replace(/\D/g, "");
      const value = Number(input.value), min = Number(input.min), max = Number(input.max);
      input.setCustomValidity(input.value && value >= min && value <= max ? "" : `Enter a whole number from ${min} to ${max}.`);
    };
    form.querySelectorAll("[data-rule-placement], [data-rule-points]").forEach(validateRuleInput);
    form.onbeforeinput = event => { if (event.target.matches("[data-rule-placement], [data-rule-points]") && event.data && !/^\d+$/.test(event.data)) event.preventDefault(); };
    form.oninput = event => { if (event.target.matches("[data-rule-placement], [data-rule-points]")) validateRuleInput(event.target); };
    $("[data-configure-awards]").onclick = () => dialog.showModal();
    document.querySelectorAll("[data-awards-close]").forEach(button => button.onclick = () => dialog.close());
    form.onclick = event => { if (event.target.closest("[data-add-rule]")) { const list=$("[data-rule-list]",form); const places=[...form.querySelectorAll("[data-rule-placement]")].map(input=>Number(input.value)||0); list.insertAdjacentHTML("beforeend",ruleRow(Math.max(0,...places)+1,"")); } const remove=event.target.closest("[data-remove-rule]"); if(remove && form.querySelectorAll("[data-rule-row]").length>1) remove.closest("[data-rule-row]").remove(); };
    form.onsubmit = async event => { event.preventDefault(); if(!form.reportValidity())return; const placement_rules={}; let duplicate=false; form.querySelectorAll("[data-rule-row]").forEach(row=>{const place=$("[data-rule-placement]",row).value; if(placement_rules[place] !== undefined)duplicate=true; placement_rules[place]=$("[data-rule-points]",row).value;}); if(duplicate){window.Notifications?.warning?.("Each place can be configured only once.");return;} try{const response=await request("placement-rules-save",{placement_rules});dialog.close();window.Notifications?.success?.(response.data.message);await load();}catch(error){window.Notifications?.error?.(error.response?.data?.message||"Placement points could not be saved.");} };
  }
  function bindDraft() {
    const form = $("[data-score-form]"), finalize = $("[data-finalize]");
    const validateScore = input => {
      input.value = input.value.replace(/\D/g, "");
      input.setCustomValidity(input.value && Number(input.value) > 200 ? "Raw scores cannot exceed 200." : "");
    };
    form.querySelectorAll("[data-score]").forEach(validateScore);
    form.addEventListener("beforeinput", event => { if (event.target.matches("[data-score]") && event.data && !/^\d+$/.test(event.data)) event.preventDefault(); });
    form.addEventListener("input", event => { if (event.target.matches("[data-score]")) validateScore(event.target); });
    form.onsubmit = async event => { event.preventDefault(); if (!form.reportValidity()) return; try { const response=await request("save",values()); window.Notifications?.success?.(response.data.message); await load(); } catch(error) { window.Notifications?.error?.(error.response?.data?.message || "Scores could not be saved."); } };
    finalize.onclick = async () => { if (!form.reportValidity()) return; const incomplete=Object.values(values()).some(value => value === ""); if(incomplete){window.Notifications?.warning?.("Enter a raw score for every eligible tribe before finalizing.");return;} if(!state.placement_rules.length){window.Notifications?.warning?.("Save at least one placement rule before finalizing.");return;} const accepted=window.Notifications?.confirm ? await window.Notifications.confirm({title:"Finalize this competition?",message:"Configured placement points will be added to the overall tribe standings.",action:"Finalize competition"}) : confirm("Finalize this competition?"); if(!accepted)return; try { await request("save",values()); const response=await request("finalize"); window.Notifications?.success?.(response.data.message); await load(); } catch(error) { window.Notifications?.error?.(error.response?.data?.message || "Competition could not be finalized."); } };
  }
  activitySearch.oninput = renderActivityResults;
  activitySchedule.onchange = renderActivityResults;
  activityResults.onclick = event => { const button = event.target.closest("[data-activity-id]"); if (!button) return; activityId = Number(button.dataset.activityId); activitySearch.value = ""; load(); };
  if (!eventId) { location.replace("pages/adviser/scores.html"); return; }
  authenticate().then(load).catch(error => { if(error.message!=="Unauthorized") host.innerHTML='<p class="py-10 text-center text-red-600">Competition scores could not be loaded.</p>'; });
});
