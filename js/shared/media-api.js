(() => {
  'use strict';
  const URL = 'api/media.php';
  let csrf = '';
  const configure = token => { csrf = token || ''; };
  const load = async eventId => (await axios.get(URL, {params: eventId ? {event_id: eventId} : {}})).data.data;
  const posts = async ({eventId = null, cursor = null, mine = false} = {}) => (await axios.get(URL, {params: {action: 'posts', ...(eventId ? {event_id: eventId} : {}), ...(cursor ? {cursor} : {}), ...(mine ? {scope: 'mine'} : {})}})).data.data;
  const post = async postId => (await axios.get(URL, {params: {action: 'post', post_id: postId}})).data.data;
  const comments = async (postId, cursor = null) => (await axios.get(URL, {params: {action: 'comments', post_id: postId, ...(cursor ? {cursor} : {})}})).data.data;
  const moderation = async page => (await axios.get(URL, {params: {action: 'moderation', page}})).data.data;
  const send = async data => {
    const body = data instanceof FormData ? data : data;
    return (await axios.post(URL, body, {headers: {'X-CSRF-Token': csrf}})).data;
  };
  window.CiteMediaApi = {configure, load, posts, post, comments, moderation, send};
})();
