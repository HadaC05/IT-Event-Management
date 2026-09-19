(() => {
  const start = () => window.SharedNavigation.ready
    .then(session => window.CiteMediaFeed.initialize(document.querySelector('[data-media-feed-root]'), session))
    .catch(error => {
      console.error(error);
      const root = document.querySelector('[data-media-feed-root]');
      if (root) root.textContent = error.response?.data?.message || 'The media feed could not be opened.';
    });
  if (window.SharedNavigation) start();
  else document.addEventListener('DOMContentLoaded', start, {once: true});
})();
