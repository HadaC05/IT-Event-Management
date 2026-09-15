(() => {
  'use strict';
  const form=document.querySelector('[data-scan-filters]'),list=document.querySelector('[data-scan-locations]');
  const esc=value=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const when=value=>new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short'}).format(new Date(String(value).replace(' ','T')));
  let optionsReady=false,request=0;
  function fill(options){
    const add=(field,rows,value,label)=>{const select=form.elements[field];rows.forEach(row=>select.add(new Option(label(row),String(value(row)))));};
    add('event_id',options.events,row=>row.id,row=>row.title);
    add('day',options.days,row=>row.id,row=>`${row.event_name} · Day ${row.day_number} · ${row.schedule_date}`);
    add('team_id',options.teams,row=>row.id,row=>row.name);
    add('officer_id',options.officers,row=>row.id,row=>[row.first_name,row.middle_name,row.last_name].filter(Boolean).join(' '));
    optionsReady=true;
  }
  function render(scans){
    if(!scans.length){list.innerHTML='<p class="rounded-2xl border border-[#121017]/10 bg-white p-6 text-center text-sm text-[#121017]/50">No SBO attendance scans match these filters.</p>';return;}
    list.innerHTML=scans.map(row=>{
      const coordinates=row.scan_latitude!==null&&row.scan_longitude!==null;
      const map=coordinates?`<a class="text-xs font-black text-[#397565]" href="https://www.google.com/maps?q=${encodeURIComponent(row.scan_latitude+','+row.scan_longitude)}" target="_blank" rel="noopener noreferrer">View Location →</a>`:'<span class="text-xs text-[#121017]/45">No map location</span>';
      const location=coordinates?`${Number(row.scan_latitude).toFixed(6)}, ${Number(row.scan_longitude).toFixed(6)}`:'Unavailable';
      const accuracy=row.location_accuracy_m===null?'—':'±'+Number(row.location_accuracy_m).toFixed(0)+' m';
      const distance=row.distance_from_venue_m===null?'—':Number(row.distance_from_venue_m).toFixed(1)+' m';
      const statusClass=row.location_status==='inside'?'text-[#397565]':row.location_status==='outside'?'text-[#D64A12]':'text-[#121017]/50';
      return `<article class="rounded-2xl border border-[#121017]/10 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><strong class="text-sm">${esc(row.student_name)}</strong>
          <p class="mt-1 text-xs text-[#121017]/50">${esc(row.id_number)} · ${esc(row.team_name)} · ${esc(row.event_name)} · Day ${row.day_number} · ${esc(row.session_code)}</p></div>
          <strong class="text-xs ${statusClass}">${esc(row.location_status)}</strong></div>
        <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2 xl:grid-cols-3">
          <p><b>Status:</b> ${esc(row.status)}</p><p><b>Scanned:</b> ${when(row.scanned_at)}</p>
          <p><b>SBO officer:</b> ${esc(row.officer_name)}</p><p><b>Venue:</b> ${esc(row.venue_name_snapshot||'Not configured')}</p>
          <p><b>Coordinates:</b> ${esc(location)}</p><p><b>Accuracy:</b> ${accuracy}</p>
          <p><b>Distance from venue:</b> ${distance}</p><p><b>Location captured:</b> ${row.location_captured_at?when(row.location_captured_at):'—'}</p>
          <p><b>Unavailable reason:</b> ${esc(row.location_unavailable_reason||'—')}</p>
        </div><div class="mt-3">${map}</div></article>`;
    }).join('');
  }
  async function load(){
    const id=++request;
    try{
      const filters=Object.fromEntries(new FormData(form));const response=await axios.get('api/adviser-attendance-scans.php',{params:filters});
      if(id!==request)return;
      if(!optionsReady)fill(response.data.data.options);
      render(response.data.data.scans);
    }catch(error){if(id===request)list.textContent=error.response?.data?.message||'Scan locations could not be loaded.';}
  }
  form.addEventListener('change',load);load();
})();
