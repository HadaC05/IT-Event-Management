const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const post = (id, pinned = false) => ({
  id, user_id: 1, event_id: 17, category: 'general', content: `Update ${id}`,
  image_path: null, video_path: null, created_at: '2026-09-27 09:00:00',
  is_official: false, is_pinned: pinned, event_title: 'IT Days',
  author_name: 'Test Student', author_initials: 'TS', author_role: 'Student',
  profile_photo_path: null, viewer_reaction: null, reactions_count: 0, comments_count: 0,
});
const activities = Array.from({length: 18}, (_, index) => ({
  id: index + 1, event_id: 17, name: `Activity ${index + 1}`,
  status: index === 0 ? 'ongoing' : 'upcoming', schedule_date: '2026-09-27', description: '',
}));
const viewer = {id: 1, role: 'Student', first_name: 'Test', last_name: 'Student', full_name: 'Test Student', initials: 'TS', must_change_password: false};
const feed = {viewer, permissions: {moderate: false, manage_carousel: false, hide: false},
  featured_events: [], active_events: [], post_events: [], carousel_events: [], own_posts: [],
  event_program: [{id: 17, title: 'IT Days', activity_count: 18, activities: activities.slice(0, 5)}],
  pinned_preview: [post(90, true)], pinned_count: 4,
  posts: Array.from({length: 15}, (_, index) => post(89 - index)), next_cursor: 'older'};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 1280, height: 800}});
    const errors = [];
    let pinnedRequests = 0, programRequests = 0;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/api/**', async route => {
      const url = new URL(route.request().url());
      const action = url.searchParams.get('action');
      let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
      if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: viewer, csrf_token: 'test'};
      if (url.pathname.endsWith('/media.php')) {
        if (action === 'pinned_posts') { pinnedRequests++; payload = {success: true, data: {posts: [post(90, true), post(60, true), post(30, true), post(10, true)], next_cursor: null, total: 4}}; }
        else if (action === 'event_program') { programRequests++; payload = {success: true, data: [{id: 17, title: 'IT Days', activities}]}; }
        else if (action === 'posts') payload = {success: true, data: {posts: [post(74), post(73)], next_cursor: null}};
        else payload = {success: true, data: feed};
      }
      await route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
    });
    await page.goto(`${base}/pages/student/home.html`, {waitUntil: 'domcontentloaded'});
    await page.locator('[data-public-feed] [data-post-id]').first().waitFor();
    assert.equal(await page.locator('[data-public-feed] [data-post-id]').count(), 15);
    assert.equal(await page.locator('[data-pinned-host] .cite-pinned-summary').count(), 1);
    assert.equal(await page.locator('[data-pinned-host] .cite-pinned-summary__text').textContent(), 'Update 90');
    assert.equal(await page.locator('.cite-program-item').count(), 5);
    assert.equal(pinnedRequests, 0, 'Full pinned posts must load on demand');
    assert.equal(programRequests, 0, 'Full activity program must load on demand');
    if (process.env.CITE_SCREENSHOT) await page.screenshot({path: process.env.CITE_SCREENSHOT, fullPage: true});
    await page.setViewportSize({width: 390, height: 780});
    assert.equal(await page.locator('[data-pinned-host] .cite-pinned-summary').isVisible(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Pinned preview must fit mobile width');

    await page.locator('[data-open-pinned]').click();
    await page.locator('[data-pinned-list] [data-post-id]').first().waitFor();
    assert.equal(await page.locator('[data-pinned-list] [data-post-id]').count(), 4);
    assert.equal(pinnedRequests, 1);
    await page.locator('[data-close-pinned]').click();

    await page.setViewportSize({width: 1280, height: 800});
    await page.locator('.cite-program-all').click();
    await page.getByText('Activity 18').waitFor();
    assert.equal(programRequests, 1);
    assert.equal(await page.locator('[data-all-event-activities] strong').count(), 18);
    assert.deepEqual(errors, []);
    console.log('PASS: pinned preview, chronological feed, lazy pinned drawer, and bounded activity summary');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
