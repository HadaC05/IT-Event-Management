(() => {
  'use strict';
  const isFaculty=document.body.dataset.navigationRole==='faculty';
  const API=isFaculty?'api/faculty.php?page=attendance':'api/sbo-attendance.php';
  const $=selector=>document.querySelector(selector);
  const selector=$('[data-assignment-selector]'),video=$('[data-scanner-video]'),cameraButton=$('[data-camera-toggle]');
  const dialog=$('[data-scanner-dialog]'),openButton=$('[data-scanner-open]'),preview=$('[data-scanner-preview]');
  const switchButton=$('[data-camera-switch]'),flashButton=$('[data-flash-toggle]'),manualButton=$('[data-manual-submit]');
  const locationRefresh=$('[data-location-refresh]'),gpsToggle=$('[data-gps-toggle]'),gpsPolicyDialog=$('[data-gps-policy-dialog]');
  const esc=value=>(isFaculty?window.SharedNavigation:window.SboPortal).escapeHtml(value);
  const notify=(type,message,options)=>window.Notifications?.[type]?.(message,options);
  let csrf='',assignments=[],selected=null,scanner=null,QrScanner=null,cameras=[],cameraIndex=0,position=null;
  let locationReason='not_provided',locationRequest=null,locationWatchId=null,gpsEnabled=false,gpsGeneration=0,gpsManuallyDisabled=false,isProcessing=false,cooldownUntil=0,lastToken='',lastTokenAt=0,lastTokenIgnoreMs=3000,lastScanToast=null,contextTimer=0,startGeneration=0,cameraHintTimer=0;
  let qrModulePromise=null,checkpoint='in',decodeErrorReported=false;
  let locationRefreshTimer=0;
  const shownGpsNotices=new Map();
  const time=value=>value?new Intl.DateTimeFormat('en-PH',{hour:'numeric',minute:'2-digit'}).format(new Date('2000-01-01T'+value)):'—';
  const date=value=>value?new Intl.DateTimeFormat('en-PH',{dateStyle:'medium'}).format(new Date(value.replace(' ','T'))):'—';
  const clock=value=>value?new Intl.DateTimeFormat('en-PH',{timeStyle:'short',timeZone:'Asia/Manila'}).format(new Date(value.replace(' ','T')+'+08:00')):'—';
  const scanNotice=(type,message,options={})=>{lastScanToast?.remove();lastScanToast=notify(type,message,options);};
  const coords=value=>Number(value).toFixed(6);
  const insideBox=(latitude,longitude,centerLatitude,centerLongitude,radius)=>{
    const latitudeDelta=radius/111320;
    const longitudeScale=Math.max(Math.cos(centerLatitude*Math.PI/180),0.000001);
    const longitudeDelta=radius/(111320*longitudeScale);
    const rawDifference=Math.abs(longitude-centerLongitude)%360;
    const longitudeDifference=rawDifference>180?360-rawDifference:rawDifference;
    return Math.abs(latitude-centerLatitude)<=latitudeDelta&&longitudeDifference<=longitudeDelta;
  };
  const boxProximity=(latitude,longitude,centerLatitude,centerLongitude,radius)=>{
    const latitudeMeters=Math.abs(latitude-centerLatitude)*111320;
    const rawLongitudeDifference=Math.abs(longitude-centerLongitude)%360;
    const longitudeDegrees=rawLongitudeDifference>180?360-rawLongitudeDifference:rawLongitudeDifference;
    const longitudeMeters=longitudeDegrees*111320*Math.max(Math.cos(centerLatitude*Math.PI/180),0.000001);
    const latitudeOutside=Math.max(0,latitudeMeters-radius);
    const longitudeOutside=Math.max(0,longitudeMeters-radius);
    return {
      inside:latitudeOutside===0&&longitudeOutside===0,
      outsideMeters:Math.hypot(latitudeOutside,longitudeOutside),
      centerMeters:Math.hypot(latitudeMeters,longitudeMeters),
    };
  };
  const freshPosition=()=>{
    if(!position)return false;
    const age=Date.now()-position.timestamp;
    return age>=-10000&&age<=30000;
  };
  const configuredVenues=()=>{
    const venues=Array.isArray(selected?.venues)&&selected.venues.length?selected.venues:[{id:selected?.location_id,name:selected?.venue_name,latitude:selected?.venue_latitude,longitude:selected?.venue_longitude,radius:selected?.venue_radius_m,is_primary:true}];
    return venues.filter(venue=>venue.latitude!==null&&venue.latitude!==undefined&&venue.longitude!==null&&venue.longitude!==undefined&&venue.radius!==null&&venue.radius!==undefined);
  };
  const detectedVenue=()=>{
    if(!freshPosition())return null;
    const matches=configuredVenues().map(venue=>({venue,proximity:boxProximity(position.latitude,position.longitude,Number(venue.latitude),Number(venue.longitude),Number(venue.radius))}));
    const inside=matches.filter(match=>match.proximity.inside).sort((left,right)=>left.proximity.centerMeters-right.proximity.centerMeters);
    matches.sort((left,right)=>left.proximity.centerMeters-right.proximity.centerMeters);
    return inside[0]||matches[0]||null;
  };
  const insideSelectedVenue=()=>Boolean(detectedVenue()?.proximity.inside);
  const loadQrModule=()=>{
    if(!qrModulePromise)qrModulePromise=import(new URL('js/vendor/qr-scanner/qr-scanner.min.js',document.baseURI))
      .then(module=>module.default).catch(error=>{qrModulePromise=null;throw error;});
    return qrModulePromise;
  };

  function renderLocation(){
    const locationCard=$('[data-location-card]');
    if(selected?.assignment_state==='upcoming'){
      locationCard.innerHTML='<p class="rounded-xl border border-[#397565]/15 bg-[#397565]/5 p-3 text-sm leading-5 text-[#397565]">GPS and venue checks begin when this assignment becomes active.</p>';
      updateControls();
      return;
    }
    const detailsOpen=Boolean(locationCard.querySelector('details')?.open);
    const match=detectedVenue();
    const venue=match?.venue.name||selected?.venue_name||selected?.location||'Not configured';
    const hasVenueBoundary=Boolean(match);
    const proximity=match?.proximity||null;
    const venueDistance=proximity?(proximity.inside?' (At venue)':` (${Math.max(1,Math.round(proximity.outsideMeters)).toLocaleString('en-US')} m outside venue)`):'';
    let status=!window.isSecureContext?'HTTPS required for GPS':locationReason==='checking'?'Checking GPS…':'GPS required';
    if(freshPosition()){
      if(!configuredVenues().length)
        status='Venue boundary not configured';
      else status=match?.proximity.inside?'Inside an event location boundary':'Outside all event location boundaries';
    }
    const isInside=status==='Inside an event location boundary';
    const isOutside=status==='Outside all event location boundaries';
    const statusStyle=isInside?'border-[#397565]/30 bg-[#397565]/10 text-[#397565]':isOutside?'border-[#FF6B2C]/30 bg-[#FF6B2C]/10 text-[#D64A12]':'border-[#121017]/10 bg-[#F3F0E9] text-[#121017]/70';
    const statusGuidance=isInside?`Your GPS position is inside ${venue}.`:isOutside
      ?selected?.location_policy==='strict'?'You are outside every event venue. Move inside one before scanning attendance.':`You are outside all event venues. Warning mode allows scanning and ${venue} will be recorded as the nearest venue.`
      :'';
    const reason=!window.isSecureContext?'This HTTP address cannot access phone GPS or camera. Open the system through a trusted HTTPS address.':locationReason==='location_timeout'?'No fresh GPS reading arrived before the request timed out.':locationReason==='permission_denied'?'Location permission was denied. Allow location for this site in Safari and iPhone settings.':locationReason==='stale_location'?'The device returned only an outdated GPS reading.':locationReason==='not_provided'?'Location has not been checked.':'Location is unavailable.';
    const note=locationReason==='checking'?'Checking location in the background.':
      freshPosition()?'Live GPS is active. Accuracy depends on your device.':window.isSecureContext?`${reason} Turn on or refresh GPS to enable attendance scanning.`:reason;
    locationCard.innerHTML=`<div class="rounded-xl border p-3 ${statusStyle}"><p><strong>Status:</strong> ${esc(status)}</p>${statusGuidance?`<p class="mt-1 leading-5">${esc(statusGuidance)}</p>`:''}</div>
      <p class="text-[#121017]/60">${esc(note)}</p>
      <details class="mt-2 rounded-xl border border-[#121017]/10 p-3" ${detailsOpen?'open':''}><summary class="cursor-pointer font-bold text-[#397565]">View location details</summary>
        <div class="mt-2 grid gap-1 text-[#121017]/70"><p><strong>${isInside?'Detected':'Nearest'} venue:</strong> ${esc(venue+venueDistance)}</p>
        <p><strong>Latitude:</strong> ${position?coords(position.latitude):'—'}</p>
        <p><strong>Longitude:</strong> ${position?coords(position.longitude):'—'}</p>
        <p><strong>Accuracy:</strong> ${position?'±'+Number(position.accuracy).toFixed(0)+' meters':'—'}</p>
        <p><strong>Last GPS update:</strong> ${position?new Intl.DateTimeFormat('en-PH',{timeStyle:'medium'}).format(new Date(position.timestamp)):'—'}</p></div>
      </details>`;
    updateControls();
  }
  function updateControls(){
    const active=Boolean(selected?.[`${checkpoint}_window_open`]);
    const boundaryConfigured=configuredVenues().length>0;
    const gpsBlocked=!freshPosition()||!boundaryConfigured;
    const strictBlocked=selected?.location_policy==='strict'&&!insideSelectedVenue();
    const locationBlocked=gpsBlocked||strictBlocked;
    openButton.disabled=!active||locationBlocked||!window.isSecureContext||!navigator.mediaDevices;
    cameraButton.disabled=openButton.disabled;
    manualButton.disabled=!active||locationBlocked;
    gpsToggle.hidden=!selected||selected.assignment_state==='upcoming';
    gpsToggle.textContent=gpsEnabled?'Turn Off GPS':window.isSecureContext?'Turn On GPS':'GPS needs HTTPS';
    gpsToggle.classList.toggle('bg-[#397565]',!gpsEnabled);
    gpsToggle.classList.toggle('bg-[#FF6B2C]',gpsEnabled);
    locationRefresh.hidden=!gpsEnabled;
    if(!window.isSecureContext)$('[data-camera-status]').textContent='Camera: HTTPS is required on mobile devices.';
    if(locationBlocked)$('[data-camera-message]').textContent=!freshPosition()
      ?'Turn on GPS and wait for a current location before attendance scanning.'
      :!boundaryConfigured
        ?'The event location boundary is not configured.'
        :'Move inside one of the event location boxes before strict attendance scanning.';
    renderPhaseStatus();
  }
  function getLocation(force=false){
    if(!gpsEnabled)return Promise.resolve(null);
    if(!window.isSecureContext){position=null;locationReason='insecure_context';renderLocation();return Promise.resolve(null);}
    if(!force&&freshPosition())return Promise.resolve(position);
    if(locationRequest)return locationRequest;
    if(!navigator.geolocation){position=null;locationReason='geolocation_unavailable';renderLocation();return Promise.resolve(null);}
    const generation=gpsGeneration;
    if(locationWatchId===null){
      locationWatchId=navigator.geolocation.watchPosition(result=>{
        if(!gpsEnabled||generation!==gpsGeneration)return;
        position={latitude:result.coords.latitude,longitude:result.coords.longitude,accuracy:result.coords.accuracy,timestamp:result.timestamp};
        locationReason='';renderLocation();
      },error=>{
        if(!gpsEnabled||generation!==gpsGeneration)return;
        locationReason=error.code===1?'permission_denied':error.code===3?'location_timeout':'location_unavailable';
        renderLocation();
      },{enableHighAccuracy:true,maximumAge:5000,timeout:20000});
    }
    locationReason='checking';renderLocation();
    locationRefresh.disabled=true;locationRefresh.textContent='Updating...';
    locationRequest=new Promise((resolve,reject)=>{
      let settled=false;
      const finish=(result,error=null)=>{
        if(settled)return;
        settled=true;window.clearTimeout(timer);
        error?reject(error):resolve(result);
      };
      const timer=window.setTimeout(()=>finish(null,{code:3}),8000);
      try{
        navigator.geolocation.getCurrentPosition(result=>finish(result),error=>finish(null,error),{
          enableHighAccuracy:true,maximumAge:5000,timeout:7000,
        });
      }catch(error){finish(null,error);}
    }).then(result=>{
      if(!gpsEnabled||generation!==gpsGeneration)return null;
      position={latitude:result.coords.latitude,longitude:result.coords.longitude,accuracy:result.coords.accuracy,timestamp:result.timestamp};
      locationReason='';renderLocation();return position;
    }).catch(error=>{
      if(!gpsEnabled||generation!==gpsGeneration)return null;
      locationReason=error.code===1?'permission_denied':error.code===3?'location_timeout':'location_unavailable';
      renderLocation();return null;
    }).finally(()=>{if(generation===gpsGeneration){locationRequest=null;locationRefresh.disabled=false;locationRefresh.textContent='Refresh';}});
    return locationRequest;
  }
  function stopLocationWatch(){
    if(locationWatchId!==null)navigator.geolocation.clearWatch(locationWatchId);
    locationWatchId=null;
  }
  function stopLocationRefresh(){
    window.clearInterval(locationRefreshTimer);
    locationRefreshTimer=0;
  }
  function startLocationRefresh(){
    stopLocationRefresh();
    // watchPosition reports movement; it does not guarantee periodic readings.
    // Renew before the 30-second limit, including while the scanner is open.
    locationRefreshTimer=window.setInterval(()=>{
      if(!gpsEnabled||document.hidden||!selected?.is_session_active)return;
      renderLocation();
      if(locationRequest||locationReason==='permission_denied')return;
      if(!position||Date.now()-position.timestamp>=15000)getLocation(true);
    },5000);
  }
  function turnOffGps(manual=false){
    if(manual)gpsManuallyDisabled=true;
    gpsGeneration++;stopLocationRefresh();stopLocationWatch();locationRequest=null;gpsEnabled=false;position=null;locationReason='not_provided';
    locationRefresh.disabled=false;locationRefresh.textContent='Refresh';renderLocation();
  }
  function toggleGps(){
    if(gpsEnabled){turnOffGps(true);return;}
    if(!window.isSecureContext){
      locationReason='insecure_context';renderLocation();
      $('[data-gps-policy-title]').textContent='HTTPS is required for phone GPS';
      $('[data-gps-policy-message]').textContent='This phone is opening the scanner over HTTP. Browsers cannot share its GPS or camera with an HTTP IP address. Open this same system through a trusted HTTPS address, then allow location and camera access. The manually entered event location is only the boundary used to compare your phone’s live position.';
      if(!gpsPolicyDialog.open)gpsPolicyDialog.showModal();
      return;
    }
    gpsManuallyDisabled=false;gpsGeneration++;gpsEnabled=true;locationReason='checking';startLocationRefresh();renderLocation();getLocation(true).catch(()=>{});
  }
  async function autoStartGps(){
    if(gpsEnabled||gpsManuallyDisabled||!selected||!navigator.geolocation||!navigator.permissions?.query)return freshPosition()?position:null;
    try{
      const permission=await navigator.permissions.query({name:'geolocation'});
      if(permission.state!=='granted'||gpsEnabled||gpsManuallyDisabled)return freshPosition()?position:null;
      gpsGeneration++;gpsEnabled=true;locationReason='checking';startLocationRefresh();renderLocation();return await getLocation(true);
    }catch{return null;}
  }
  function showGpsPolicyNotice(force=false){
    const policy=selected?.location_policy;
    if(!selected||gpsPolicyDialog.open)return;
    const eventId=String(selected.event_id);
    if(!force&&shownGpsNotices.get(eventId)===policy)return;
    shownGpsNotices.set(eventId,policy);
    if(!['strict','warning'].includes(policy))return;
    $('[data-gps-policy-title]').textContent='GPS is required for this event';
    $('[data-gps-policy-message]').textContent=policy==='strict'
      ?'Turn on GPS and remain inside any event location box. Attendance scanning stays disabled when GPS is off, unavailable, or outside every boundary.'
      :'Turn on GPS before scanning attendance. Warning mode permits attendance outside all venue boxes, but GPS must remain available and the nearest venue will be recorded.';
    gpsPolicyDialog.showModal();
  }
  function locationPayload(){
    if(freshPosition())return {latitude:position.latitude,longitude:position.longitude,accuracy_m:position.accuracy,timestamp_ms:Math.round(position.timestamp)};
    return {unavailable_reason:position?'stale_location':locationReason||'not_provided'};
  }
  function renderRecent(rows){
    $('[data-recent-scans]').innerHTML=rows.length?rows.map(row=>`<article class="sbo-scan-item flex flex-wrap items-center gap-3 p-4">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#C6F24E]/35 text-xs font-black text-[#397565]">✓</span>
      <div class="min-w-0 flex-1"><strong class="block truncate text-sm">${esc(row.full_name)}</strong>
        <span class="block text-xs text-[#121017]/45">${esc(row.id_number)} · ${esc(row.team_name)} · ${esc(row.venue_name_snapshot||'Venue unavailable')}</span></div>
      <div class="text-right"><time class="block text-xs font-bold text-[#397565]">${row.phase==='out'?'Out':'In'} ${clock(row.scanned_at)}</time>
        <small class="text-xs text-[#121017]/50">${esc(row.location_status)}</small></div></article>`).join(''):'<p class="p-8 text-center text-sm text-[#121017]/45">No attendance scans yet.</p>';
  }
  function renderCounts(counts){
    const timedIn=Number(counts.total)||0, timedOut=Number(counts.checked_out)||0, remaining=Number(counts.remaining)||0;
    const scanIn=$('[data-scan-in]'),scanOut=$('[data-scan-out]'),scanRemaining=$('[data-scan-remaining]');
    if(scanIn)scanIn.textContent=timedIn.toLocaleString('en-PH');
    if(scanOut)scanOut.textContent=timedOut.toLocaleString('en-PH');
    if(scanRemaining)scanRemaining.textContent=remaining.toLocaleString('en-PH');
    const percentage=Math.round(timedIn/Math.max(1,timedIn+remaining)*100);
    const progress=$('[data-scan-progress]'),progressFill=$('[data-scan-progress-fill]');
    if(progress)progress.setAttribute('aria-valuenow',String(percentage));
    if(progressFill)progressFill.style.width=`${percentage}%`;
    $('[data-scan-counts]').textContent=`${timedIn} timed in · ${timedOut} timed out · ${remaining} remaining ${selected?.scanner_mode==='general'?'eligible students':'from your assigned team'}`;
  }
  function renderPhaseStatus(){
    const phase=checkpoint==='in'?'In':'Out';
    const message=!selected?'No attendance assignment is selected.'
      :selected.assignment_state==='upcoming'?'This assignment is upcoming. Scanning opens during its scheduled session.'
      :!selected[`${checkpoint}_window_open`]
        ?selected.is_session_active?'The other checkpoint is open. Select it above.':'No QR is scannable now. Wait for the next window or ask the adviser to extend it.'
      :!window.isSecureContext?'Open this page over HTTPS to use the camera and GPS.'
      :!configuredVenues().length?'The event venue boundary is not configured. Ask the adviser to set it.'
      :!gpsEnabled?'Time '+phase+' is open. Turn On GPS to enable attendance scanning.'
      :!freshPosition()?'Time '+phase+' is open. Waiting for a fresh GPS reading; check location permission or tap Refresh.'
      :selected.location_policy==='strict'&&!insideSelectedVenue()?'Move inside an event location boundary to scan attendance.'
      :!navigator.mediaDevices?'Time '+phase+' is open. Camera is unavailable in this browser; Record by ID remains available.'
      :'Time '+phase+' scanning is open.';
    const sessionState=$('[data-session-state]');
    if(sessionState.textContent!==message)sessionState.textContent=message;
    openButton.title=openButton.disabled?message:'';
    manualButton.title=manualButton.disabled?message:'';
  }
  function setCheckpoint(next){
    checkpoint=next;lastToken='';lastTokenAt=0;lastTokenIgnoreMs=3000;
    document.querySelectorAll('[data-checkpoint-option]').forEach(button=>{
      const active=button.dataset.checkpointOption===checkpoint;
      button.setAttribute('aria-pressed',String(active));
      button.classList.toggle('bg-[#397565]',active);button.classList.toggle('text-white',active);
      button.classList.toggle('bg-white',!active);button.classList.toggle('text-[#397565]',!active);
    });
    $('[data-checkpoint-hint]').textContent=checkpoint==='in'
      ?'Scan the student’s Time In QR while the Time In window is open.'
      :'Scan the student’s separate Time Out QR. A previous Time In is required.';
    $('[data-scanner-checkpoint]').textContent=checkpoint==='in'?'time in':'time out';
    openButton.textContent=`Open Time ${checkpoint==='in'?'In':'Out'} scanner`;
    if(dialog.open&&!selected?.[`${checkpoint}_window_open`])dialog.close();
    updateControls();
    renderPhaseStatus();
  }
  document.querySelectorAll('[data-checkpoint-option]').forEach(button=>button.addEventListener('click',()=>setCheckpoint(button.dataset.checkpointOption)));
  function renderAssignment(next){
    if(!selected)return;
    const heroState=$('[data-attendance-hero-state]'),heroDetail=$('[data-attendance-hero-detail]');
    if(heroState)heroState.textContent=selected.assignment_state==='upcoming'?'Upcoming':selected.is_session_active?'Session open':'Session closed';
    if(heroDetail)heroDetail.textContent=`${selected.event_name} · Day ${selected.day_number} · ${selected.session_name}`;
    const assignmentSummary=$('[data-assignment-summary]');
    const detailsOpen=Boolean(assignmentSummary.querySelector('details')?.open);
    const upcoming=selected.assignment_state==='upcoming';
    assignmentSummary.innerHTML=(upcoming?`<div class="sm:col-span-2 lg:col-span-3 rounded-xl border border-[#397565]/25 bg-[#C6F24E]/20 p-3 text-sm text-[#397565]"><strong>Upcoming assignment.</strong> This responsibility is for an upcoming event. Attendance scanning will be available when its scheduled session opens.</div>`:'')+[
      ['Event',selected.event_name],['Scanner access',selected.scanner_mode==='general'?'General · all teams':`Specific · ${selected.scanner_team_name}`],
      ['Event day',`Day ${selected.day_number} · ${date(selected.schedule_date)}`],
      ['Session',`${selected.session_name} · ${time(selected.session_start)}–${time(selected.session_end)}`],
    ].map(([label,value])=>`<article><span class="text-[10px] font-black uppercase tracking-wider text-[#397565]">${label}</span><strong class="mt-1 block text-sm">${esc(value)}</strong></article>`).join('')+
      `<details class="sbo-assignment-details" ${detailsOpen?'open':''}><summary>Venues and location policy</summary><p class="mt-2 text-sm">${esc((selected.venues||[]).map(venue=>venue.name).join(', ')||selected.venue_name||'Venue not configured')} · ${esc(selected.location_policy)} location policy</p></details>`;
    renderPhaseStatus();
    $('[data-next-session]').textContent=next?`Next scheduled session: ${next.event} · ${next.name} · ${date(next.starts_at)} ${clock(next.starts_at)}`:'No later attendance session is scheduled for today.';
    renderLocation();updateControls();
  }
  async function load(requested=0){
    const previousEventId=selected?.event_id??null;
    const previousPolicy=selected?.location_policy??null;
    const response=await axios.get(API,{params:requested?{assignment_id:requested}:{}});
    const data=response.data.data;assignments=data.assignments;
    $('[data-attendance-loading]').classList.add('hidden');
    $('[data-attendance-start-link]').classList.toggle('hidden',assignments.length===0);
    $('[data-no-assignment]').classList.toggle('hidden',assignments.length>0);
    $('[data-attendance-workspace]').classList.toggle('hidden',assignments.length===0);
    selector.innerHTML=assignments.map(a=>`<option value="${a.id}">${a.assignment_state==='upcoming'?'Upcoming assignment · ':''}${esc(a.event_name)} · Day ${a.day_number} · ${esc(a.session_name)} · ${esc(a.team_name)}</option>`).join('');
    $('[data-assignment-selector-wrap]').classList.toggle('hidden',assignments.length<2);
    $('[data-assignment-selector-wrap]').classList.toggle('grid',assignments.length>1);
    selected=data.selected_assignment||(requested?assignments.find(a=>a.id===Number(requested)):assignments[0])||null;
    if(selected){
      selector.value=selected.id;renderAssignment(data.next_session);renderRecent(data.recent_scans);renderCounts(data.counts);
      const policyChanged=previousEventId!==null&&Number(previousEventId)===Number(selected.event_id)&&previousPolicy!==selected.location_policy;
      if(selected.assignment_state!=='upcoming')autoStartGps().then(()=>{if(policyChanged||!freshPosition())showGpsPolicyNotice(policyChanged);}).catch(()=>showGpsPolicyNotice(policyChanged));
    }
    else{await stop();turnOffGps();updateControls();}
  }
  function successSound(){
    try{const audio=new (window.AudioContext||window.webkitAudioContext)();const tone=audio.createOscillator();const gain=audio.createGain();
      tone.type='sine';tone.frequency.value=740;tone.connect(gain);gain.connect(audio.destination);
      gain.gain.setValueAtTime(0.08,audio.currentTime);gain.gain.exponentialRampToValueAtTime(0.001,audio.currentTime+0.16);
      tone.start();tone.stop(audio.currentTime+0.16);tone.onended=()=>audio.close();}catch{}
  }
  async function submit(payload){
    if(isProcessing||Date.now()<cooldownUntil||!selected?.[`${checkpoint}_window_open`])return false;
    isProcessing=true;
    try{
      await getLocation();
      if(!freshPosition()){
        const message='Your location could not be verified. Check your location permission and try again.';
        $('[data-scan-result]').textContent=message;
        $('[data-modal-scan-result]').dataset.state='error';
        $('[data-modal-scan-result]').textContent=message;
        scanNotice('error',message,{title:'Scan not recorded',duration:7000});lastTokenIgnoreMs=1000;return false;
      }
      const response=await axios.post(API,{...payload,assignment_id:selected.id,checkpoint,location:locationPayload()},{headers:{'X-CSRF-Token':csrf}});
      const result=response.data.data;renderRecent(result.recent_scans);renderCounts(result.counts);
      const location=result.location.status==='unavailable'?'unavailable':`${result.location.status} · ${result.location.venue_name||'Venue unavailable'}${result.location.distance_m===null?'':' · '+result.location.distance_m+' meters from venue center'}`;
      const label=result.checkpoint==='out'?'Time out':'Time in';
      $('[data-scan-result]').innerHTML=`<strong class="text-[#397565]">${label} recorded successfully.</strong><br>${esc(result.student.full_name)} · ${esc(result.student.id_number)} · ${esc(result.student.team_name)}<br>${esc(result.session_name)} · ${clock(result.scanned_at)}<br>Location: ${esc(location)}`;
      $('[data-modal-scan-result]').dataset.state='success';
      $('[data-modal-scan-result]').innerHTML=`<strong>${esc(label)} recorded</strong><br>${esc(result.student.full_name)} · ${esc(result.student.id_number)}<br>${esc(result.student.team_name)} · ${clock(result.scanned_at)}`;
      scanNotice('success','Attendance saved.',{title:`${label} recorded`,duration:8000,details:[
        {label:'Name',value:result.student.full_name},
        {label:'Student ID',value:result.student.id_number},
        {label:'Team',value:result.student.team_name},
        {label:result.location.venue_match==='matched'?'Detected venue':'Nearest venue',value:result.location.venue_name||'Unavailable'},
        {label:'Time',value:clock(result.scanned_at)},
      ]});
      if(payload.mode==='qr'){lastToken=payload.token;lastTokenAt=Date.now();lastTokenIgnoreMs=30000;}
      successSound();return true;
    }catch(error){
      const message=error.response?.data?.message||'Attendance scan failed.';
      $('[data-scan-result]').textContent=message;scanNotice(error.response?.status===409?'warning':'error',message,{title:error.response?.status===409?'Already recorded':'Scan not recorded',duration:7000});
      $('[data-modal-scan-result]').dataset.state='error';
      $('[data-modal-scan-result]').textContent=message;
      lastTokenIgnoreMs=1000;
      return false;
    }finally{cooldownUntil=Date.now()+1000;setTimeout(()=>isProcessing=false,1000);}
  }
  async function onDecode(result){
    const token=String(result?.data||'').trim();
    if(!token||isProcessing||Date.now()<cooldownUntil)return;
    if(token===lastToken&&Date.now()-lastTokenAt<lastTokenIgnoreMs)return;
    lastToken=token;lastTokenAt=Date.now();lastTokenIgnoreMs=3000;
    await submit({mode:'qr',token});
  }
  async function start(){
    if(!dialog.open||scanner||!selected?.[`${checkpoint}_window_open`])return;
    const generation=++startGeneration;
    cameraButton.hidden=true;cameraButton.disabled=true;
    preview.classList.remove('is-active');preview.classList.add('is-loading');
    $('[data-camera-status]').textContent='Preparing scanner…';
    $('[data-camera-message]').textContent='Opening camera…';
    if(!window.isSecureContext){notify('error','Open this page over HTTPS on your phone to use camera and location.');await stop();return;}
    if(!freshPosition()){
      $('[data-modal-scan-result]').textContent='Camera can open now; GPS must be verified before attendance is recorded.';
      getLocation().catch(()=>{});
    }
    try{
      if(!QrScanner)QrScanner=await loadQrModule();
      if(generation!==startGeneration||!dialog.open)return;
      decodeErrorReported=false;
      scanner=new QrScanner(video,onDecode,{preferredCamera:'environment',maxScansPerSecond:10,highlightScanRegion:true,highlightCodeOutline:true,returnDetailedScanResult:true,onDecodeError:error=>{
        if(error===QrScanner.NO_QR_CODE_FOUND||decodeErrorReported)return;
        decodeErrorReported=true;
        console.error('Attendance QR decoder failed',error);
        $('[data-camera-status]').textContent='Camera active; QR reader unavailable';
        $('[data-camera-message]').textContent='The QR reader could not start. Close the scanner and try again.';
        scanNotice('error','The QR reader could not start. Close the scanner and try again.',{title:'QR scanner unavailable',duration:8000});
      }});
      const current=scanner;
      $('[data-camera-status]').textContent='Waiting for camera…';
      cameraHintTimer=setTimeout(()=>{
        if(generation===startGeneration&&dialog.open&&preview.classList.contains('is-loading')){
          $('[data-camera-status]').textContent='Waiting for camera permission…';
          $('[data-camera-message]').textContent='Allow camera access in your browser to continue.';
        }
      },4000);
      await current.start();
      if(generation!==startGeneration||!dialog.open){if(scanner===current){current.destroy();scanner=null;}return;}
      clearTimeout(cameraHintTimer);cameraHintTimer=0;
      cameraButton.textContent='Pause camera';cameraButton.hidden=false;cameraButton.disabled=false;
      preview.classList.remove('is-loading');preview.classList.add('is-active');
      if(!decodeErrorReported){
        $('[data-camera-status]').textContent='Camera: active';
        $('[data-camera-message]').textContent='Point the student QR token inside the highlighted region.';
      }
      try{cameras=await QrScanner.listCameras();cameraIndex=0;if(scanner===current)switchButton.hidden=cameras.length<2;}catch{cameras=[];switchButton.hidden=true;}
      try{if(scanner===current)flashButton.hidden=!(await current.hasFlash());}catch{flashButton.hidden=true;}
    }catch(error){if(generation===startGeneration){notify('error','Camera is unavailable: '+(error?.message||'check permission.'));await stop();$('[data-camera-status]').textContent='Camera unavailable';$('[data-camera-message]').textContent='Allow camera access, then retry.';$('[data-modal-scan-result]').dataset.state='error';$('[data-modal-scan-result]').textContent='Camera could not start. Check permission and retry.';}}
    finally{clearTimeout(cameraHintTimer);cameraHintTimer=0;if(generation===startGeneration)updateControls();}
  }
  async function stop(){
    startGeneration++;
    clearTimeout(cameraHintTimer);cameraHintTimer=0;
    if(scanner){scanner.destroy();scanner=null;}
    preview.classList.remove('is-active','is-loading');
    cameraButton.textContent='Retry camera';cameraButton.hidden=!dialog.open;switchButton.hidden=true;flashButton.hidden=true;
    flashButton.textContent='Flashlight';
    $('[data-camera-status]').textContent='Camera: off';$('[data-camera-message]').textContent='Point the QR code inside the frame.';
    updateControls();
  }
  openButton.addEventListener('click',async()=>{if(openButton.disabled)return;dialog.showModal();$('[data-modal-scan-result]').dataset.state='';$('[data-modal-scan-result]').textContent='Ready to scan.';await start();});
  $('[data-scanner-close]').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>stop());
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
  cameraButton.addEventListener('click',()=>scanner?stop():start());
  switchButton.addEventListener('click',async()=>{if(!scanner||cameras.length<2)return;cameraIndex=(cameraIndex+1)%cameras.length;await scanner.setCamera(cameras[cameraIndex].id);flashButton.hidden=!(await scanner.hasFlash());});
  flashButton.addEventListener('click',async()=>{if(!scanner)return;await scanner.toggleFlash();flashButton.textContent=scanner.isFlashOn()?'Flashlight on':'Flashlight';});
  locationRefresh.addEventListener('click',()=>getLocation(true));
  gpsToggle.addEventListener('click',toggleGps);
  $('[data-gps-policy-confirm]').addEventListener('click',()=>gpsPolicyDialog.close());
  gpsPolicyDialog.addEventListener('click',event=>{if(event.target===gpsPolicyDialog)gpsPolicyDialog.close();});
  selector.addEventListener('change',async()=>{if(dialog.open)dialog.close();await stop();turnOffGps();await load(Number(selector.value));});
  $('[data-manual-form]').addEventListener('submit',async event=>{event.preventDefault();const field=event.currentTarget.elements.student_id;const value=field.value.trim();if(!value)return;if(await submit({mode:'manual',student_id:value}))field.value='';});
  window.addEventListener('pagehide',()=>{clearInterval(contextTimer);stopLocationRefresh();stopLocationWatch();if(dialog.open)dialog.close();stop();});
  document.addEventListener('visibilitychange',()=>{
    if(document.hidden){stopLocationRefresh();stopLocationWatch();if(dialog.open)dialog.close();stop();}
    else if(gpsEnabled){startLocationRefresh();if(locationReason!=='permission_denied')getLocation(true);}
  });
  const initialize=isFaculty?window.SharedNavigation.ready:window.SboPortal.initialize('attendance');
  initialize.then(async context=>{csrf=context.csrfToken;const accountMenu=document.querySelector(isFaculty?'[data-shared-account-menu]':'[data-sbo-account-menu]');if(accountMenu){accountMenu.classList.remove('ml-auto');gpsToggle.classList.add('ml-auto');accountMenu.before(gpsToggle);}await load();if(selected?.is_session_active)loadQrModule().catch(()=>{});contextTimer=setInterval(async()=>{if(isProcessing)return;const old=selected?.id,wasActive=selected?.is_session_active;try{await load(old||0);if(!wasActive&&selected?.is_session_active)loadQrModule().catch(()=>{});if(!selected?.is_session_active){if(dialog.open)dialog.close();await stop();}}catch(error){console.error('Attendance refresh failed',error);notify('error',error.response?.data?.message||'Attendance could not refresh. Reload the page and try again.');}},30000);})
    .catch(error=>{console.error('Attendance initialization failed',error);$('[data-attendance-loading]').classList.add('hidden');$('[data-attendance-start-link]').classList.add('hidden');openButton.disabled=true;manualButton.disabled=true;const message=error.response?.data?.message||'Attendance could not load. Reload the page and try again.';notify('error',message);const alert=document.createElement('p');alert.setAttribute('role','alert');alert.className='mb-4 rounded-xl border border-[#FF6B2C]/30 bg-[#FF6B2C]/10 p-4 text-sm font-bold text-[#9A360C]';alert.textContent=message;document.querySelector('main')?.prepend(alert);});
})();
