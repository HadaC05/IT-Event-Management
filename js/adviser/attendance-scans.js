window.SharedNavigation.ready.then(() => {
  'use strict';
  const form=document.querySelector('[data-scan-filters]'),list=document.querySelector('[data-scan-locations]'),pagination=document.querySelector('[data-scan-pagination]');
  const mapSection=document.querySelector('[data-latest-scan-map]'),mapCanvas=document.querySelector('[data-latest-map-canvas]'),mapEmpty=document.querySelector('[data-latest-map-empty]');
  const mapEmptyMessage=document.querySelector('[data-latest-map-empty-message]'),mapDetails=document.querySelector('[data-latest-map-details]');
  const mapUpdated=document.querySelector('[data-latest-map-updated]'),teamLegend=document.querySelector('[data-team-map-legend]');
  const scanDetails=document.querySelector('[data-scan-details]');
  const mapReset=document.querySelector('[data-map-reset]');
  const searchInput=document.querySelector('[data-scan-search]');
  const resetFilters=document.querySelector('[data-reset-scan-filters]');
  const distanceFilter=document.querySelector('[data-distance-filter]'),distanceAll=distanceFilter.querySelector('[data-distance-all]'),distanceOptions=[...distanceFilter.querySelectorAll('[name="distance_group[]"]')],distanceLabel=distanceFilter.querySelector('[data-distance-filter-label]');
  const distanceCountElements=[...distanceFilter.querySelectorAll('[data-distance-count]')],distanceFarAlert=document.querySelector('[data-distance-far-alert]');
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
  const FAR_OUTSIDE_METERS=150;
  const distanceBeyondVenue=scan=>{
    const boxDistance=Number(scan.distance_from_venue_box_m);
    if(scan.distance_from_venue_box_m!==null&&scan.distance_from_venue_box_m!==undefined&&Number.isFinite(boxDistance))return Math.max(0,boxDistance);
    const distance=Number(scan.distance_from_venue_m),radius=Number(scan.venue_radius??0);
    return scan.distance_from_venue_m!==null&&scan.distance_from_venue_m!==undefined&&Number.isFinite(distance)?Math.max(0,distance-(Number.isFinite(radius)?radius:0)):null;
  };
  const isFarScan=scan=>scan.location_status==='outside'&&distanceBeyondVenue(scan)>FAR_OUTSIDE_METERS;
  const distanceStatusMeta=scan=>{
    if(scan.location_status==='inside')return {label:'Inside venue',color:'#218739',className:'attendance-popup-inside'};
    if(scan.location_status==='outside')return isFarScan(scan)
      ? {label:'Far from venue',color:'#D92D20',className:'attendance-popup-far'}
      : {label:'Nearby outside venue',color:'#D97706',className:'attendance-popup-nearby'};
    return {label:'GPS unavailable',color:'#737078',className:'attendance-popup-unavailable'};
  };
  const venueStatusTag=scan=>{
    if(scan.location_status==='inside')return '<span class="ml-1 shrink-0 font-black" style="color:#218739">(IN)</span>';
    const outside=distanceBeyondVenue(scan);
    if(scan.location_status==='outside'&&outside!==null)return isFarScan(scan)
      ? `<span class="attendance-far-warning"><i aria-hidden="true">!</i>(AWAY ${outside.toFixed(1)} m)</span>`
      : `<span class="ml-1 shrink-0 font-black" style="color:#D97706">(AWAY ${outside.toFixed(1)} m)</span>`;
    return '<span class="ml-1 shrink-0 font-black" style="color:#737078">(GPS unavailable)</span>';
  };
  const teamColor=scan=>/^#[0-9a-f]{6}$/i.test(String(scan.team_color||''))?scan.team_color:'#397565';
  const teamBadge=(scan,prefix='')=>`<span class="attendance-team-badge" style="--team-badge-color:${teamColor(scan)}"><i aria-hidden="true"></i><b>${esc(prefix)}${esc(scan.team_name||'Unassigned')}</b></span>`;
  const recorderRole=scan=>scan.recorder_role==='Faculty'?'Faculty':scan.recorder_role==='SBO Officer'?'SBO':scan.recorder_role||'Recorder';
  const officerLabel=scan=>`${scan.officer_name} (${scan.scanner_mode==='general'?'GENERAL':'SPECIFIC'})`;
  const scanPopup=scan=>`<strong>${esc(recorderRole(scan))}:</strong> ${esc(officerLabel(scan))}<br>${teamBadge(scan,'Team: ')}<br><strong>Student:</strong> ${esc(scan.student_name)} &middot; ${scan.phase==='out'?'Time Out':'Time In'}<br>${esc(scan.event_name)}<br>Venue: ${esc(scan.venue_name_snapshot||'Not configured')} ${venueStatusTag(scan)}<br>${esc(when(scan.scanned_at))}<br>${accuracyBadge(scan)}`;
  const officerInitials=name=>String(name||'SBO').trim().split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]).join('').toUpperCase()||'S';
  const teamMarkerIcon=scan=>{
    const distanceStatus=distanceStatusMeta(scan);
    return L.divIcon({
      className:'attendance-team-marker-icon',
      html:`<span class="attendance-team-marker" style="--team-marker-color:${teamColor(scan)};--distance-status-color:${distanceStatus.color}"><i class="attendance-marker-distance-dot" title="${esc(distanceStatus.label)}" aria-hidden="true"></i><b>${esc(officerInitials(scan.officer_name))}</b></span>`,
      iconSize:[38,42],iconAnchor:[19,40],popupAnchor:[0,-39],tooltipAnchor:[0,-36]
    });
  };
  let teamLegendExpanded=false;
  const renderTeamLegend=scans=>{
    const teams=new Map();
    scans.forEach(scan=>{const key=String(scan.team_id||scan.team_name||'unassigned');if(!teams.has(key))teams.set(key,scan);});
    teamLegend.classList.toggle('hidden',teams.size===0);
    teamLegend.classList.toggle('flex',teams.size>0);
    const allTeams=[...teams.values()];
    const visibleTeams=teamLegendExpanded?allTeams:allTeams.slice(0,4);
    const items=visibleTeams.map(scan=>`<span class="inline-flex items-center gap-1.5 whitespace-nowrap"><i class="h-2.5 w-2.5 rounded-full ring-2 ring-white shadow-sm" style="background:${teamColor(scan)}"></i>${esc(scan.team_name||'Unassigned')}</span>`);
    if(allTeams.length>4)items.push(`<button class="whitespace-nowrap rounded-full border border-[#397565]/20 bg-[#397565]/8 px-2 py-0.5 font-black text-[#397565] hover:bg-[#397565] hover:text-white" type="button" data-team-legend-toggle aria-expanded="${teamLegendExpanded}">${teamLegendExpanded?'Show less':`+${allTeams.length-4} more`}</button>`);
    teamLegend.innerHTML=items.join('');
  };
  let optionsReady=false,optionData={events:[],event_locations:[],teams:[],officers:[]},request=0,pollTimer=0,searchTimer=0,activeController=null,lastDataVersion='',lastScans=[],lastMapScans=[],lastLatestMapOfficerScans=[],lastLatestOfficerScans=[],currentMapScans=[],focusedScanId=null;
  const expandedScans=new Set();
  const scanMarkers=new Map();
  const eventBoundaries=new Map();
  const scanMap=L.map(mapCanvas,{zoomControl:true,keyboard:false,maxZoom:22}).setView([8.4702,124.6344],16);
  mapCanvas.tabIndex=0;
  mapCanvas.setAttribute('aria-keyshortcuts','ArrowUp ArrowDown ArrowLeft ArrowRight W A S D');
  const farIndicatorLayer=document.createElement('div');
  farIndicatorLayer.className='attendance-far-indicators';
  farIndicatorLayer.setAttribute('aria-label','Scan and venue locations outside the visible map');
  mapCanvas.append(farIndicatorLayer);
  L.DomEvent.disableClickPropagation(farIndicatorLayer);
  L.DomEvent.disableScrollPropagation(farIndicatorLayer);
  scanDetails?.addEventListener('toggle',event=>{
    if(event.target.open){requestAnimationFrame(()=>scanMap.invalidateSize());load();}
  });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxNativeZoom:19,
    maxZoom:22,
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(scanMap);
  const liveLayer=L.layerGroup().addTo(scanMap);
  const focusLayer=L.layerGroup();
  function renderFarIndicators(){
    if(!scanMap._loaded){farIndicatorLayer.replaceChildren();return;}
    const bounds=scanMap.getBounds(),size=scanMap.getSize(),center=L.point(size.x/2,size.y/2),margin=34;
    const edgePosition=latLng=>{
      const target=scanMap.latLngToContainerPoint(latLng);
      const dx=target.x-center.x,dy=target.y-center.y;
      const scale=Math.min((center.x-margin)/Math.max(Math.abs(dx),0.0001),(center.y-margin)/Math.max(Math.abs(dy),0.0001));
      return {x:Math.max(margin,Math.min(size.x-margin,center.x+dx*scale)),y:Math.max(margin,Math.min(size.y-margin,center.y+dy*scale))};
    };
    const indicators=currentMapScans.filter(scan=>scan.scan_latitude!==null&&scan.scan_longitude!==null&&isFarScan(scan))
      .filter(scan=>!bounds.contains([Number(scan.scan_latitude),Number(scan.scan_longitude)]))
      .map(scan=>{
        const {x,y}=edgePosition([Number(scan.scan_latitude),Number(scan.scan_longitude)]);
        const beyond=distanceBeyondVenue(scan);
        return `<button class="attendance-far-indicator" type="button" data-far-scan="${esc(scan.id)}" style="left:${x.toFixed(1)}px;top:${y.toFixed(1)}px;--tribe-color:${teamColor(scan)}" aria-label="View far scan by ${esc(officerLabel(scan))} from ${esc(scan.team_name||'unassigned tribe')}" title="${esc(officerLabel(scan))} · ${beyond===null?'Far from venue':`${Math.round(beyond)} m beyond venue`}"><i aria-hidden="true"></i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg><small>FAR</small></button>`;
      });
    eventBoundaries.forEach((entry,key)=>{
      const venueBounds=entry.rectangle.getBounds();
      if(bounds.intersects(venueBounds))return;
      const {x,y}=edgePosition(venueBounds.getCenter());
      const venueName=entry.event?.venue_name||'Event venue';
      indicators.push(`<button class="attendance-venue-indicator" type="button" data-offscreen-venue="${esc(key)}" style="left:${x.toFixed(1)}px;top:${y.toFixed(1)}px" aria-label="View off-screen venue ${esc(venueName)}" title="${esc(venueName)}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M6 20V8l6-4 6 4v12M9 11h2m2 0h2m-6 4h2m2 0h2"/></svg><small>VENUE</small></button>`);
    });
    farIndicatorLayer.innerHTML=indicators.join('');
  }
  scanMap.on('move zoom resize',renderFarIndicators);
  farIndicatorLayer.addEventListener('click',event=>{
    const button=event.target.closest('[data-far-scan],[data-offscreen-venue]');
    if(!button)return;
    event.preventDefault();
    event.stopPropagation();
    if(button.dataset.farScan){
      const scan=currentMapScans.find(item=>String(item.id)===button.dataset.farScan);
      if(scan)focusScan(scan,false);
      return;
    }
    const venue=eventBoundaries.get(button.dataset.offscreenVenue);
    if(!venue)return;
    scanMap.fitBounds(venue.rectangle.getBounds(),{padding:[45,45],maxZoom:21});
    venue.rectangle.openPopup();
    mapCanvas.focus({preventScroll:true});
  });
  mapCanvas.addEventListener('keydown',event=>{
    if(event.target!==mapCanvas||event.altKey||event.ctrlKey||event.metaKey)return;
    const movement={arrowup:[0,-120],w:[0,-120],arrowdown:[0,120],s:[0,120],arrowleft:[-120,0],a:[-120,0],arrowright:[120,0],d:[120,0]}[event.key.toLowerCase()];
    if(!movement)return;
    event.preventDefault();
    event.stopPropagation();
    scanMap.panBy(movement,{animate:true,duration:.18});
  });
  scanMap.on('click',()=>mapCanvas.focus({preventScroll:true}));
  const dateInput=form.elements.schedule_date;
  const todayIso=()=>{
    const parts=Object.fromEntries(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).filter(part=>part.type!=='literal').map(part=>[part.type,part.value]));
    return `${parts.year}-${parts.month}-${parts.day}`;
  };
  const hasActiveFilters=()=>dateInput.value!==todayIso()
    ||['event_type_id','event_id','phase','session','team_id','officer_id','location_status'].some(name=>form.elements[name].value)
    ||searchInput.value.trim()!==''
    ||distanceOptions.some(input=>input.checked);
  const syncResetFilters=()=>resetFilters.classList.toggle('hidden',!hasActiveFilters());
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
    if(!scans.length){list.innerHTML='<p class="rounded-2xl border border-[#121017]/10 bg-white p-6 text-center text-sm text-[#121017]/50">No attendance scans match these filters.</p>';return;}
    const sessions={morning:'Morning',afternoon:'Afternoon',whole_day:'Whole day'};
    const statuses={inside:'Inside',outside:'Outside',unavailable:'GPS unavailable'};
    list.innerHTML=scans.map(row=>{
      const coordinates=row.scan_latitude!==null&&row.scan_longitude!==null;
      const map=coordinates?`<button class="text-xs font-black text-[#397565] hover:underline" type="button" data-focus-scan="${esc(row.id)}">View Location &rarr;</button>`:'<span class="text-xs text-[#121017]/45">No map location</span>';
      const location=coordinates?`${Number(row.scan_latitude).toFixed(6)}, ${Number(row.scan_longitude).toFixed(6)}`:'Unavailable';
      const accuracy=row.location_accuracy_m===null?'&mdash;':'&plusmn;'+Number(row.location_accuracy_m).toFixed(0)+' m';
      const outsideBoxDistance=distanceBeyondVenue(row);
      const distance=row.location_status==='inside'?'Inside venue':row.location_status==='outside'&&outsideBoxDistance!==null?outsideBoxDistance.toFixed(1)+' m outside box':'&mdash;';
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
            <p><b>${esc(recorderRole(row))}:</b> ${esc(officerLabel(row))}</p><p><b>${row.location_status==='outside'?'Nearest venue':'Detected venue'}:</b> ${esc(row.venue_name_snapshot||'Not configured')}</p>
            <p><b>Coordinates:</b> ${esc(location)}</p><p><b>Accuracy:</b> ${accuracy}</p>
            <p><b>Distance outside venue box:</b> ${distance}</p><p><b>Location captured:</b> ${row.location_captured_at?esc(when(row.location_captured_at)):'&mdash;'}</p>
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
  function renderDistanceCounts(counts={}){
    distanceCountElements.forEach(element=>{
      const count=Math.max(0,Number.parseInt(counts[element.dataset.distanceCount]??0,10)||0);
      element.textContent=`(${count})`;
      element.setAttribute('aria-label',`${count} scan${count===1?'':'s'} on selected date`);
    });
    const farCount=Math.max(0,Number.parseInt(counts.far??0,10)||0);
    distanceFarAlert.classList.toggle('hidden',farCount===0);
  }
  function renderLatestMap(scans,latestMapOfficerScans,latestOfficerScans){
    const showEveryMatchingScan=distanceOptions.some(input=>input.checked);
    const mapScans=showEveryMatchingScan?scans:latestMapOfficerScans;
    currentMapScans=mapScans;
    const matching=mapScans.map(scan=>[`scan-${scan.id}`,scan]);
    const matchingById=new Map(matching);
    renderTeamLegend(mapScans);
    const activeKeys=new Set(matchingById.keys());
    let changed=false;
    scanMarkers.forEach((entry,key)=>{
      const matchingScan=matchingById.get(key);
      const hasCoordinates=matchingScan&&matchingScan.scan_latitude!==null&&matchingScan.scan_longitude!==null;
      if(!activeKeys.has(key)||!hasCoordinates){liveLayer.removeLayer(entry.marker);scanMarkers.delete(key);changed=true;}
    });
    matching.forEach(([key,scan])=>{
      if(scan.scan_latitude===null||scan.scan_longitude===null)return;
      const latitude=Number(scan.scan_latitude),longitude=Number(scan.scan_longitude);
      const existing=scanMarkers.get(key);
      if(existing?.scanId===scan.id)return;
      if(existing)liveLayer.removeLayer(existing.marker);
      const popup=scanPopup(scan);
      const marker=L.marker([latitude,longitude],{icon:teamMarkerIcon(scan)}).bindPopup(popup,{className:`attendance-map-popup ${distanceStatusMeta(scan).className}`}).addTo(liveLayer);
      marker.bindTooltip(`<strong>${esc(officerLabel(scan))}</strong>${teamBadge(scan)}${accuracyBadge(scan)}`,{direction:'top',offset:[0,-12],className:'attendance-map-tooltip'});
      scanMarkers.set(key,{marker,scanId:scan.id,far:isFarScan(scan)});
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
      if(eventBoundaries.get(key)?.signature===signature){eventBoundaries.get(key).event=event;return;}
      if(eventBoundaries.has(key))liveLayer.removeLayer(eventBoundaries.get(key).rectangle);
      const latitudeDelta=radius/111320;
      const longitudeDelta=radius/(111320*Math.max(Math.cos(latitude*Math.PI/180),0.000001));
      const bounds=[[latitude-latitudeDelta,longitude-longitudeDelta],[latitude+latitudeDelta,longitude+longitudeDelta]];
      const rectangle=L.rectangle(bounds,{color:'#397565',weight:2,opacity:.9,dashArray:'8 6',fillColor:'#C6F24E',fillOpacity:.13})
        .bindPopup(`<strong>${esc(event.venue_name||'Event venue')}</strong><br>${esc(event.title)}<br>Square boundary: ${radius.toFixed(0)} m from center`)
        .addTo(liveLayer);
      eventBoundaries.set(key,{rectangle,signature,event});
      changed=true;
    });
    if(focusedScanId!==null){
      setTimeout(()=>{scanMap.invalidateSize();renderFarIndicators();},0);
      return;
    }
    const located=matching.filter(([,scan])=>scan.scan_latitude!==null&&scan.scan_longitude!==null);
    const officerCount=new Set(mapScans.map(scan=>scan.officer_id===null?`unknown-${scan.id}`:String(scan.officer_id))).size;
    mapUpdated.textContent=matching.length
      ? showEveryMatchingScan
        ? `${located.length} of ${matching.length} matching scan locations from ${officerCount} recorder${officerCount===1?'':'s'} shown · checked ${new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date())}`
        : `${located.length} of ${matching.length} recent scan position${matching.length===1?'':'s'} shown · checked ${new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date())}`
      : `${scheduledEvents.length} event${scheduledEvents.length===1?'':'s'} scheduled · no scans`;
    const hasMapContent=located.length>0||eventBoundaries.size>0;
    mapEmpty.classList.toggle('hidden',hasMapContent);
    mapEmptyMessage.textContent=matching.length
      ? 'The matching scans did not include GPS coordinates.'
      : 'Matching attendance scans will appear here.';
    mapDetails.classList.toggle('hidden',latestOfficerScans.length===0);
    mapDetails.classList.toggle('flex',latestOfficerScans.length>0);
    const recentPositionByOfficer=new Map();
    mapDetails.innerHTML=[...latestOfficerScans].sort((left,right)=>String(right.scanned_at).localeCompare(String(left.scanned_at))||Number(right.id)-Number(left.id)).map(scan=>{
      const officerKey=scan.officer_id===null?`unknown-${scan.id}`:String(scan.officer_id);
      const recentPosition=(recentPositionByOfficer.get(officerKey)||0)+1;
      recentPositionByOfficer.set(officerKey,recentPosition);
      const coordinates=scan.scan_latitude!==null&&scan.scan_longitude!==null;
      const statusClass=scan.location_status==='inside'?'text-[#397565]':scan.location_status==='outside'?'text-[#D64A12]':'text-[#121017]/50';
      const locate=coordinates
        ? `<button class="shrink-0 text-[10px] font-black text-[#397565] hover:underline" type="button" data-locate-map-scan="${esc(scan.id)}" aria-label="View ${esc(officerLabel(scan))} on the map">View Location &rarr;</button>`
        : '<span class="shrink-0 text-[10px] text-[#121017]/35">No GPS</span>';
      return `<article class="w-72 shrink-0 rounded-xl border border-[#121017]/8 bg-[#F3F0E9]/45 px-3 py-2.5" style="border-left:4px solid ${distanceStatusMeta(scan).color}"><div class="flex items-center justify-between gap-3"><b class="min-w-0 truncate text-xs">${esc(officerLabel(scan))}</b>${locate}</div><div class="mt-1 flex items-center justify-between gap-3">${teamBadge(scan)}<span class="shrink-0 text-[10px] font-black ${statusClass}">${coordinates?esc(scan.location_status):'GPS unavailable'}</span></div><span class="mt-1 block truncate text-[10px] text-[#121017]/50">${recentPosition===1?'Latest':'Recent scan '+recentPosition}: ${esc(scan.student_name)} &middot; ${esc(when(scan.scanned_at))}</span><span class="mt-0.5 flex min-w-0 items-center text-[10px] text-[#121017]/50"><span class="min-w-0 truncate">Venue: ${esc(scan.venue_name_snapshot||'Not configured')}</span>${venueStatusTag(scan)}</span></article>`;
    }).join('');
    if(changed&&hasMapContent){
      const layers=[...scanMarkers.values()].filter(entry=>!entry.far).map(entry=>entry.marker).concat([...eventBoundaries.values()].map(entry=>entry.rectangle));
      if(layers.length){
        const bounds=L.featureGroup(layers).getBounds();
        scanMap.fitBounds(bounds,{padding:[35,35],maxZoom:21});
      }
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
  function focusScan(scan,includeVenueBoundary=true){
    if(!scan||scan.scan_latitude===null||scan.scan_longitude===null)return;
    focusedScanId=String(scan.id);
    if(!scanMap.hasLayer(liveLayer))liveLayer.addTo(scanMap);
    focusLayer.clearLayers();
    if(!scanMap.hasLayer(focusLayer))focusLayer.addTo(scanMap);
    const latitude=Number(scan.scan_latitude),longitude=Number(scan.scan_longitude);
    const popup=scanPopup(scan);
    const marker=L.marker([latitude,longitude],{icon:teamMarkerIcon(scan)}).bindPopup(popup,{className:`attendance-map-popup ${distanceStatusMeta(scan).className}`}).addTo(focusLayer);
    marker.bindTooltip(`<strong>${esc(officerLabel(scan))}</strong>${teamBadge(scan)}${accuracyBadge(scan)}`,{direction:'top',offset:[0,-12],className:'attendance-map-tooltip'});
    const boundary=includeVenueBoundary?scanBoundary(scan):null;
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
    mapUpdated.textContent=`Focused scan by ${officerLabel(scan)}`;
    mapSection.scrollIntoView({behavior:'smooth',block:'start'});
    setTimeout(()=>{scanMap.invalidateSize();renderFarIndicators();},0);
  }
  function resetMapFocus(){
    focusedScanId=null;
    if(scanMap.hasLayer(focusLayer))scanMap.removeLayer(focusLayer);
    focusLayer.clearLayers();
    if(!scanMap.hasLayer(liveLayer))liveLayer.addTo(scanMap);
    mapReset.classList.add('hidden');
    renderLatestMap(lastMapScans,lastLatestMapOfficerScans,lastLatestOfficerScans);
    const layers=[...scanMarkers.values()].filter(entry=>!entry.far).map(entry=>entry.marker).concat([...eventBoundaries.values()].map(entry=>entry.rectangle));
    if(layers.length){
      const bounds=L.featureGroup(layers).getBounds();
      if(bounds.isValid())scanMap.fitBounds(bounds,{padding:[35,35],maxZoom:21});
    }
    render(lastScans);
  }
  async function load({poll=false}={}){
    const id=++request;
    if(activeController)activeController.abort();
    const controller=new AbortController();
    activeController=controller;
    try{
      const filters=Object.fromEntries(new FormData(form));
      delete filters['distance_group[]'];
      filters.distance_group=distanceOptions.filter(input=>input.checked).map(input=>input.value);
      filters.include_options=optionsReady?'0':'1';
      if(poll&&lastDataVersion)filters.data_version=lastDataVersion;
      const response=await axios.get('api/adviser-attendance-scans.php',{params:filters,signal:controller.signal});
      if(id!==request)return;
      const data=response.data.data;
      lastDataVersion=data.data_version||lastDataVersion;
      if(data.not_modified)return;
      if(!optionsReady&&data.options)fill(data.options);
      const scans=data.scans;
      const mapScans=data.map_scans||scans;
      const latestMapOfficerScans=data.latest_map_officer_scans||mapScans;
      const latestOfficerScans=data.latest_officer_scans||mapScans;
      lastScans=scans;
      lastMapScans=mapScans;
      lastLatestMapOfficerScans=latestMapOfficerScans;
      lastLatestOfficerScans=latestOfficerScans;
      if(focusedScanId!==null&&!scans.some(scan=>String(scan.id)===focusedScanId))resetMapFocus();
      renderLatestMap(mapScans,latestMapOfficerScans,latestOfficerScans);
      render(scans);
      renderDistanceCounts(data.distance_counts);
      renderPagination(data.pagination);
      syncResetFilters();
    }catch(error){
      if(id===request&&error.code!=='ERR_CANCELED'&&error.name!=='CanceledError')list.textContent=error.response?.data?.message||'Scan locations could not be loaded.';
    }finally{
      if(id===request)activeController=null;
    }
  }
  const schedulePoll=()=>{clearInterval(pollTimer);pollTimer=setInterval(()=>{if(!document.hidden&&scanDetails?.open)load({poll:true});},5000);};
  const syncDistanceFilter=changed=>{
    if(changed===distanceAll&&distanceAll.checked)distanceOptions.forEach(input=>input.checked=false);
    if(distanceOptions.includes(changed)&&changed.checked)distanceAll.checked=false;
    const selected=distanceOptions.filter(input=>input.checked);
    if(!selected.length)distanceAll.checked=true;
    distanceLabel.textContent=distanceAll.checked?'5 recent scans per recorder':selected.length===1?selected[0].closest('label').querySelector('[data-distance-option-text]').textContent.trim():`${selected.length} distance groups`;
  };
  distanceFilter.addEventListener('change',event=>syncDistanceFilter(event.target));
  document.addEventListener('click',event=>{if(distanceFilter.open&&!distanceFilter.contains(event.target))distanceFilter.removeAttribute('open');});
  form.addEventListener('change',event=>{if(event.target===dateInput||event.target===form.elements.event_type_id)syncEventOptions();if(event.target.name!=='page')form.elements.page.value='1';syncResetFilters();load();});
  searchInput.addEventListener('input',()=>{
    clearTimeout(searchTimer);
    form.elements.page.value='1';
    syncResetFilters();
    searchTimer=setTimeout(load,300);
  });
  resetFilters.addEventListener('click',()=>{
    clearTimeout(searchTimer);
    form.reset();
    dateInput.value=todayIso();
    searchInput.value='';
    form.elements.page.value='1';
    distanceAll.checked=true;
    distanceOptions.forEach(input=>input.checked=false);
    distanceFilter.removeAttribute('open');
    syncDistanceFilter(distanceAll);
    syncEventOptions();
    expandedScans.clear();
    teamLegendExpanded=false;
    focusedScanId=null;
    if(scanMap.hasLayer(focusLayer))scanMap.removeLayer(focusLayer);
    focusLayer.clearLayers();
    if(!scanMap.hasLayer(liveLayer))liveLayer.addTo(scanMap);
    mapReset.classList.add('hidden');
    syncResetFilters();
    load();
  });
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
  teamLegend.addEventListener('click',event=>{
    const button=event.target.closest('[data-team-legend-toggle]');
    if(!button)return;
    teamLegendExpanded=!teamLegendExpanded;
    renderTeamLegend(currentMapScans);
  });
  mapDetails.addEventListener('click',event=>{
    const button=event.target.closest('[data-locate-map-scan]');
    if(!button)return;
    const scan=lastLatestOfficerScans.find(item=>String(item.id)===button.dataset.locateMapScan)||lastMapScans.find(item=>String(item.id)===button.dataset.locateMapScan);
    if(scan)focusScan(scan);
  });
  pagination.addEventListener('click',event=>{
    const button=event.target.closest('[data-scan-page]');
    if(!button||button.disabled)return;
    form.elements.page.value=button.dataset.scanPage;
    load();
    list.scrollIntoView({behavior:'smooth',block:'start'});
  });
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&scanDetails?.open)load({poll:true});});
  addEventListener('beforeunload',()=>{clearInterval(pollTimer);clearTimeout(searchTimer);activeController?.abort();});
  syncDistanceFilter(distanceAll);
  syncResetFilters();
  schedulePoll();
});
