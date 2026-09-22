(() => {
  'use strict';
  const URL = 'api/media.php';
  let csrf = '';
  const configure = token => { csrf = token || ''; };
  const load = async eventId => (await axios.get(URL, {params: eventId ? {event_id: eventId} : {}})).data.data;
  const moderation = async page => (await axios.get(URL, {params: {action: 'moderation', page}})).data.data;
  const send = async data => {
    const body = data instanceof FormData ? data : data;
    return (await axios.post(URL, body, {headers: {'X-CSRF-Token': csrf}})).data;
  };
  window.CiteMediaApi = {configure, load, moderation, send};
})();
