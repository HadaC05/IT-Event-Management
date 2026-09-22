// Browser regression: older approved posts and comments are requested only on demand.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const posts = Array.from({length: 25}, (_, index) => ({
  id: 25 - index, user_id: 1, event_id: null, content: `Approved post ${25 - index}`,
  image_path: null, video_path: null, created_at: '2026-09-23 08:00:00',
  is_official: false, event_title: null, author_name: 'Test Student',
  author_initials: 'TS', author_role: 'Student', profile_photo_path: null,
  viewer_reaction: null, reactions_count: 0, comments_count: 25,
}));
const comments = Array.from({length: 25}, (_, index) => ({
  id: index + 1, post_id: 25, user_id: 1, body: `Comment ${index + 1}`,
  is_pinned: false, author_name: 'Test Student', author_initials: 'TS',
}));
const viewer = {id: 1, role: 'Student', full_name: 'Test Student'};
const common = {viewer, permissions: {moderate: false, manage_carousel: false, hide: false},
  featured_events: [], active_events: [], post_events: [], carousel_events: [],
  event_program: [], own_posts: [], posts: posts.slice(0, 20), next_cursor: 'older-posts'};
const profile = {id: 1, full_name: 'Test Student', initials: 'TS', id_number: '123',
  year_level_label: 'Fourth Year', team_name: 'Test Team', team_color: '#397565',
  profile_photo_path: null, created_at: '2026-09-23 08:00:00'};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const pathname of ['home.html', 'profile.html']) {
      const page = await browser.newPage({viewport: {width: 390, height: 780}});
      const errors = [];
      let commentRequests = 0;
      let liked = false;
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        const action = url.searchParams.get('action');
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true,
          user: {...viewer, first_name: 'Test', last_name: 'Student', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/student-profile.php')) payload = {success: true,
          data: {...common, profile, post_counts: {approved: 25, pending: 0, rejected: 0, hidden: 0}}};
        if (url.pathname.endsWith('/media.php')) {
          if (route.request().method() === 'POST') {
            const input = route.request().postDataJSON();
            if (input.action === 'reaction_toggle') liked = Boolean(input.active);
            payload = {success: true, message: 'Action saved.'};
          } else if (action === 'posts') payload = {success: true, data: {posts: posts.slice(20), next_cursor: null}};
          else if (action === 'comments') {
            commentRequests++;
            payload = {success: true, data: url.searchParams.has('cursor')
              ? {comments: comments.slice(20), next_cursor: null}
              : {comments: comments.slice(0, 20), next_cursor: 'older-comments'}};
          } else if (action === 'post') payload = {success: true, data: {...posts.find(post => post.id === Number(url.searchParams.get('post_id'))), viewer_reaction: liked ? 'like' : null, reactions_count: liked ? 1 : 0}};
          else payload = {success: true, data: common};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/student/${pathname}`, {waitUntil: 'domcontentloaded'});
      const root = pathname === 'home.html' ? '[data-public-feed]' : '[data-profile-posts]';
      await page.locator(`${root} [data-post-id]`).first().waitFor();
      assert.equal(await page.locator(`${root} [data-post-id]`).count(), 20);
      assert.equal(commentRequests, 0, 'Opening the feed must not request comments');
      await page.locator(pathname === 'home.html' ? '[data-more-posts]' : '[data-more-profile-posts]').click();
      await page.waitForFunction(selector => document.querySelectorAll(`${selector} [data-post-id]`).length === 25, root);
      assert.equal(commentRequests, 0, 'Loading older posts must not request comments');
      const first = page.locator(`${root} [data-post-id="25"]`);
      await first.locator('[data-comment-focus]').click();
      await page.waitForFunction(() => document.querySelectorAll('[data-post-id="25"] [data-comments] > div').length === 20);
      assert.equal(commentRequests, 1);
      await first.locator('[data-more-comments]').click();
      await page.waitForFunction(() => document.querySelectorAll('[data-post-id="25"] [data-comments] > div').length === 25);
      assert.equal(commentRequests, 2);
      await first.locator('[data-reaction]').click();
      await page.waitForFunction(() => document.querySelector('[data-post-id="25"] [data-reaction]')?.getAttribute('aria-pressed') === 'true');
      assert.equal(await page.locator(`${root} [data-post-id]`).count(), 25, 'Engagement must not discard older loaded posts');
      assert.equal(await page.locator('[data-post-id="25"] [data-comment-focus]').getAttribute('aria-expanded'), 'true');
      await page.setViewportSize({width: 320, height: 780});
      const dimensions = await page.evaluate(() => ({scroll: document.documentElement.scrollWidth, visible: document.documentElement.clientWidth}));
      assert.ok(dimensions.scroll <= dimensions.visible + 1, `Horizontal overflow at 320px on ${pathname}`);
      assert.deepEqual(errors, []);
      console.log(`PASS: ${pathname} loads 25 posts and 25 comments on demand without browser errors`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
