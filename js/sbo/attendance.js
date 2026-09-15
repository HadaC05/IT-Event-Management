(() => {
  'use strict';
  const API='api/sbo-attendance.php';
  const $=selector=>document.querySelector(selector);
  const selector=$('[data-assignment-selector]'),video=$('[data-scanner-video]'),cameraButton=$('[data-camera-toggle]');
  const dialog=$('[data-scanner-dialog]'),openButton=$('[data-scanner-open]'),preview=$('[data-scanner-preview]');
  const switchButton=$('[data-camera-switch]'),flashButton=$('[data-flash-toggle]'),manualButton=$('[data-manual-submit]');
  const esc=value=>window.SboPortal.escapeHtml(value);
  const notify=(type,message)=>window.Notifications?.[type]?.(message);
  let csrf='',assignments=[],selected=null,scanner=null,QrScanner=null,cameras=[],cameraIndex=0,position=null;
  let locationReason='not_provided',locationRequest=null,isProcessing=false,cooldownUntil=0,lastToken='',lastTokenAt=0,contextTimer=0,startGeneration=0,cameraHintTimer=0;
  let qrModulePromise=null,checkpoint='in';
  const time=value=>value?new Intl.DateTimeFormat('en-PH',{hour:'numeric',minute:'2-digit'}).format(new Date('2000-01-01T'+value)):'—';
  const date=value=>value?new Intl.DateTimeFormat('en-PH',{dateStyle:'medium'}).format(new Date(value.replace(' ','T'))):'—';
  const clock=value=>value?new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date(value.replace(' ','T'))):'—';
  const coords=value=>Number(value).toFixed(6);
  const approximateDistance=(lat1,lon1,lat2,lon2)=>{
    const rad=Math.PI/180,dLat=(lat2-lat1)*rad,dLon=(lon2-lon1)*rad;
    const h=Math.sin(dLat/2)**2+Math.cos(lat1*rad)*Math.cos(lat2*rad)*Math.sin(dLon/2)**2;
    return 6371000*2*Math.asin(Math.min(1,Math.sqrt(h)));
  };
  const freshPosition=()=>Boolean(position&&Date.now()-position.timestamp<=30000);
  const loadQrModule=()=>{
    if(!qrModulePromise)qrModulePromise=import(new URL('js/vendor/qr-scanner/qr-scanner.min.js',document.baseURI))
      .then(module=>module.default).catch(error=>{qrModulePromise=null;throw error;});
    return qrModulePromise;
  };

  function renderLocation(){
    const venue=selected?.venue_name||selected?.location||'Not configured';
    let status=locationReason==='checking'?'Checking GPS…':selected?.location_policy==='off'?'Not required':selected?.location_policy==='strict'?'GPS required':'GPS unavailable';
    if(freshPosition()){
      if(selected?.venue_latitude===null||selected?.venue_longitude===null||selected?.venue_radius_m===null)
        status='Venue boundary not configured';
      else status=approximateDistance(position.latitude,position.longitude,selected.venue_latitude,selected.venue_longitude)<=selected.venue_radius_m?
        'Approximately inside event venue':'Approximately outside event venue';
    }
    const reason=locationReason==='location_timeout'?'Location timed out.':locationReason==='permission_denied'?'Location permission was denied.':locationReason==='not_provided'?'Location has not been checked.':'Location is unavailable.';
    const note=selected?.location_policy==='off'?'GPS is off for this event; scanning does not need location.':
      locationReason==='checking'?'Checking location in the background.':
      freshPosition()?'Browser GPS is approximate.':selected?.location_policy==='strict'?`${reason} Refresh to enable attendance scanning.`:`${reason} Scanning can continue without GPS.`;
    $('[data-location-card]').innerHTML=`<p><strong>Status:</strong> ${esc(status)}</p>
      <p class="text-[#121017]/60">${esc(note)}</p>
      <details class="mt-2 rounded-xl border border-[#121017]/10 p-3"><summary class="cursor-pointer font-bold text-[#397565]">View location details</summary>
        <div class="mt-2 grid gap-1 text-[#121017]/70"><p><strong>Venue:</strong> ${esc(venue)}</p>
        <p><strong>Latitude:</strong> ${position?coords(position.latitude):'—'}</p>
        <p><strong>Longitude:</strong> ${position?coords(position.longitude):'—'}</p>
        <p><strong>Accuracy:</strong> ${position?'±'+Number(position.accuracy).toFixed(0)+' meters':'—'}</p>
        <p><strong>Captured:</strong> ${position?new Intl.DateTimeFormat('en-PH',{timeStyle:'short'}).format(new Date(position.timestamp)):'—'}</p></div>
      </details>`;
    updateControls();
  }
  function updateControls(){
    const active=Boolean(selected?.[`${checkpoint}_window_open`]);
    const strictBlocked=selected?.location_policy==='strict'&&!freshPosition();
    openButton.disabled=!active||strictBlocked||!window.isSecureContext||!navigator.mediaDevices;
    cameraButton.disabled=openButton.disabled;
    manualButton.disabled=!active||strictBlocked;
    $('[data-location-refresh]').hidden=selected?.location_policy==='off';
    if(!window.isSecureContext)$('[data-camera-status]').textContent='Camera: HTTPS is required on mobile devices.';
    if(strictBlocked)$('[data-camera-message]').textContent='Location permission is required before strict attendance scanning.';
  }
  function getLocation(force=false){
    if(!force&&selected?.location_policy==='off')return Promise.resolve(null);
    if(!force&&freshPosition())return Promise.resolve(position);
    if(locationRequest)return locationRequest;
    if(!navigator.geolocation){position=null;locationReason='geolocation_unavailable';renderLocation();return Promise.resolve(null);}
    const strict=selected?.location_policy==='strict';
    locationReason='checking';renderLocation();
    locationRequest=new Promise((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{
      enableHighAccuracy:strict,maximumAge:strict?0:30000,timeout:strict?8000:4000,
    })).then(result=>{
      position={latitude:result.coords.latitude,longitude:result.coords.longitude,accuracy:result.coords.accuracy,timestamp:result.timestamp};
      locationReason='';renderLocation();return position;
    }).catch(error=>{
      position=null;locationReason=error.code===1?'permission_denied':error.code===3?'location_timeout':'location_unavailable';
      renderLocation();return null;
    }).finally(()=>locationRequest=null);
    return locationRequest;
  }
  function locationPayload(){
    if(selected?.location_policy==='off')return {unavailable_reason:'not_provided'};
    if(freshPosition())return {latitude:position.latitude,longitude:position.longitude,accuracy_m:position.accuracy,timestamp_ms:Math.round(position.timestamp)};
    return {unavailable_reason:position?'stale_location':locationReason||'not_provided'};
  }
  function renderRecent(rows){
    $('[data-recent-scans]').innerHTML=rows.length?rows.map(row=>`<article class="flex flex-wrap items-center gap-3 p-4">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#C6F24E]/35 text-xs font-black text-[#397565]">✓</span>
      <div class="min-w-0 flex-1"><strong class="block truncate text-sm">${esc(row.full_name)}</strong>
        <span class="block text-xs text-[#121017]/45">${esc(row.id_number)} · ${esc(row.team_name)} · ${esc(row.status)}</span></div>
      <div class="text-right"><time class="block text-xs font-bold text-[#397565]">${row.phase==='out'?'Out':'In'} ${clock(row.scanned_at)}</time>
        <small class="text-xs text-[#121017]/50">${esc(row.location_status)}</small></div></article>`).join(''):'<p class="p-8 text-center text-sm text-[#121017]/45">No attendance scans yet.</p>';
  }
  function renderCounts(counts){$('[data-scan-counts]').textContent=`${counts.total} timed in · ${counts.checked_out||0} timed out · ${counts.remaining} remaining from your assigned team`;}
  function renderPhaseStatus(){
    $('[data-session-state]').textContent=selected?.[`${checkpoint}_window_open`]
      ?`Time ${checkpoint==='in'?'In':'Out'} scanning is open.`
      :selected?.is_session_active?'The other checkpoint is open. Select it above.'
        :'No QR is scannable now. Wait for the next window or ask the adviser to extend it.';
  }
  function setCheckpoint(next){
    checkpoint=next;lastToken='';lastTokenAt=0;
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
    $('[data-assignment-summary]').innerHTML=[
      ['Event',selected.event_name],['Your team',selected.team_name],
      ['Event day',`Day ${selected.day_number} · ${date(selected.schedule_date)}`],
      ['Session',`${selected.session_name} · ${time(selected.session_start)}–${time(selected.session_end)}`],
    ].map(([label,value])=>`<article><span class="text-[10px] font-black uppercase tracking-wider text-[#397565]">${label}</span><strong class="mt-1 block text-sm">${esc(value)}</strong></article>`).join('')+
      `<details class="sbo-assignment-details"><summary>Venue and location policy</summary><p class="mt-2 text-sm">${esc(selected.venue_name||'Venue not configured')} · ${esc(selected.location_policy)} location policy</p></details>`;
    renderPhaseStatus();
    $('[data-next-session]').textContent=next?`Next scheduled session: ${next.event} · ${next.name} · ${date(next.starts_at)} ${clock(next.starts_at)}`:'No later attendance session is scheduled for today.';
    renderLocation();updateControls();
  }
  async function load(requested=0){
    const response=await axios.get(API,{params:requested?{assignment_id:requested}:{}});
    const data=response.data.data;assignments=data.assignments;
    $('[data-no-assignment]').classList.toggle('hidden',assignments.length>0);
    $('[data-attendance-workspace]').classList.toggle('hidden',assignments.length===0);
    selector.innerHTML=assignments.map(a=>`<option value="${a.id}">${esc(a.event_name)} · Day ${a.day_number} · ${esc(a.session_name)} · ${esc(a.team_name)}</option>`).join('');
    $('[data-assignment-selector-wrap]').classList.toggle('hidden',assignments.length<2);
    $('[data-assignment-selector-wrap]').classList.toggle('grid',assignments.length>1);
    selected=data.selected_assignment||(requested?assignments.find(a=>a.id===Number(requested)):assignments[0])||null;
    if(selected){selector.value=selected.id;renderAssignment(data.next_session);renderRecent(data.recent_scans);renderCounts(data.counts);}
    else{await stop();updateControls();}
  }
  function successSound(){
    try{const audio=new (window.AudioContext||window.webkitAudioContext)();const tone=audio.createOscillator();const gain=audio.createGain();
      tone.type='sine';tone.frequency.value=740;tone.connect(gain);gain.connect(audio.destination);
      gain.gain.setValueAtTime(0.08,audio.currentTime);gain.gain.exponentialRampToValueAtTime(0.001,audio.currentTime+0.16);
      tone.start();tone.stop(audio.currentTime+0.16);tone.onended=()=>audio.close();}catch{}
  }
  async function submit(payload){
    if(isProcessing||Date.now()<cooldownUntil||!selected?.[`${checkpoint}_window_open`])return;
    isProcessing=true;
    try{
      if(selected.location_policy==='strict'){
        await getLocation();
        if(!freshPosition()){notify('error','Your location could not be verified. Check your location permission and try again.');return;}
      }else if(selected.location_policy==='warning'&&!freshPosition())getLocation().catch(()=>{});
      const response=await axios.post(API,{...payload,assignment_id:selected.id,checkpoint,location:locationPayload()},{headers:{'X-CSRF-Token':csrf}});
      const result=response.data.data;renderRecent(result.recent_scans);renderCounts(result.counts);
      const location=result.location.status==='unavailable'?'unavailable':result.location.status+(result.location.distance_m===null?'':' · '+result.location.distance_m+' meters from venue');
      const label=result.checkpoint==='out'?'Time out':'Time in';
      $('[data-scan-result]').innerHTML=`<strong class="text-[#397565]">${label} recorded successfully.</strong><br>${esc(result.student.full_name)} · ${esc(result.student.team_name)}<br>${esc(result.session_name)} · ${clock(result.scanned_at)}<br>Location: ${esc(location)}`;
      $('[data-modal-scan-result]').textContent=`${label} recorded: ${result.student.full_name} · ${result.student.team_name}`;
      notify('success',response.data.message);successSound();
    }catch(error){
      const message=error.response?.data?.message||'Attendance scan failed.';
      $('[data-scan-result]').textContent=message;notify(error.response?.status===409?'warning':'error',message);
      $('[data-modal-scan-result]').textContent=message;
    }finally{cooldownUntil=Date.now()+1000;setTimeout(()=>isProcessing=false,1000);}
  }
  async function onDecode(result){
    const token=String(result?.data||'').trim();
    if(!token||isProcessing||Date.now()<cooldownUntil)return;
    if(token===lastToken&&Date.now()-lastTokenAt<3000)return;
    lastToken=token;lastTokenAt=Date.now();
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
    if(selected.location_policy==='strict'&&!freshPosition()){
      $('[data-modal-scan-result]').textContent='Camera can open now; GPS must be verified before attendance is recorded.';
      getLocation().catch(()=>{});
    }
    try{
      if(!QrScanner)QrScanner=await loadQrModule();
      if(generation!==startGeneration||!dialog.open)return;
      scanner=new QrScanner(video,onDecode,{preferredCamera:'environment',maxScansPerSecond:10,highlightScanRegion:true,highlightCodeOutline:true,returnDetailedScanResult:true});
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
      $('[data-camera-status]').textContent='Camera: active';
      $('[data-camera-message]').textContent='Point the student QR token inside the highlighted region.';
      if(selected.location_policy==='warning'&&!freshPosition())getLocation().catch(()=>{});
      try{cameras=await QrScanner.listCameras();cameraIndex=0;if(scanner===current)switchButton.hidden=cameras.length<2;}catch{cameras=[];switchButton.hidden=true;}
      try{if(scanner===current)flashButton.hidden=!(await current.hasFlash());}catch{flashButton.hidden=true;}
    }catch(error){if(generation===startGeneration){notify('error','Camera is unavailable: '+(error?.message||'check permission.'));await stop();$('[data-camera-status]').textContent='Camera unavailable';$('[data-camera-message]').textContent='Allow camera access, then retry.';$('[data-modal-scan-result]').textContent='Camera could not start. Check permission and retry.';}}
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
  openButton.addEventListener('click',async()=>{if(openButton.disabled)return;dialog.showModal();$('[data-modal-scan-result]').textContent='Ready to scan.';await start();});
  $('[data-scanner-close]').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>stop());
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
  cameraButton.addEventListener('click',()=>scanner?stop():start());
  switchButton.addEventListener('click',async()=>{if(!scanner||cameras.length<2)return;cameraIndex=(cameraIndex+1)%cameras.length;await scanner.setCamera(cameras[cameraIndex].id);flashButton.hidden=!(await scanner.hasFlash());});
  flashButton.addEventListener('click',async()=>{if(!scanner)return;await scanner.toggleFlash();flashButton.textContent=scanner.isFlashOn()?'Flashlight on':'Flashlight';});
  $('[data-location-refresh]').addEventListener('click',()=>getLocation(true));
  selector.addEventListener('change',async()=>{if(dialog.open)dialog.close();await stop();position=null;locationReason='not_provided';await load(Number(selector.value));if(selected?.is_session_active&&selected.location_policy==='strict')getLocation().catch(()=>{});});
  $('[data-manual-form]').addEventListener('submit',async event=>{event.preventDefault();const field=event.currentTarget.elements.student_id;const value=field.value.trim();if(!value)return;await submit({mode:'manual',student_id:value});field.value='';});
  window.addEventListener('pagehide',()=>{clearInterval(contextTimer);if(dialog.open)dialog.close();stop();});
  document.addEventListener('visibilitychange',()=>{if(document.hidden){if(dialog.open)dialog.close();stop();}});
  SboPortal.initialize('attendance').then(async context=>{csrf=context.csrfToken;await load();if(selected?.is_session_active){loadQrModule().catch(()=>{});if(selected.location_policy==='strict')getLocation().catch(()=>{});}contextTimer=setInterval(async()=>{if(isProcessing)return;const old=selected?.id,wasActive=selected?.is_session_active;await load(old||0);if(!wasActive&&selected?.is_session_active){loadQrModule().catch(()=>{});if(selected.location_policy==='strict')getLocation().catch(()=>{});}if(!selected?.is_session_active){if(dialog.open)dialog.close();await stop();}},30000);})
    .catch(error=>notify('error',error.response?.data?.message||error.message));
})();
