(() => {
  'use strict';
  let csrfToken = '';
  const escapeHtml = value => window.SharedNavigation?.escapeHtml(value) ?? String(value ?? '');
  const initialize = async () => {
    const session = await window.SharedNavigation.ready;
    if (!session || session.role !== 'student') throw new Error('Student navigation is unavailable.');
    csrfToken = session.csrfToken;
    return session.user;
  };
  const formatDate = (value, includeTime = true) => {
    if (!value) return 'To be announced';
    const options = {month:'short',day:'numeric',year:'numeric'};
    if (includeTime) Object.assign(options,{hour:'numeric',minute:'2-digit'});
    return new Intl.DateTimeFormat('en-PH',options).format(new Date(value.replace(' ','T')));
  };
  const timeOnly = value => {
    if (!value) return '—';
    const normalized = value.includes(' ') ? value.replace(' ','T') : `2000-01-01T${value}`;
    return new Intl.DateTimeFormat('en-PH',{hour:'numeric',minute:'2-digit'}).format(new Date(normalized));
  };
  window.StudentPortal = {initialize,formatDate,timeOnly,escapeHtml,get csrfToken(){return csrfToken;}};
})();
