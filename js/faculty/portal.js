(() => {
  'use strict';
  const page = document.body.dataset.facultyPage;
  const host = document.querySelector('[data-faculty-content]');
  const esc = value => window.SharedNavigation.escapeHtml(value);
  const card = (body, extra='') => `<section class="rounded-3xl border border-[#397565]/15 bg-white p-5 shadow-sm sm:p-6 ${extra}">${body}</section>`;
  const empty = message => `<section class="faculty-panel faculty-empty"><span class="faculty-empty__mark" aria-hidden="true">C</span><p class="faculty-section-kicker">Your faculty workspace</p><h2>Waiting for a team</h2><p>${esc(message)}</p></section>`;
  const date = value => value ? new Date(value.replace(' ','T')).toLocaleString('en-PH',{dateStyle:'medium',timeStyle:'short'}) : '—';
  const status = value => {const label=String(value||'Unrecorded');const state=['present','late','absent','excused'].includes(label.toLowerCase())?label.toLowerCase():'unrecorded';return `<span class="faculty-status faculty-status--${state}">${esc(label)}</span>`;};
  const formatNumber = value => Number(value || 0).toLocaleString('en-PH', {maximumFractionDigits: 2});
  const nameInitials = name => {const words=String(name||'').trim().split(/\s+/).filter(Boolean);return (words.length>1?`${words[0][0]}${words[words.length-1][0]}`:(words[0]||'CU').slice(0,2)).toUpperCase();};
  const safeAccent = value => /^#[0-9a-f]{6}$/i.test(String(value||'')) ? value : '#397565';
  const get = async (name=page, params={}) => (await axios.get('api/faculty.php',{params:{page:name,...params}})).data.data;
  let csrf = '';

  async function students() {
    const first=await get('students',{page_number:1});
    if (!first.team) { host.innerHTML=empty('No team is assigned to your Faculty account. Contact the SBO Adviser.'); return; }
    host.innerHTML=`<section class="faculty-panel"><div class="faculty-roster-intro"><div><p class="faculty-section-kicker">Your assigned team</p><h2>${esc(first.team.name)}</h2><p class="mt-2 text-xs text-[#121017]/50">View-only roster · Open a student to load attendance history.</p></div><div class="faculty-roster-count" data-roster-count>${formatNumber(first.pagination.total)}<span>Students</span></div></div><div class="faculty-roster-filters"><label class="faculty-field">Find a student<input placeholder="Name or Student ID" data-search autocomplete="off"></label><label class="faculty-field">Event<select data-event><option value="">All events</option>${Object.entries(first.events).map(([id,title])=>`<option value="${esc(id)}">${esc(title)}</option>`).join('')}</select></label><label class="faculty-field">Attendance<select data-status><option value="">All statuses</option>${['present','late','absent','excused','unrecorded'].map(s=>`<option value="${s}">${s[0].toUpperCase()+s.slice(1)}</option>`).join('')}</select></label></div><div class="faculty-roster-list" data-list></div><div class="faculty-roster-pagination hidden" data-pagination></div></section>`;
    const search=host.querySelector('[data-search]'),eventFilter=host.querySelector('[data-event]'),statusFilter=host.querySelector('[data-status]'),list=host.querySelector('[data-list]'),pager=host.querySelector('[data-pagination]');
    let currentPage=1,request=0,searchTimer;
    const historyRecord=a=>`<div class="faculty-history-record"><div class="flex flex-wrap items-center justify-between gap-2"><strong>${esc(a.event_title)}</strong>${status(a.status)}</div><p>${esc(a.date)}${a.manual_status?' · Adviser corrected':''}</p><p>Morning: ${esc(a.morning_in_at||'—')} – ${esc(a.morning_out_at||'—')}<br>Afternoon: ${esc(a.afternoon_in_at||'—')} – ${esc(a.afternoon_out_at||'—')}</p></div>`;
    const loadHistory=async(details,studentId,pageNumber=1)=>{
      const target=details.querySelector('[data-history]');target.innerHTML='<p class="text-sm text-[#121017]/50">Loading attendance…</p>';
      try{
        const data=await get('student_history',{student_id:studentId,event_id:eventFilter.value,page_number:pageNumber});
        target.innerHTML=data.records.length?data.records.map(historyRecord).join(''):'<p class="text-sm text-[#121017]/50">No attendance recorded.</p>';
        if(data.pagination.last_page>1){const controls=document.createElement('div');controls.className='flex flex-wrap items-center justify-between gap-2 pt-2 text-xs text-[#121017]/50';controls.innerHTML=`<span>History page ${data.pagination.current_page} of ${data.pagination.last_page}</span><span class="flex gap-2"><button type="button" data-history-page="${data.pagination.current_page-1}" ${data.pagination.current_page===1?'disabled':''}>Previous</button><button type="button" data-history-page="${data.pagination.current_page+1}" ${data.pagination.current_page===data.pagination.last_page?'disabled':''}>Next</button></span>`;controls.querySelectorAll('[data-history-page]').forEach(button=>button.onclick=()=>loadHistory(details,studentId,Number(button.dataset.historyPage)));target.append(controls);}
      }catch(error){target.innerHTML=`<p class="text-sm font-bold text-[#b94312]">${esc(error.response?.data?.message||'Attendance history could not be loaded.')}</p>`;}
    };
    const renderPager=pageData=>{
      const visible=pageData.total>0;pager.classList.toggle('hidden',!visible);pager.classList.toggle('flex',visible);pager.replaceChildren();if(!visible)return;
      const count=document.createElement('span');count.textContent=`Showing ${pageData.from.toLocaleString()}–${pageData.to.toLocaleString()} of ${pageData.total.toLocaleString()} students`;
      const controls=document.createElement('span');controls.className='flex flex-wrap items-center gap-2';const position=document.createElement('span');position.textContent=`${pageData.current_page} / ${pageData.last_page}`;
      const button=(label,target,disabled)=>{const control=document.createElement('button');control.type='button';control.textContent=label;control.disabled=disabled;control.onclick=()=>load(target);return control;};
      controls.append(button('Previous',pageData.current_page-1,pageData.current_page===1),position,button('Next',pageData.current_page+1,pageData.current_page===pageData.last_page));pager.append(count,controls);
    };
    const render=data=>{
      host.querySelector('[data-roster-count]').innerHTML=`${formatNumber(data.pagination.total)}<span>Students</span>`;
      list.innerHTML=data.students.length?data.students.map(student=>`<details class="faculty-student-row" data-student-id="${student.id}"><summary><span class="faculty-student-avatar" aria-hidden="true">${esc(nameInitials(student.name))}</span><span class="faculty-student-identity"><strong>${esc(student.name)}</strong><small>${esc(student.id_number)} · ${esc(student.year_level||'Year not set')}</small></span><span class="faculty-student-records">${formatNumber(student.attendance_count)} records</span><span class="faculty-student-chevron" aria-hidden="true">›</span></summary><div class="faculty-student-history" data-history><p class="text-sm text-[#121017]/50">Loading attendance history…</p></div></details>`).join(''):'<p class="py-8 text-center text-sm text-[#121017]/50">No students match these filters. Try a different name, event, or status.</p>';
      list.querySelectorAll('details').forEach(details=>details.addEventListener('toggle',()=>{if(details.open&&!details.dataset.loaded){details.dataset.loaded='true';loadHistory(details,Number(details.dataset.studentId));}}));renderPager(data.pagination);
    };
    const load=async(targetPage=1)=>{const serial=++request;const data=await get('students',{search:search.value.trim(),event_id:eventFilter.value,status:statusFilter.value,page_number:targetPage});if(serial!==request)return;currentPage=data.pagination.current_page;render(data);};
    search.oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>load(1),300);};eventFilter.onchange=()=>load(1);statusFilter.onchange=()=>load(1);currentPage=first.pagination.current_page;render(first);
  }

  async function leaderboard() {
    const first=await get();
    const now=new Date();
    const active=first.events.filter(event=>new Date(event.end_at.replace(' ','T'))>=now);
    const old=first.events.filter(event=>new Date(event.end_at.replace(' ','T'))<now);
    let selected=active[0]?.id || old[0]?.id || null;
    let category='';
    let past=!active.length && old.length>0;
    let request=0;
    const eventButton=event=>`<button type="button" data-event="${event.id}" aria-pressed="${selected===event.id}" title="${esc(event.title)}">${esc(event.title)}</button>`;
    const render=async()=>{
      const serial=++request;
      const data=await get('leaderboard',selected?{event_id:selected,...(category?{category_id:category}:{})}:{});
      if(serial!==request)return;
      const eventName=first.events.find(event=>event.id===selected)?.title || 'All events';
      const leader=data.rankings[0]||null;
      const rankingRows=data.rankings.map(team=>`<li style="border-left:3px solid ${safeAccent(team.color)};padding-left:.7rem"><span class="faculty-ranking-position">${team.rank||'—'}</span><span class="faculty-ranking-name"><strong>${esc(team.name)}${team.id===data.team_id?' · Your team':''}</strong><small>${formatNumber(team.members_count)} members</small></span><span class="faculty-ranking-points">${formatNumber(team.total_score)} pts</span></li>`).join('');
      host.innerHTML=`<div class="faculty-panel faculty-board-filters"><div><p class="faculty-section-kicker">Choose an event</p><div class="faculty-event-pills" role="group" aria-label="Scored events">${active.map(eventButton).join('')}<button type="button" data-past aria-pressed="${past}">Past events ${old.length?'('+old.length+')':''}</button></div>${past?`<div class="faculty-event-pills" role="group" aria-label="Past scored events">${old.map(eventButton).join('')||'<p class="text-sm text-[#121017]/50">No past events yet.</p>'}</div>`:''}</div><div class="faculty-board-filter-row"><p class="text-sm text-[#121017]/55">Showing finalized scores for <strong>${esc(eventName)}</strong>.</p><label class="faculty-field">Scoring category<select data-category><option value="">All categories</option>${data.categories.map(item=>`<option value="${item.id}" ${category==item.id?'selected':''}>${esc(item.name)}</option>`).join('')}</select></label></div></div><section class="faculty-board-highlight mt-5" aria-label="Current leader"><div><p class="faculty-section-kicker">Front of the field</p><h2>${leader?esc(leader.name):'The field is open'}</h2><p>${leader?`${esc(eventName)} · ${formatNumber(data.rankings.length)} ranked teams`:'Finalized scores will appear here once they are recorded.'}</p></div><div class="faculty-board-score">${leader?formatNumber(leader.total_score):'—'}<small>${leader?'Points':'Awaiting scores'}</small></div></section><div class="faculty-board-layout"><section class="faculty-panel"><div class="faculty-panel__head"><p class="faculty-section-kicker">The standings</p><h2>Team rankings</h2><p>Only finalized scores count toward these positions.</p></div>${rankingRows?`<ol class="faculty-ranking-list">${rankingRows}</ol>`:'<p class="px-6 py-10 text-sm text-[#121017]/55">No scores are available for this event and category yet.</p>'}</section><aside class="faculty-panel"><div class="faculty-panel__head"><p class="faculty-section-kicker">At a glance</p><h2>Scoreboard notes</h2></div><div class="px-6 pb-5 pt-2"><div class="faculty-board-stat"><span>Ranked teams</span><strong>${formatNumber(data.summary?.ranked_teams)}</strong></div><div class="faculty-board-stat"><span>Finalized points</span><strong>${formatNumber(data.summary?.points)}</strong></div><div class="faculty-board-stat"><span>Your team</span><strong>${esc(data.rankings.find(team=>team.id===data.team_id)?.name||'Not ranked yet')}</strong></div></div></aside></div>`;
      host.querySelectorAll('[data-event]').forEach(button=>button.onclick=()=>{selected=Number(button.dataset.event);category='';past=old.some(event=>event.id===selected);render();});
      host.querySelector('[data-past]').onclick=()=>{past=!past;render();};
      host.querySelector('[data-category]').onchange=event=>{category=event.target.value;render();};
    };
    await render();
  }

  async function team() {
    const {team:t}=await get();
    if (!t) {host.innerHTML=empty('No team is assigned to your Faculty account. Contact the SBO Adviser.');return;}
    const accent=/^#[0-9a-f]{6}$/i.test(String(t.color||''))?t.color:'#397565';
    const number=value=>Number(value||0).toLocaleString('en-PH',{maximumFractionDigits:2});
    const initials=name=>{const words=String(name||'').trim().split(/\s+/).filter(Boolean);return (words.length>1?`${words[0][0]}${words[words.length-1][0]}`:(words[0]||'TM').slice(0,2)).toUpperCase();};
    const attendance=Object.fromEntries((t.attendance||[]).map(item=>[String(item.status||'').toLowerCase(),Number(item.total||0)]));
    const nextActivity=t.activities?.[0]||null;
    const memberRows=(t.member_preview||[]).map(member=>`<li class="flex items-center gap-3 border-b border-[#121017]/8 py-3 last:border-0"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-black" style="background:${accent}18;color:${accent}" aria-hidden="true">${esc(initials(member.name))}</span><span class="min-w-0 flex-1"><strong class="block truncate text-sm">${esc(member.name)}</strong><span class="mt-0.5 block truncate text-xs text-[#121017]/50">${esc(member.id_number)} · ${esc(member.year_level||'Year not set')}</span></span></li>`).join('');
    const scoreRows=(t.scores||[]).slice(0,3).map(score=>`<li class="flex items-start justify-between gap-4 border-b border-[#121017]/8 py-3 last:border-0"><span class="min-w-0"><strong class="block truncate text-sm">${esc(score.event_title)}</strong><span class="text-xs text-[#121017]/50">${esc(score.category)}</span></span><strong class="shrink-0 text-sm">${number(score.points)} pts</strong></li>`).join('');
    const announcementRows=(t.announcements||[]).map(item=>`<li class="grid gap-1 border-b border-[#121017]/8 py-3 last:border-0 sm:flex sm:items-start sm:justify-between sm:gap-5"><p class="break-words text-sm leading-6">${esc(item.content)}</p><span class="shrink-0 text-xs text-[#121017]/45">${item.event_title?`${esc(item.event_title)} · `:''}${date(item.created_at)}</span></li>`).join('');
    host.innerHTML=`
      <section class="relative overflow-hidden rounded-3xl border border-[#121017]/10 bg-white p-6 shadow-sm sm:p-8" style="border-top:6px solid ${accent};background:linear-gradient(135deg,${accent}16 0%,#fff 52%)">
        <span class="pointer-events-none absolute -right-4 -top-10 select-none font-black leading-none" style="font-size:clamp(7rem,18vw,12rem);opacity:.055" aria-hidden="true">${esc(initials(t.name))}</span>
        <div class="relative max-w-3xl">
          <p class="text-[10px] font-black uppercase tracking-wider" style="color:${accent}">Your assigned team · ${esc(t.school_year)}</p>
          <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">${esc(t.name)}</h2>
          <p class="mt-2 text-sm text-[#121017]/55">Faculty team overview for the current academic year.</p>
        </div>
        <dl class="relative mt-7 grid grid-cols-2 gap-x-5 gap-y-5 border-t border-[#121017]/10 pt-6 sm:grid-cols-4">
          <div><dt class="text-[10px] font-black uppercase tracking-wider text-[#121017]/45">Members</dt><dd class="mt-1 text-2xl font-black">${number(t.members_count)}</dd></div>
          <div><dt class="text-[10px] font-black uppercase tracking-wider text-[#121017]/45">Current rank</dt><dd class="mt-1 text-2xl font-black">${t.rank?`#${number(t.rank)}`:'Unranked'}</dd></div>
          <div><dt class="text-[10px] font-black uppercase tracking-wider text-[#121017]/45">Finalized points</dt><dd class="mt-1 text-2xl font-black">${number(t.total_score)}</dd></div>
          <div><dt class="text-[10px] font-black uppercase tracking-wider text-[#121017]/45">Attendance records</dt><dd class="mt-1 text-2xl font-black">${number(t.attendance_total)}</dd></div>
        </dl>
      </section>

      <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <section class="rounded-3xl border border-[#397565]/15 bg-white p-5 shadow-sm sm:p-6">
          <div class="flex items-center justify-between gap-4">
            <div><p class="text-[10px] font-black uppercase tracking-wider text-[#397565]">Team roster</p><h2 class="mt-1 text-xl font-black">Members</h2></div>
            <a class="rounded-xl border border-[#397565]/25 px-3 py-2 text-xs font-black text-[#397565] transition hover:bg-[#397565]/10" href="pages/faculty/students.html">View all ${number(t.members_count)}</a>
          </div>
          <ul class="mt-4">${memberRows||'<li class="py-8 text-center text-sm text-[#121017]/50">No members are assigned yet.</li>'}</ul>
        </section>

        <section class="rounded-3xl border border-[#397565]/15 bg-white p-5 shadow-sm sm:p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-[#397565]">At a glance</p><h2 class="mt-1 text-xl font-black">Team performance</h2>
          <div class="mt-5">
            <div class="flex items-center justify-between"><h3 class="text-sm font-black">Recorded attendance</h3><span class="text-xs text-[#121017]/45">${esc(t.school_year)}</span></div>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
              <div class="flex justify-between gap-2"><dt class="text-[#121017]/55">Present</dt><dd class="font-black">${number(attendance.present)}</dd></div>
              <div class="flex justify-between gap-2"><dt class="text-[#121017]/55">Late</dt><dd class="font-black">${number(attendance.late)}</dd></div>
              <div class="flex justify-between gap-2"><dt class="text-[#121017]/55">Absent</dt><dd class="font-black">${number(attendance.absent)}</dd></div>
              <div class="flex justify-between gap-2"><dt class="text-[#121017]/55">Excused</dt><dd class="font-black">${number(attendance.excused)}</dd></div>
            </dl>
          </div>
          <div class="mt-5 border-t border-[#121017]/10 pt-5">
            <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-black">Latest finalized scores</h3><a class="text-xs font-black text-[#397565]" href="pages/faculty/leaderboard.html">Leaderboard →</a></div>
            <ul class="mt-2">${scoreRows||'<li class="py-4 text-sm text-[#121017]/50">No finalized scores yet.</li>'}</ul>
          </div>
          <div class="mt-5 border-t border-[#121017]/10 pt-5">
            <h3 class="text-sm font-black">Next event</h3>
            ${nextActivity?`<p class="mt-2 font-black">${esc(nextActivity.title)}</p><p class="mt-1 text-xs leading-5 text-[#121017]/50">${date(nextActivity.start_at)}${nextActivity.location?` · ${esc(nextActivity.location)}`:''}</p>`:'<p class="mt-2 text-sm text-[#121017]/50">No upcoming team events.</p>'}
          </div>
        </section>
      </div>

      <section class="mt-5 rounded-3xl border border-[#397565]/15 bg-white p-5 shadow-sm sm:p-6">
        <div><p class="text-[10px] font-black uppercase tracking-wider text-[#397565]">What is happening</p><h2 class="mt-1 text-xl font-black">Team updates</h2></div>
        <ul class="mt-3">${announcementRows||'<li class="py-6 text-sm text-[#121017]/50">No team announcements have been published yet.</li>'}</ul>
      </section>`;
  }

  async function attendance() {
    const data=await get();
    if(!data.team){host.innerHTML=empty('No team is assigned to your Faculty account. Contact the SBO Adviser before scanning attendance.');return;}
    host.innerHTML=card(`<h2 class="font-black">Scan student attendance</h2><p class="mt-1 text-sm text-[#121017]/55">Only Student QR codes from ${esc(data.team.name)} can be recorded. Choose the event session and Time In or Time Out.</p><label class="mt-4 grid gap-1 text-sm font-bold">Event session<select class="min-h-11 rounded-xl border bg-white px-3" data-scan-session></select></label><div class="mt-4 flex gap-2"><button class="min-h-11 rounded-xl border px-4 text-sm font-bold" type="button" data-phase="in">Time In</button><button class="min-h-11 rounded-xl border px-4 text-sm font-bold" type="button" data-phase="out">Time Out</button></div><p class="mt-3 text-sm text-[#121017]/55" data-scan-window></p><button class="mt-4 min-h-11 rounded-xl bg-[#397565] px-5 text-sm font-bold text-white disabled:opacity-40" type="button" data-camera-button>Open QR scanner</button><div class="mt-4 hidden max-w-lg overflow-hidden rounded-xl bg-[#121017]" data-camera-panel><video class="aspect-square w-full object-cover" playsinline muted data-camera-video></video></div><p class="mt-3 text-sm" data-scan-message aria-live="polite">Ready to scan.</p>`)+card('<h2 class="font-black">Your team scans</h2><div class="mt-3 grid gap-2" data-scan-history></div>','mt-4');
    let sessions=data.sessions||[],phase='in',scanner=null,qrModule=null,working=false,lastToken='',lastAt=0;
    const select=host.querySelector('[data-scan-session]'),video=host.querySelector('[data-camera-video]'),cameraButton=host.querySelector('[data-camera-button]'),panel=host.querySelector('[data-camera-panel]'),message=host.querySelector('[data-scan-message]');
    const selected=()=>sessions.find(s=>`${s.event_schedule_id}:${s.session_code}`===select.value);
    const history=records=>{host.querySelector('[data-scan-history]').innerHTML=records.length?records.map(r=>`<div class="rounded-xl border border-[#121017]/10 p-3 text-sm"><strong>${esc(r.student_name)}</strong> · ${esc(r.id_number)}<p class="mt-1 text-xs text-[#121017]/55">${esc(r.event_title)} · ${esc(r.phase==='out'?'Time Out':'Time In')} · ${date(r.scanned_at)}</p></div>`).join(''):'<p class="text-sm text-[#121017]/50">No students scanned yet.</p>';};
    const stop=()=>{if(scanner){scanner.destroy();scanner=null;}panel.classList.add('hidden');cameraButton.textContent='Open QR scanner';};
    const controls=()=>{const s=selected();host.querySelectorAll('[data-phase]').forEach(b=>{const active=b.dataset.phase===phase;b.classList.toggle('bg-[#397565]',active);b.classList.toggle('text-white',active);b.classList.toggle('bg-white',!active);});const open=Boolean(s?.[`${phase}_window_open`]);cameraButton.disabled=!open;host.querySelector('[data-scan-window]').textContent=!s?'No attendance session is enabled for your team today.':open?`Time ${phase==='in'?'In':'Out'} scanning is open for ${s.event_name}.`:`Time ${phase==='in'?'In':'Out'} scanning is closed for ${s.event_name}.`;if(!open)stop();};
    const refresh=async()=>{const updated=await get('attendance');sessions=updated.sessions||[];const value=select.value;select.innerHTML=sessions.map(s=>`<option value="${s.event_schedule_id}:${s.session_code}">${esc(s.event_name)} · ${esc(s.session_name)}</option>`).join('');if(sessions.some(s=>`${s.event_schedule_id}:${s.session_code}`===value))select.value=value;history(updated.records||[]);controls();};
    const location=()=>new Promise(resolve=>{if(!navigator.geolocation){resolve({unavailable_reason:'geolocation_unavailable'});return;}navigator.geolocation.getCurrentPosition(p=>resolve({latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy_m:p.coords.accuracy,timestamp_ms:p.timestamp}),e=>resolve({unavailable_reason:e.code===1?'permission_denied':e.code===3?'location_timeout':'location_unavailable'}),{enableHighAccuracy:true,maximumAge:5000,timeout:8000});});
    const scan=async result=>{const token=String(result?.data||'').trim(),s=selected();if(!token||!s||working||!s[`${phase}_window_open`]||(token===lastToken&&Date.now()-lastAt<30000))return;working=true;lastToken=token;lastAt=Date.now();message.textContent='Checking location and recording attendance…';try{const response=await axios.post('api/faculty.php?page=scan',{schedule_id:s.event_schedule_id,session:s.session_code,checkpoint:phase,mode:'qr',token,location:await location()},{headers:{'X-CSRF-Token':csrf}});const student=response.data.data.student;message.textContent=`${phase==='in'?'Time In':'Time Out'} recorded for ${student.full_name} (${student.id_number}).`;message.className='mt-3 text-sm font-bold text-[#397565]';await refresh();}catch(e){message.textContent=e.response?.data?.message||'Attendance scan failed.';message.className='mt-3 text-sm font-bold text-[#D64A12]';lastAt=Date.now()-29000;}finally{working=false;}};
    cameraButton.onclick=async()=>{if(scanner){stop();return;}if(!window.isSecureContext){message.textContent='Camera scanning requires HTTPS or localhost.';return;}try{qrModule??=(await import(new URL('js/vendor/qr-scanner/qr-scanner.min.js',document.baseURI))).default;scanner=new qrModule(video,scan,{preferredCamera:'environment',maxScansPerSecond:10,highlightScanRegion:true,highlightCodeOutline:true,returnDetailedScanResult:true});panel.classList.remove('hidden');await scanner.start();cameraButton.textContent='Close scanner';message.textContent='Point the camera at a student QR code.';}catch(e){stop();message.textContent=e?.message||'Camera is unavailable. Check permission and retry.';}};
    host.querySelectorAll('[data-phase]').forEach(b=>b.onclick=()=>{phase=b.dataset.phase;controls();});select.onchange=()=>{stop();controls();};history(data.records||[]);if(sessions.some(s=>s.in_window_open))phase='in';else if(sessions.some(s=>s.out_window_open))phase='out';select.innerHTML=sessions.map(s=>`<option value="${s.event_schedule_id}:${s.session_code}">${esc(s.event_name)} · ${esc(s.session_name)}</option>`).join('');controls();
    const timer=setInterval(()=>{if(!document.hidden&&!working)refresh().catch(()=>{});},30000);window.addEventListener('pagehide',()=>{clearInterval(timer);stop();},{once:true});document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});
  }

  async function profile() {
    const data=await get();
    const fullName=[data.first_name,data.middle_name,data.last_name].filter(Boolean).join(' ');
    const avatar=data.profile_photo_path?`<img src="${esc(data.profile_photo_path)}" alt="${esc(fullName)} profile picture">`:esc(nameInitials(fullName));
    const fields=[['first_name','First name'],['middle_name','Middle name'],['last_name','Last name'],['email','Email address']];
    host.innerHTML=`<div class="faculty-profile-layout"><aside class="faculty-panel faculty-profile-card"><p class="faculty-section-kicker">Your identity</p><div class="faculty-profile-avatar" data-profile-avatar>${avatar}</div><h2>${esc(fullName)}</h2><p>@${esc(data.username)}</p><p class="faculty-profile-note">Your name and photo appear across the faculty workspace. Your role and assigned team are managed by the SBO Adviser.</p></aside><section class="faculty-panel"><div class="faculty-panel__head"><p class="faculty-section-kicker">The essentials</p><h2>Profile details</h2><p>Keep your contact details and introduction up to date.</p></div><form class="faculty-profile-form" data-profile>${fields.map(([key,label])=>`<label class="faculty-field">${label}<input name="${key}" value="${esc(data[key]||'')}" ${key==='email'?'type="email" autocomplete="email"':''} ${key==='first_name'||key==='last_name'||key==='email'?'required':''}></label>`).join('')}<label class="faculty-field faculty-field--wide">About you<textarea name="bio" maxlength="280" placeholder="A short introduction for your CITE community">${esc(data.bio||'')}</textarea></label><label class="faculty-field faculty-field--wide">Profile picture<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><span class="text-xs font-normal text-[#121017]/50">JPG, PNG, or WebP · up to 5 MB.</span></label><div class="faculty-profile-actions"><button type="submit">Save profile</button></div></form></section></div>`;
    host.querySelector('form').onsubmit=async e=>{e.preventDefault();const button=e.submitter;window.Notifications?.setLoading(button,true,'Saving…');try{await axios.post('api/faculty.php?page=profile',new FormData(e.target),{headers:{'X-CSRF-Token':csrf}});window.Notifications?.flashNext('Profile updated.');location.reload();}catch(error){window.Notifications?.error(error.response?.data?.message||'Profile could not be saved.');window.Notifications?.setLoading(button,false);}};
  }

  window.SharedNavigation.ready.then(async session=>{csrf=session.csrfToken;await ({students,leaderboard,team,attendance,profile})[page]();}).catch(error=>{console.error(error);host.textContent=error.response?.data?.message||'This page could not be loaded.';});
})();
