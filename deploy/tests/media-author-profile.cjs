// Browser smoke test for author search, the public profile, and multi-photo posts.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const photo = 'assets/images/cite_favicon.png';
const post = {
  id: 15, user_id: 7, event_id: null, content: 'Two photos from the event',
  image_path: photo, images: [photo, photo], video_path: null,
  created_at: '2026-09-23 08:00:00', is_official: false, event_title: null,
  author_name: 'Micah Dusil Lago', author_initials: 'ML', author_role: 'Faculty',
  profile_photo_path: photo, viewer_reaction: null, reactions_count: 0, comments_count: 0,
};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({viewport: {width, height: 800}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        const action = url.searchParams.get('action');
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 2, role: 'Student', first_name: 'Test', last_name: 'Student', full_name: 'Test Student', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/media.php')) {
          if (action === 'authors') payload = {success: true, data: {users: [{id: 7, full_name: 'Micah Dusil Lago', initials: 'ML', role_name: 'Faculty', profile_photo_path: photo, post_count: 1}]}};
          else if (action === 'author') payload = {success: true, data: {profile: {id: 7, full_name: 'Micah Dusil Lago', initials: 'ML', role_name: 'Faculty', profile_photo_path: photo, post_count: 1}, posts: [post], next_cursor: null}};
          else payload = {success: true, data: {viewer: {id: 2, role: 'Student'}, permissions: {moderate: false, hide: false, manage_carousel: false}, featured_events: [], active_events: [], post_events: [], carousel_events: [], event_program: [], own_posts: [], posts: [post], next_cursor: null}};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/student/home.html`, {waitUntil: 'domcontentloaded'});
      const search = page.locator('[data-author-search]');
      await search.waitFor();
      await search.fill('Mic');
      await page.locator('[data-author-results] a').waitFor();
      await page.locator('[data-author-results] a').click();
      await page.locator('[data-public-profile-root] [data-post-id="15"]').waitFor();
      assert.match(await page.locator('[data-public-profile-root] h1').textContent(), /Micah Dusil Lago/);
      assert.equal(await page.locator('[data-post-id="15"] .cite-post-gallery img').count(), 2);
      assert.equal(await page.locator('[data-post-id="15"] [data-reaction-current]').count(), 1);
      assert.equal(await page.locator('[data-post-id="15"] [data-reaction-choice="love"]').count(), 1);
      await page.locator('[data-post-id="15"] [data-open-post-image="1"]').click();
      assert.equal(await page.locator('.cite-image-dialog').evaluate(dialog => dialog.open), true);
      await page.keyboard.press('Escape');
      assert.deepEqual(errors, []);
      console.log(`PASS: author search, profile and multi-photo post at ${width}px`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
