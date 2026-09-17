(() => {
  'use strict';
  let csrfToken = '';
  const esc = value => window.SharedNavigation?.escapeHtml(value) ?? String(value ?? '');
  const initialize = async () => {
    const session = await window.SharedNavigation.ready;
    if (!session || session.role !== 'sbo') throw new Error('SBO Officer navigation is unavailable.');
    csrfToken = session.csrfToken;
    const notificationRegion=document.querySelector('[data-notification-region]'); if(notificationRegion) notificationRegion.style.zIndex='100';
    return {user:session.user,csrfToken};
  };
  window.SboPortal={initialize,escapeHtml:esc,get csrfToken(){return csrfToken;}};
})();
