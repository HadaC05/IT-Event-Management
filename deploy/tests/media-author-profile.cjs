// Browser smoke test for author search, the public profile, and multi-photo posts.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const photo = 'assets/images/cite_favicon.png';
const post = {
  id: 15, user_id: 7, event_id: null, content: 'Thirty photos from the event',
  image_path: photo, images: Array(30).fill(photo), video_path: null,
  created_at: '2026-09-23 08:00:00', is_official: false, event_title: null,
  author_name: 'Micah Dusil Lago', author_initials: 'ML', author_role: 'Faculty', special_tag: 'Dev',
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
          if (action === 'authors') payload = {success: true, data: {users: url.searchParams.get('q')?.toLowerCase().includes('mic')
            ? [{id: 7, full_name: 'Micah Dusil Lago', initials: 'ML', role_name: 'Faculty', profile_photo_path: photo, post_count: 1}]
            : [{id: 8, full_name: 'New Student', initials: 'NS', role_name: 'Student', profile_photo_path: null, post_count: 0}]}};
          else if (action === 'author') payload = url.searchParams.get('user_id') === '8'
            ? {success: true, data: {profile: {id: 8, full_name: 'New Student', initials: 'NS', role_name: 'Student', post_count: 0}, posts: [], next_cursor: null}}
            : {success: true, data: {profile: {id: 7, full_name: 'Micah Dusil Lago', initials: 'ML', role_name: 'Faculty', profile_photo_path: photo, post_count: 1}, posts: [post], next_cursor: null}};
          else payload = {success: true, data: {viewer: {id: 2, role: 'Student'}, permissions: {moderate: false, hide: false, manage_carousel: false}, featured_events: [], active_events: [], post_events: [], carousel_events: [], event_program: [], own_posts: [], posts: [post], next_cursor: null}};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/student/home.html`, {waitUntil: 'domcontentloaded'});
      const photoBytes = fs.readFileSync(photo);
      await page.locator('[data-shared-post-form] input[name="images[]"]').setInputFiles(Array.from({length: 30}, (_, index) => ({name: `photo-${index + 1}.png`, mimeType: 'image/png', buffer: photoBytes})));
      assert.equal(await page.locator('[data-image-preview-list] img').count(), 6, 'Composer previews only six of 30 selected photos');
      assert.match(await page.locator('[data-file-name]').textContent(), /30 photos selected/);
      await page.locator('[data-shared-post-form] input[name="images[]"]').setInputFiles(Array.from({length: 31}, (_, index) => ({name: `extra-${index + 1}.png`, mimeType: 'image/png', buffer: photoBytes})));
      assert.equal(await page.locator('[data-shared-post-form] input[name="images[]"]').evaluate(input => input.files.length), 0, 'Composer rejects 31 photos');
      assert.equal(await page.locator('[data-image-preview-list] img').count(), 0, 'Rejected selection clears stale previews');
      const search = page.locator('[data-author-search]');
      await search.waitFor();
      await search.fill('Mic');
      await page.locator('[data-author-results] a').waitFor();
      await page.locator('[data-author-results] a').click();
      await page.locator('[data-public-profile-root] [data-post-id="15"]').waitFor();
      assert.match(await page.locator('[data-public-profile-root] h1').textContent(), /Micah Dusil Lago/);
      assert.equal(await page.locator('[data-post-id="15"] .cite-post-gallery img').count(), 4, 'Large galleries render only four thumbnails');
      assert.match(await page.locator('[data-post-id="15"] .cite-post-gallery').textContent(), /\+26/);
      assert.match(await page.locator('[data-post-id="15"] header').textContent(), /Dev/, 'Author tag stays visible on the post');
      assert.equal(await page.locator('[data-post-id="15"] [data-reaction-current]').count(), 1);
      assert.equal(await page.locator('[data-post-id="15"] [data-reaction-choice="love"]').count(), 1);
      await page.locator('[data-post-id="15"]').evaluate(card => { window.reactionTestCard = card; });
      await page.locator('[data-post-id="15"] [data-reaction-picker]').evaluate(element => element.scrollIntoView({block: 'center'}));
      await page.locator('[data-post-id="15"] [data-reaction-current]').click();
      await page.locator('[data-post-id="15"] [data-reaction-choice="like"]').click();
      await page.waitForFunction(() => document.querySelector('[data-post-id="15"] [data-reaction-current]')?.getAttribute('aria-pressed') === 'true');
      assert.equal(await page.locator('[data-post-id="15"]').evaluate(card => card === window.reactionTestCard), true, 'Reacting on the author page keeps the post in place');
      await page.locator('[data-post-id="15"] [data-open-post-image="1"]').click();
      assert.equal(await page.locator('.cite-image-dialog').evaluate(dialog => dialog.open), true);
      assert.equal(await page.locator('[data-image-count]').textContent(), '2 / 30');
      await page.locator('[data-image-next]').click();
      assert.equal(await page.locator('[data-image-count]').textContent(), '3 / 30');
      await page.keyboard.press('Escape');
      await page.goto(`${base}/pages/shared/public-profile.html?user_id=8`, {waitUntil: 'domcontentloaded'});
      await page.locator('[data-public-profile-root] h1').waitFor();
      assert.match(await page.locator('[data-public-profile-root] h1').textContent(), /New Student/);
      assert.equal(await page.locator('[data-profile-posts]').count(), 0, 'A profile with no posts is still visible');
      assert.deepEqual(errors, []);
      console.log(`PASS: author search, profile and multi-photo post at ${width}px`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
