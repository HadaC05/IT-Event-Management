window.SharedNavigation.ready.then(() => {
  'use strict';
  const form=document.querySelector('[data-scan-filters]'),list=document.querySelector('[data-scan-locations]'),pagination=document.querySelector('[data-scan-pagination]');
  const mapCanvas=document.querySelector('[data-latest-map-canvas]'),mapEmpty=document.querySelector('[data-latest-map-empty]');
  const mapEmptyMessage=document.querySelector('[data-latest-map-empty-message]'),mapDetails=document.querySelector('[data-latest-map-details]');
  const mapUpdated=document.querySelector('[data-latest-map-updated]'),teamLegend=document.querySelector('[data-team-map-legend]');
  const scanDetails=document.querySelector('[data-scan-details]');
  const mapReset=document.querySelector('[data-map-reset]');
  const distanceFilter=document.querySelector('[data-distance-filter]'),distanceAll=distanceFilter.querySelector('[data-distance-all]'),distanceOptions=[...distanceFilter.querySelectorAll('[name="distance_group[]"]')],distanceLabel=distanceFilter.querySelector('[data-distance-filter-label]');
  const esc=value=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const when=value=>new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short'}).format(new Date(String(value).replace(' ','T')));
  const accuracyMeta=value=>{
    const meters=Number(value);
    if(value===null||value===undefined||!Number.isFinite(meters))return {label:'Unavailable',className:'gps-accuracy-unknown',text:'Accuracy unavailable'};
    if(meters<=20)return {label:'Good',className:'gps-accuracy-good',text:`±${Math.round(meters)} m`};
    if(meters<=50)return {label:'Moderate',className:'gps-accuracy-moderate',text:`±${Math.round(meters)} m`};
    if(meters<=100)return {label:'Poor',className:'gps-accuracy-poor',text:`±${Math.round(meters)} m`};
    return {label:'Low confidence',className:'gps-accuracy-low',text:`±${Math.round(meters)} m`};
  };
  const accuracyBadge=scan=>{const accuracy=accuracyMeta(scan.location_accuracy_m);return `<span class="gps-accuracy-badge ${accuracy.className}">GPS quality: ${esc(accuracy.text)} &middot; ${accuracy.label}</span>`;};
  const stableNumber=value=>{
    const numeric=Number(value);
    if(Number.isFinite(numeric)&&numeric>0)return numeric;
    return [...String(value||'Unassigned')].reduce((hash,character)=>((hash*31)+character.charCodeAt(0))>>>0,7);
  };
  const teamColor=scan=>`hsl(${Math.round((stableNumber(scan.team_id||scan.team_name)*137.508)%360)} 68% 38%)`;
  const officerInitials=name=>String(name||'SBO').trim().split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]).join('').toUpperCase()||'S';
  const teamMarkerIcon=scan=>L.divIcon({
    className:'attendance-team-marker-icon',
    html:`<span class="attendance-team-marker" style="--team-marker-color:${teamColor(scan)}"><b>${esc(officerInitials(scan.officer_name))}</b></span>`,
    iconSize:[38,42],iconAnchor:[19,40],popupAnchor:[0,-39],tooltipAnchor:[0,-36]
  });
  const renderTeamLegend=scans=>{
    const teams=new Map();
    scans.forEach(scan=>{const key=String(scan.team_id||scan.team_name||'unassigned');if(!teams.has(key))teams.set(key,scan);});
    teamLegend.classList.toggle('hidden',teams.size===0);
    teamLegend.classList.toggle('flex',teams.size>0);
    teamLegend.innerHTML=[...teams.values()].map(scan=>`<span class="inline-flex items-center gap-1.5 whitespace-nowrap"><i class="h-2.5 w-2.5 rounded-full ring-2 ring-white shadow-sm" style="background:${teamColor(scan)}"></i>${esc(scan.team_name||'Unassigned')}</span>`).join('');
  };
  let optionsReady=false,optionData={events:[],event_locations:[],teams:[],officers:[]},request=0,pollTimer=0,lastScans=[],lastMapScans=[],focusedScanId=null;
  const expandedScans=new Set();
  const officerMarkers=new Map();
  const eventBoundaries=new Map();
  const scanMap=L.map(mapCanvas,{zoomControl:true,maxZoom:22}).setView([8.4702,124.6344],16);
  scanDetails?.addEventListener('toggle',event=>{
    if(event.target.open){requestAnimationFrame(()=>scanMap.invalidateSize());load();}
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxNativeZoom:19,
    maxZoom:22,
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(scanMap);
  const liveLayer=L.layerGroup().addTo(scanMap);
  const focusLayer=L.layerGroup();
  const dateInput=form.elements.schedule_date;
  const todayIso=()=>{
    const parts=Object.fromEntries(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).filter(part=>part.type!=='literal').map(part=>[part.type,part.value]));
    return `${parts.year}-${parts.month}-${parts.day}`;
  };
  const eventsForSelectedDate=()=>optionData.events.filter(row=>row.schedule_date===dateInput.value
    &&(!form.elements.event_type_id.value||String(row.event_type_id)===form.elements.event_type_id.value)
    &&(!form.elements.event_id.value||String(row.id)===form.elements.event_id.value));
  function syncEventOptions(){
    const select=form.elements.event_id;
    const type=form.elements.event_type_id.value;
    const events=optionData.events.filter(row=>row.schedule_date===dateInput.value&&(!type||String(row.event_type_id)===type));
    select.replaceChildren(new Option(events.length?'All events matching date and type':'No matching events',''));
    events.forEach(row=>select.add(new Option(row.title,String(row.id))));
    select.value='';
  }
  function shiftDate(days){
    const date=new Date(`${dateInput.value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate()+days);
    dateInput.value=date.toISOString().slice(0,10);
    syncEventOptions();
    form.elements.page.value='1';
    load();
  }
  dateInput.value=todayIso();
  function fill(options){
    optionData=options;
    const add=(field,rows,value,label)=>{const select=form.elements[field];if(!select)return;rows.forEach(row=>select.add(new Option(label(row),String(value(row)))));};
    add('event_id',options.events,row=>row.id,row=>row.title);
    add('event_type_id',options.event_types||[],row=>row.id,row=>row.label);
    add('day',options.days,row=>row.id,row=>`${row.event_name} · Day ${row.day_number} · ${row.schedule_date}`);
    add('team_id',options.teams,row=>row.id,row=>row.name);
    add('officer_id',options.officers,row=>row.id,row=>[row.first_name,row.middle_name,row.last_name].filter(Boolean).join(' '));
    syncEventOptions();
    optionsReady=true;
  }
  function render(scans){
    if(!scans.length){list.innerHTML='<p class="rounded-2xl border border-[#121017]/10 bg-white p-6 text-center text-sm text-[#121017]/50">No SBO attendance scans match these filters.</p>';return;}
    const sessions={morning:'Morning',afternoon:'Afternoon',whole_day:'Whole day'};
    const statuses={inside:'Inside',outside:'Outside',unavailable:'GPS unavailable'};
    list.innerHTML=scans.map(row=>{
      const coordinates=row.scan_latitude!==null&&row.scan_longitude!==null;
      const map=coordinates?`<button class="text-xs font-black text-[#397565] hover:underline" type="button" data-focus-scan="${esc(row.id)}">View Location &rarr;</button>`:'<span class="text-xs text-[#121017]/45">No map location</span>';
      const location=coordinates?`${Number(row.scan_latitude).toFixed(6)}, ${Number(row.scan_longitude).toFixed(6)}`:'Unavailable';
      const accuracy=row.location_accuracy_m===null?'&mdash;':'&plusmn;'+Number(row.location_accuracy_m).toFixed(0)+' m';
      const distance=row.distance_from_venue_m===null?'&mdash;':Number(row.distance_from_venue_m).toFixed(1)+' m';
      const statusClass=row.location_status==='inside'?'text-[#397565]':row.location_status==='outside'?'text-[#D64A12]':'text-[#121017]/50';
      const scanId=String(row.id),session=sessions[row.session_code]||row.session_code,status=statuses[row.location_status]||row.location_status;
      return `<details class="group overflow-hidden rounded-xl border border-[#121017]/10 bg-white shadow-sm" data-scan-record="${esc(scanId)}" ${expandedScans.has(scanId)?'open':''}>
        <summary class="attendance-scan-summary cursor-pointer list-none gap-3 p-4">
          <span class="min-w-0"><b class="block truncate text-sm">${esc(row.student_name)}</b><small class="block truncate text-[#121017]/45">${esc(row.id_number)} &middot; ${esc(row.team_name)}</small></span>
          <span class="min-w-0"><b class="block truncate text-xs">${esc(row.event_name)}</b><small class="block truncate text-[#121017]/45">Day ${row.day_number} &middot; ${esc(session)}</small></span>
          <span class="min-w-0"><b class="block text-xs">${row.phase==='out'?'Time Out':'Time In'}</b><small class="block truncate text-[#121017]/45">${esc(when(row.scanned_at))}</small></span>
          <span class="flex items-center justify-between gap-3"><b class="text-xs ${statusClass}">${esc(status)}</b><span class="attendance-scan-chevron text-sm text-[#397565]" aria-hidden="true">&#9662;</span></span>
        </summary>
        <div class="border-t border-[#121017]/8 bg-[#F3F0E9]/25 p-4">
          <div class="grid gap-2 text-xs sm:grid-cols-2 xl:grid-cols-3">
            <p><b>SBO officer:</b> ${esc(row.officer_name)}</p><p><b>${row.location_status==='outside'?'Nearest venue':'Detected venue'}:</b> ${esc(row.venue_name_snapshot||'Not configured')}</p>
            <p><b>Coordinates:</b> ${esc(location)}</p><p><b>Accuracy:</b> ${accuracy}</p>
            <p><b>Distance from venue:</b> ${distance}</p><p><b>Location captured:</b> ${row.location_captured_at?esc(when(row.location_captured_at)):'&mdash;'}</p>
            <p><b>Unavailable reason:</b> ${esc(row.location_unavailable_reason||'—')}</p>
          </div><div class="mt-3">${map}</div>
        </div>
      </details>`;
    }).join('');
  }
  function renderPagination(meta){
    if(meta)form.elements.page.value=String(meta.current_page);
    const visible=meta&&meta.last_page>1;
    pagination.classList.toggle('hidden',!visible);
    pagination.classList.toggle('flex',visible);
    if(!visible){pagination.innerHTML='';return;}
    pagination.innerHTML=`<span class="font-bold text-[#121017]/50">Showing ${meta.from}-${meta.to} of ${meta.total} scans</span><span class="flex items-center gap-2"><button class="min-h-10 rounded-xl border border-[#121017]/10 bg-white px-4 font-black text-[#397565] disabled:opacity-30" type="button" data-scan-page="${meta.current_page-1}" ${meta.current_page===1?'disabled':''}>Previous</button><b class="px-2 text-[#121017]/55">${meta.current_page} / ${meta.last_page}</b><button class="min-h-10 rounded-xl border border-[#121017]/10 bg-white px-4 font-black text-[#397565] disabled:opacity-30" type="button" data-scan-page="${meta.current_page+1}" ${meta.current_page===meta.last_page?'disabled':''}>Next</button></span>`;
  }
  function renderLatestMap(scans){
    const latestByOfficer=new Map();
    scans.forEach(scan=>{
      const key=scan.officer_id===null?`event-${scan.event_id}-unknown-${scan.id}`:`event-${scan.event_id}-officer-${scan.officer_id}`;
      if(!latestByOfficer.has(key))latestByOfficer.set(key,scan);
    });
    const latest=[...latestByOfficer.entries()];
    renderTeamLegend(latest.map(([,scan])=>scan));
    const activeKeys=new Set(latest.map(([key])=>key));
    let changed=false;
    officerMarkers.forEach((entry,key)=>{
      const newest=latestByOfficer.get(key);
      const hasCoordinates=newest&&newest.scan_latitude!==null&&newest.scan_longitude!==null;
      if(!activeKeys.has(key)||!hasCoordinates){liveLayer.removeLayer(entry.marker);officerMarkers.delete(key);changed=true;}
    });
    latest.forEach(([key,scan])=>{
      if(scan.scan_latitude===null||scan.scan_longitude===null)return;
      const latitude=Number(scan.scan_latitude),longitude=Number(scan.scan_longitude);
      const existing=officerMarkers.get(key);
      if(existing?.scanId===scan.id)return;
      if(existing)liveLayer.removeLayer(existing.marker);
      const popup=`<strong>${esc(scan.officer_name)}</strong><br>Team: ${esc(scan.team_name||'Unassigned')}<br>${esc(scan.student_name)} &middot; ${scan.phase==='out'?'Time Out':'Time In'}<br>${esc(scan.event_name)}<br>Venue: ${esc(scan.venue_name_snapshot||'Not configured')}<br>${esc(when(scan.scanned_at))}<br>${accuracyBadge(scan)}`;
      const marker=L.marker([latitude,longitude],{icon:teamMarkerIcon(scan)}).bindPopup(popup).addTo(liveLayer);
      marker.bindTooltip(`<strong>${esc(scan.officer_name)}</strong><span class="attendance-map-team"><i style="background:${teamColor(scan)}"></i>${esc(scan.team_name||'Unassigned')}</span>${accuracyBadge(scan)}`,{direction:'top',offset:[0,-12],className:'attendance-map-tooltip'});
      officerMarkers.set(key,{marker,scanId:scan.id});
      changed=true;
    });
    const desiredBoundaries=new Map();
    const scheduledEvents=eventsForSelectedDate();
    const scheduledEventIds=new Set(scheduledEvents.map(event=>Number(event.id)));
    (optionData.event_locations||[]).filter(venue=>venue.schedule_date===dateInput.value&&scheduledEventIds.has(Number(venue.event_id))).forEach(venue=>{
      if(venue.location_id===null||venue.venue_latitude===null||venue.venue_longitude===null||venue.venue_radius===null)return;
      const event=scheduledEvents.find(item=>Number(item.id)===Number(venue.event_id));
      const key=`event-${venue.event_id}-location-${venue.location_id}`;
      if(!desiredBoundaries.has(key))desiredBoundaries.set(key,{...venue,id:venue.event_id,title:event?.title||'Event'});
    });
    eventBoundaries.forEach((entry,key)=>{
      if(!desiredBoundaries.has(key)){liveLayer.removeLayer(entry.rectangle);eventBoundaries.delete(key);changed=true;}
    });
    desiredBoundaries.forEach((event,key)=>{
      const latitude=Number(event.venue_latitude),longitude=Number(event.venue_longitude),radius=Number(event.venue_radius);
      const signature=`${latitude}:${longitude}:${radius}`;
      if(eventBoundaries.get(key)?.signature===signature)return;
      if(eventBoundaries.has(key))liveLayer.removeLayer(eventBoundaries.get(key).rectangle);
      const latitudeDelta=radius/111320;
      const longitudeDelta=radius/(111320*Math.max(Math.cos(latitude*Math.PI/180),0.000001));
      const bounds=[[latitude-latitudeDelta,longitude-longitudeDelta],[latitude+latitudeDelta,longitude+longitudeDelta]];
      const rectangle=L.rectangle(bounds,{color:'#397565',weight:2,opacity:.9,dashArray:'8 6',fillColor:'#C6F24E',fillOpacity:.13})
        .bindPopup(`<strong>${esc(event.venue_name||'Event venue')}</strong><br>${esc(event.title)}<br>Square boundary: ${radius.toFixed(0)} m from center`)
        .addTo(liveLayer);
      eventBoundaries.set(key,{rectangle,signature});
      changed=true;
    });
    if(focusedScanId!==null){
      setTimeout(()=>scanMap.invalidateSize(),0);
      return;
    }
    const located=latest.filter(([,scan])=>scan.scan_latitude!==null&&scan.scan_longitude!==null);
    mapUpdated.textContent=latest.length?`${located.length} of ${latest.length} SBO officer positions shown · checked ${new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date())}`:'No matching scans';
    mapUpdated.textContent=latest.length
      ? `${located.length} of ${latest.length} officer/event positions shown · checked ${new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date())}`
      : `${scheduledEvents.length} event${scheduledEvents.length===1?'':'s'} scheduled · no scans`;
    const hasMapContent=located.length>0||eventBoundaries.size>0;
    mapEmpty.classList.toggle('hidden',hasMapContent);
    mapEmptyMessage.textContent=latest.length
      ? 'The latest matching scans did not include GPS coordinates.'
      : 'Each SBO officer will appear here after their first matching GPS scan.';
    mapDetails.classList.toggle('hidden',latest.length===0);
    mapDetails.classList.toggle('flex',latest.length>0);
    mapDetails.innerHTML=latest.map(([,scan])=>{
      const coordinates=scan.scan_latitude!==null&&scan.scan_longitude!==null;
      const statusClass=scan.location_status==='inside'?'text-[#397565]':scan.location_status==='outside'?'text-[#D64A12]':'text-[#121017]/50';
      const distanceFromVenue=Number(scan.distance_from_venue_m);
      const outsideDistance=scan.location_status==='outside'&&scan.distance_from_venue_m!==null&&scan.distance_from_venue_m!==undefined&&Number.isFinite(distanceFromVenue)
        ? `<span class="ml-1 font-black text-[#D64A12]">(${distanceFromVenue.toFixed(1)} m away)</span>`
        : '';
      const locate=coordinates
        ? `<button class="shrink-0 text-[10px] font-black text-[#397565] hover:underline" type="button" data-locate-map-scan="${esc(scan.id)}" aria-label="View ${esc(scan.officer_name)} on the map">View Location &rarr;</button>`
        : '<span class="shrink-0 text-[10px] text-[#121017]/35">No GPS</span>';
      return `<article class="w-72 shrink-0 rounded-xl border border-[#121017]/8 bg-[#F3F0E9]/45 px-3 py-2.5" style="border-left:4px solid ${teamColor(scan)}"><div class="flex items-center justify-between gap-3"><b class="min-w-0 truncate text-xs">${esc(scan.officer_name)}</b>${locate}</div><div class="mt-1 flex items-center justify-between gap-3"><span class="inline-flex min-w-0 items-center gap-1.5 truncate text-[10px] font-bold text-[#121017]/65"><i class="h-2 w-2 shrink-0 rounded-full" style="background:${teamColor(scan)}"></i>${esc(scan.team_name||'Unassigned')}</span><span class="shrink-0 text-[10px] font-black ${statusClass}">${coordinates?esc(scan.location_status):'GPS unavailable'}</span></div><span class="mt-1 block truncate text-[10px] text-[#121017]/50">Latest: ${esc(scan.student_name)} &middot; ${esc(when(scan.scanned_at))}</span><span class="mt-0.5 block truncate text-[10px] text-[#121017]/50">Venue: ${esc(scan.venue_name_snapshot||'Not configured')}${outsideDistance}</span></article>`;
    }).join('');
    if(changed&&hasMapContent){
      const layers=[...officerMarkers.values()].map(entry=>entry.marker).concat([...eventBoundaries.values()].map(entry=>entry.rectangle));
      const bounds=L.featureGroup(layers).getBounds();
      scanMap.fitBounds(bounds,{padding:[35,35],maxZoom:21});
    }
    setTimeout(()=>scanMap.invalidateSize(),0);
  }
  function scanBoundary(scan){
    if(scan.venue_latitude===null||scan.venue_longitude===null||scan.venue_radius===null)return null;
    const latitude=Number(scan.venue_latitude),longitude=Number(scan.venue_longitude),radius=Number(scan.venue_radius);
    const latitudeDelta=radius/111320;
    const longitudeDelta=radius/(111320*Math.max(Math.cos(latitude*Math.PI/180),0.000001));
    return {bounds:[[latitude-latitudeDelta,longitude-longitudeDelta],[latitude+latitudeDelta,longitude+longitudeDelta]],radius};
  }
  function focusScan(scan){
    if(!scan||scan.scan_latitude===null||scan.scan_longitude===null)return;
    focusedScanId=String(scan.id);
    if(!scanMap.hasLayer(liveLayer))liveLayer.addTo(scanMap);
    focusLayer.clearLayers();
    if(!scanMap.hasLayer(focusLayer))focusLayer.addTo(scanMap);
    const latitude=Number(scan.scan_latitude),longitude=Number(scan.scan_longitude);
    const popup=`<strong>${esc(scan.officer_name)}</strong><br>Team: ${esc(scan.team_name||'Unassigned')}<br>${esc(scan.student_name)} &middot; ${scan.phase==='out'?'Time Out':'Time In'}<br>${esc(scan.event_name)}<br>Venue: ${esc(scan.venue_name_snapshot||'Not configured')}<br>${esc(when(scan.scanned_at))}<br>${accuracyBadge(scan)}`;
    const marker=L.marker([latitude,longitude],{icon:teamMarkerIcon(scan)}).bindPopup(popup).addTo(focusLayer);
    marker.bindTooltip(`<strong>${esc(scan.officer_name)}</strong><span class="attendance-map-team"><i style="background:${teamColor(scan)}"></i>${esc(scan.team_name||'Unassigned')}</span>${accuracyBadge(scan)}`,{direction:'top',offset:[0,-12],className:'attendance-map-tooltip'});
    const boundary=scanBoundary(scan);
    if(boundary){
      L.rectangle(boundary.bounds,{color:'#397565',weight:2,opacity:.9,dashArray:'8 6',fillColor:'#C6F24E',fillOpacity:.13})
        .bindPopup(`<strong>${esc(scan.venue_name_snapshot||'Event venue')}</strong><br>${esc(scan.event_name)}<br>Square boundary: ${boundary.radius.toFixed(0)} m from center`)
        .addTo(focusLayer);
    }
    const bounds=L.featureGroup(focusLayer.getLayers()).getBounds();
    if(bounds.isValid())scanMap.fitBounds(bounds,{padding:[45,45],maxZoom:21});
    else scanMap.setView([latitude,longitude],21);
    marker.openPopup();
    mapReset.classList.remove('hidden');
    mapEmpty.classList.add('hidden');
    mapDetails.classList.remove('hidden');
    mapDetails.classList.add('flex');
    mapUpdated.textContent=`Focused scan by ${scan.officer_name}`;
    setTimeout(()=>scanMap.invalidateSize(),0);
  }
  function resetMapFocus(){
    focusedScanId=null;
    if(scanMap.hasLayer(focusLayer))scanMap.removeLayer(focusLayer);
    focusLayer.clearLayers();
    if(!scanMap.hasLayer(liveLayer))liveLayer.addTo(scanMap);
    mapReset.classList.add('hidden');
    renderLatestMap(lastMapScans);
    const layers=[...officerMarkers.values()].map(entry=>entry.marker).concat([...eventBoundaries.values()].map(entry=>entry.rectangle));
    if(layers.length){
      const bounds=L.featureGroup(layers).getBounds();
      if(bounds.isValid())scanMap.fitBounds(bounds,{padding:[35,35],maxZoom:21});
    }
    render(lastScans);
  }
  async function load(){
    const id=++request;
    try{
      const filters=Object.fromEntries(new FormData(form));
      filters.distance_group=distanceOptions.filter(input=>input.checked).map(input=>input.value);
      const response=await axios.get('api/adviser-attendance-scans.php',{params:filters});
      if(id!==request)return;
      if(!optionsReady)fill(response.data.data.options);
      const scans=response.data.data.scans;
      const mapScans=response.data.data.map_scans||scans;
      lastScans=scans;
      lastMapScans=mapScans;
      if(focusedScanId!==null&&!scans.some(scan=>String(scan.id)===focusedScanId))resetMapFocus();
      renderLatestMap(mapScans);
      render(scans);
      renderPagination(response.data.data.pagination);
    }catch(error){if(id===request)list.textContent=error.response?.data?.message||'Scan locations could not be loaded.';}
  }
  const schedulePoll=()=>{clearInterval(pollTimer);pollTimer=setInterval(()=>{if(!document.hidden&&scanDetails?.open)load();},5000);};
  const syncDistanceFilter=changed=>{
    if(changed===distanceAll&&distanceAll.checked)distanceOptions.forEach(input=>input.checked=false);
    if(distanceOptions.includes(changed)&&changed.checked)distanceAll.checked=false;
    const selected=distanceOptions.filter(input=>input.checked);
    distanceLabel.textContent=!selected.length||distanceAll.checked?'All distance groups':selected.length===1?selected[0].closest('label').childNodes[1].textContent.trim():`${selected.length} distance groups`;
  };
  distanceFilter.addEventListener('change',event=>syncDistanceFilter(event.target));
  document.addEventListener('click',event=>{if(distanceFilter.open&&!distanceFilter.contains(event.target))distanceFilter.removeAttribute('open');});
  form.addEventListener('change',event=>{if(event.target===dateInput||event.target===form.elements.event_type_id)syncEventOptions();if(event.target.name!=='page')form.elements.page.value='1';load();});
  document.querySelector('[data-date-previous]').addEventListener('click',()=>shiftDate(-1));
  document.querySelector('[data-date-next]').addEventListener('click',()=>shiftDate(1));
  document.querySelector('[data-date-today]').addEventListener('click',()=>{dateInput.value=todayIso();syncEventOptions();form.elements.page.value='1';load();});
  list.addEventListener('toggle',event=>{
    const record=event.target;
    if(!(record instanceof HTMLDetailsElement)||!record.matches('[data-scan-record]'))return;
    if(record.open)expandedScans.add(record.dataset.scanRecord);
    else expandedScans.delete(record.dataset.scanRecord);
  },true);
  list.addEventListener('click',event=>{
    const button=event.target.closest('[data-focus-scan]');
    if(!button)return;
    focusScan(lastScans.find(scan=>String(scan.id)===button.dataset.focusScan));
  });
  mapReset.addEventListener('click',resetMapFocus);
  mapDetails.addEventListener('click',event=>{
    const button=event.target.closest('[data-locate-map-scan]');
    if(!button)return;
    const scan=lastMapScans.find(item=>String(item.id)===button.dataset.locateMapScan);
    if(scan)focusScan(scan);
  });
  pagination.addEventListener('click',event=>{
    const button=event.target.closest('[data-scan-page]');
    if(!button||button.disabled)return;
    form.elements.page.value=button.dataset.scanPage;
    load();
    list.scrollIntoView({behavior:'smooth',block:'start'});
  });
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&scanDetails?.open)load();});
  addEventListener('beforeunload',()=>clearInterval(pollTimer));
  schedulePoll();
});
