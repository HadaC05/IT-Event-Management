// Browser smoke test for the adviser moderation workspace. Requires Playwright on NODE_PATH.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const posts = Array.from({length: 21}, (_, index) => ({
  id: index + 1,
  author_name: `Student ${index + 1}`,
  author_initials: 'ST',
  event_title: 'IT Days 2026',
  created_at: '2026-09-23 08:00:00',
  content: `Pending post ${index + 1}`,
  status: 'pending',
  image_path: null,
  video_path: index === 0 ? 'assets/uploads/post-videos/review-smoke.mp4' : null,
}));

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const width of [360, 1280]) {
      const context = await browser.newContext({viewport: {width, height: 800}});
      const page = await context.newPage();
      const pageErrors = [];
      page.on('pageerror', error => pageErrors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 2, role: 'SBO Adviser', first_name: 'SBO', last_name: 'Adviser', full_name: 'SBO Adviser', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/media.php')) {
          if (route.request().method() === 'POST') {
            const input = route.request().postDataJSON();
            if (input.action === 'approve') posts.splice(posts.findIndex(post => post.id === Number(input.post_id)), 1);
            payload = {success: true, message: 'Post approved.'};
          } else if (url.searchParams.get('action') === 'moderation') {
            const requested = Number(url.searchParams.get('page')) || 1;
            const pageNumber = Math.min(requested, Math.max(1, Math.ceil(posts.length / 20)));
            payload = {success: true, data: {posts: posts.slice((pageNumber - 1) * 20, pageNumber * 20), total: posts.length, counts: {pending: posts.length, rejected: 0, hidden: 0}, page: pageNumber, page_count: Math.max(1, Math.ceil(posts.length / 20)), per_page: 20}};
          } else payload = {success: true, data: {viewer: {id: 2, role: 'SBO Adviser', full_name: 'SBO Adviser'}, permissions: {moderate: true, manage_carousel: true, hide: true}, featured_events: [], active_events: [], post_events: [], carousel_events: [], event_program: [], own_posts: [], posts: []}};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/adviser/posts.html`, {waitUntil: 'domcontentloaded'});
      await page.waitForFunction(() => document.querySelector('[data-moderation-count]')?.textContent === '21');
      assert.equal(await page.locator('.cite-review-card').count(), 20);
      assert.equal(await page.locator('.cite-review-card video').count(), 1);
      await page.locator('[data-review-page="2"]').click();
      await page.locator('.cite-review-card').getByText('Pending post 21', {exact: true}).waitFor();
      assert.equal(await page.locator('.cite-review-card').count(), 1);
      await page.locator('[data-review-page="1"]').click();
      await page.locator('.cite-review-card').getByText('Pending post 1', {exact: true}).waitFor();
      await page.locator('[data-shared-post-form] textarea[name="content"]').fill('Unsubmitted draft stays here');
      await page.locator('[data-approve="1"]').click();
      await page.waitForFunction(() => document.querySelector('[data-moderation-count]')?.textContent === '20');
      assert.equal(await page.locator('[data-shared-post-form] textarea[name="content"]').inputValue(), 'Unsubmitted draft stays here');
      assert.equal(await page.locator('.cite-review-card').count(), 20);
      const dimensions = await page.evaluate(() => ({scrollWidth: document.documentElement.scrollWidth, clientWidth: document.documentElement.clientWidth, queueTop: document.querySelector('[data-moderation-workspace]').getBoundingClientRect().top, formTop: document.querySelector('[data-shared-post-form-host]').getBoundingClientRect().top}));
      assert.ok(dimensions.scrollWidth <= dimensions.clientWidth + 1, `Horizontal overflow at ${width}px`);
      assert.ok(dimensions.queueTop < dimensions.formTop, 'Moderation queue must be before the public feed');
      assert.deepEqual(pageErrors, []);
      console.log(`PASS: adviser review queue, media preview, paging, draft preservation and layout at ${width}px`);
      await context.close();
      posts.unshift({id: 1, author_name: 'Student 1', author_initials: 'ST', event_title: 'IT Days 2026', created_at: '2026-09-23 08:00:00', content: 'Pending post 1', status: 'pending', image_path: null, video_path: 'assets/uploads/post-videos/review-smoke.mp4'});
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
