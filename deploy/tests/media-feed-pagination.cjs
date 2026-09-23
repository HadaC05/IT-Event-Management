// Browser regression: older approved posts and comments are requested only on demand.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const portraitImage = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="1500"><rect width="300" height="1500" fill="#397565"/></svg>')}`;
const posts = Array.from({length: 25}, (_, index) => ({
  id: 25 - index, user_id: 1, event_id: null, content: `Approved post ${25 - index}`,
  image_path: index === 0 ? portraitImage : null, video_path: null, created_at: '2026-09-23 08:00:00',
  is_official: false, event_title: null, author_name: 'Test Middle Student',
  author_initials: 'TS', author_role: 'Student', profile_photo_path: 'assets/images/cite_favicon.png',
  viewer_reaction: null, reactions_count: 0, comments_count: 25,
}));
const comments = Array.from({length: 25}, (_, index) => ({
  id: index + 1, post_id: 25, user_id: 1, body: `Comment ${index + 1}`,
  is_pinned: false, author_name: 'Test Student', author_initials: 'TS', created_at: '2026-09-23 08:00:00',
}));
const viewer = {id: 1, role: 'Student', full_name: 'Test Middle Student', initials: 'TS', profile_photo_path: 'assets/images/cite_favicon.png'};
const common = {viewer, permissions: {moderate: false, manage_carousel: false, hide: false},
  featured_events: [], active_events: [], post_events: [], carousel_events: [],
  event_program: [], own_posts: [{id: 99, event_id: null, content: 'Pending edit', image_path: null, video_path: 'data:video/mp4;base64,AAAA', status: 'pending', created_at: '2026-09-23 08:00:00'}], posts: posts.slice(0, 20), next_cursor: 'older-posts'};
const profile = {id: 1, full_name: 'Test Middle Student', initials: 'TS', id_number: '123',
  year_level_label: 'Fourth Year', team_name: 'Test Team', team_color: '#397565',
  profile_photo_path: viewer.profile_photo_path, created_at: '2026-09-23 08:00:00'};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const pages = [
      {pathname: 'student/home.html', role: 'Student'},
      {pathname: 'student/profile.html', role: 'Student', profilePage: true},
      {pathname: 'faculty/posts.html', role: 'Faculty'},
      {pathname: 'adviser/posts.html', role: 'SBO Adviser'},
      {pathname: 'sbo/media.html', role: 'SBO Officer'},
      {pathname: 'admin/media.html', role: 'Admin'},
    ];
    for (const {pathname, role, profilePage = false} of pages) {
      const page = await browser.newPage({viewport: {width: 390, height: 780}});
      const pageViewer = {...viewer, role};
      const errors = [];
      let commentRequests = 0;
      let reactionRequests = 0;
      let liked = false;
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', async route => {
        const url = new URL(route.request().url());
        const action = url.searchParams.get('action');
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true,
          user: {...pageViewer, first_name: 'Test', last_name: 'Student', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/student-profile.php')) payload = {success: true,
          data: {...common, viewer: pageViewer, profile, post_counts: {approved: 25, pending: 0, rejected: 0, hidden: 0}}};
        if (url.pathname.endsWith('/media.php')) {
          if (route.request().method() === 'POST') {
            const input = route.request().postDataJSON();
            if (input.action === 'reaction_toggle') {
              reactionRequests++;
              await new Promise(resolve => setTimeout(resolve, 200));
              liked = Boolean(input.active);
            }
            payload = {success: true, message: 'Action saved.'};
          } else if (action === 'posts') payload = {success: true, data: {posts: posts.slice(20), next_cursor: null}};
          else if (action === 'comments') {
            commentRequests++;
            payload = {success: true, data: url.searchParams.has('cursor')
              ? {comments: comments.slice(20), next_cursor: null}
              : {comments: comments.slice(0, 20), next_cursor: 'older-comments'}};
          } else if (action === 'post') payload = {success: true, data: {...posts.find(post => post.id === Number(url.searchParams.get('post_id'))), viewer_reaction: liked ? 'like' : null, reactions_count: liked ? 1 : 0}};
          else payload = {success: true, data: {...common, viewer: pageViewer}};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/${pathname}`, {waitUntil: 'domcontentloaded'});
      const root = profilePage ? '[data-profile-posts]' : '[data-public-feed]';
      await page.locator(`${root} [data-post-id]`).first().waitFor();
      const composerPhoto = page.locator('[data-shared-post-form] img[alt="Test Middle Student profile picture"]');
      assert.equal(await composerPhoto.getAttribute('src'), viewer.profile_photo_path, 'Composer uses the viewer profile photo');
      assert.equal(await page.locator('[data-shared-account-initials] img').getAttribute('src'), viewer.profile_photo_path, 'Header uses the same profile photo');
      assert.equal(await page.locator(`${root} [data-post-id="25"] header img`).getAttribute('src'), viewer.profile_photo_path, 'Published post uses the same profile photo');
      const publishedImage = page.locator(`${root} [data-post-id="25"] .cite-post-gallery img`);
      assert.ok((await publishedImage.boundingBox()).height <= 430, 'Tall published image remains compact on mobile');
      assert.equal(await publishedImage.evaluate(image => getComputedStyle(image).objectFit), 'contain', 'Published image is not cropped');
      await page.locator(`${root} [data-post-id="25"] [data-open-post-image]`).click();
      assert.equal(await page.locator('.cite-image-dialog').evaluate(dialog => dialog.open), true, 'Published image opens the full-size viewer');
      assert.equal(await page.locator('.cite-image-dialog img').getAttribute('src'), portraitImage);
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('.cite-image-dialog').evaluate(dialog => dialog.open), false, 'Escape closes the full-size viewer');
      await page.evaluate(async () => {
        const canvas = document.createElement('canvas');
        canvas.width = 300; canvas.height = 1500;
        canvas.getContext('2d').fillRect(0, 0, 300, 1500);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        const transfer = new DataTransfer();
        transfer.items.add(new File([blob], 'portrait.png', {type: 'image/png'}));
        const input = document.querySelector('[data-shared-post-form] input[name="images[]"]');
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', {bubbles: true}));
      });
      const previewImage = page.locator('[data-media-preview] img');
      await previewImage.waitFor({state: 'visible'});
      assert.ok((await previewImage.boundingBox()).height <= 321, 'Tall upload preview remains compact on mobile');
      assert.equal(await page.locator('[data-media-preview] video').isVisible(), false, 'Video stays hidden for image previews');
      await page.locator('[data-preview-open-image]').click();
      assert.equal(await page.locator('.cite-image-dialog').evaluate(dialog => dialog.open), true, 'Upload preview opens in the full-size viewer');
      await page.keyboard.press('Escape');
      await page.locator('[data-remove-media]').click();
      assert.equal(await page.locator('[data-media-preview]').isVisible(), false, 'Removing the upload hides its preview');
      await page.setViewportSize({width: 1280, height: 800});
      assert.ok((await publishedImage.boundingBox()).height <= 433, 'Tall published image remains compact on desktop');
      await page.setViewportSize({width: 390, height: 780});
      const fallbackInitials = await page.evaluate(() => {
        const host = document.createElement('div');
        CiteMediaPostForm.mount(host, {events: [], viewer: {role: 'Student', full_name: 'Brian D. Ragasi'}, onSaved: async () => {}, notify: () => {}});
        return host.querySelector('[data-shared-post-form] span[aria-hidden="true"]').textContent;
      });
      assert.equal(fallbackInitials, 'BR', 'Composer fallback uses first and last names');
      assert.equal(await page.locator('[data-shared-post-form] textarea[name="content"]').evaluate(input => input.validity.valueMissing), true, 'Empty post remains subject to native validation');
      const composerHost = page.locator(profilePage ? '[data-profile-post-form]' : '[data-shared-post-form-host]');
      await page.locator(`${root} [data-post-id="25"] [data-post-menu] summary`).click();
      await page.locator(`${root} [data-post-id="25"] [data-own-edit]`).click();
      assert.equal(await composerHost.evaluate(host => host.previousElementSibling?.dataset.postId), '25', 'Editing a published post keeps the composer beside that post');
      assert.equal(await page.locator('[data-shared-post-form] textarea[name="content"]').inputValue(), 'Approved post 25');
      assert.equal(await page.locator('[data-submit]').textContent(), role === 'Student' ? 'Save and resubmit' : 'Save changes');
      assert.match(await page.locator('[data-edit-review-note]').textContent(), role === 'Student' ? /Pending Review/ : /published immediately/);
      assert.equal(await page.locator('[data-media-preview] img').getAttribute('src'), portraitImage, 'Editing shows the existing photo');
      assert.equal(await page.locator('[data-media-preview]').isVisible(), true);
      assert.equal(await page.locator('[data-file-name]').textContent(), '1 current photo · Choose Photos to replace');
      assert.equal(await page.locator('[data-shared-post-form] input[name="remove_media"]').inputValue(), '0', 'Text-only edits keep the attached photo');
      await page.locator('[data-preview-open-image]').click();
      assert.equal(await page.locator('.cite-image-dialog img').getAttribute('src'), portraitImage, 'Existing photo opens in the viewer');
      await page.keyboard.press('Escape');
      await page.locator('[data-shared-post-form] input[name="images[]"]').setInputFiles({name: 'replacement.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+iZQAAAABJRU5ErkJggg==', 'base64')});
      assert.match(await page.locator('[data-media-preview] img').getAttribute('src'), /^blob:/, 'Choosing a new photo replaces the preview');
      assert.equal(await page.locator('[data-file-name]').textContent(), '1 photo selected');
      assert.equal(await page.locator('[data-shared-post-form] input[name="remove_media"]').inputValue(), '0');
      await page.locator('[data-remove-media]').click();
      assert.equal(await page.locator('[data-media-preview]').isVisible(), false, 'Remove media clears the edit preview');
      assert.equal(await page.locator('[data-shared-post-form] input[name="remove_media"]').inputValue(), '1', 'Remove media explicitly requests deletion');
      await page.locator('[data-cancel-edit]').click();
      assert.equal(await composerHost.evaluate(host => host.previousElementSibling?.dataset.postId || null), null, 'Cancel restores the composer to its normal position');
      if (profilePage) await page.locator('[data-profile-edit="99"]').click();
      else {
        await page.locator('[data-post-status] > summary').click();
        await page.locator('[data-own-edit="99"]').locator('..').locator('..').locator('summary').click();
        await page.locator('[data-own-edit="99"]').click();
      }
      assert.equal(await composerHost.evaluate(host => host.previousElementSibling?.tagName), 'ARTICLE', 'Editing an in-review post also keeps the composer beside it');
      assert.equal(await page.locator('[data-shared-post-form] textarea[name="content"]').inputValue(), 'Pending edit');
      assert.equal(await page.locator('[data-media-preview] video').isVisible(), true, 'Editing an in-review post shows its existing video');
      assert.equal(await page.locator('[data-file-name]').textContent(), 'Current video · Choose Video to replace');
      await page.locator('[data-cancel-edit]').click();
      assert.equal(await page.locator(`${root} [data-post-id]`).count(), 20);
      assert.equal(commentRequests, 0, 'Opening the feed must not request comments');
      await page.locator(profilePage ? '[data-more-profile-posts]' : '[data-more-posts]').click();
      await page.waitForFunction(selector => document.querySelectorAll(`${selector} [data-post-id]`).length === 25, root);
      assert.equal(commentRequests, 0, 'Loading older posts must not request comments');
      const first = page.locator(`${root} [data-post-id="25"]`);
      await first.locator('[data-comment-focus]').click();
      await page.waitForFunction(() => document.querySelectorAll('[data-post-id="25"] [data-comments] > div').length === 20);
      assert.equal(commentRequests, 1);
      assert.equal(await first.locator('[data-comment-id="1"] time').count(), 1, 'Comment shows its time');
      await first.locator('[data-comment-id="1"] .cite-comment-menu summary').click();
      assert.equal(await first.locator('[data-comment-id="1"] .cite-comment-menu [data-edit-comment]').count(), 1, 'Comment actions are available in the menu');
      await page.keyboard.press('Escape');
      await first.locator('[data-more-comments]').click();
      await page.waitForFunction(() => document.querySelectorAll('[data-post-id="25"] [data-comments] > div').length === 25);
      assert.equal(commentRequests, 2);
       await page.evaluate(() => {
         const button = document.querySelector('[data-post-id="25"] [data-reaction]');
         button.click(); button.click(); button.click();
       });
       assert.equal(await first.locator('[data-reaction]').isDisabled(), true, 'Like button waits for the update to finish');
       await page.waitForFunction(() => document.querySelector('[data-post-id="25"] [data-reaction]')?.getAttribute('aria-pressed') === 'true');
       assert.equal(reactionRequests, 1, 'Rapid clicks make only one reaction request');
       await first.locator('[data-reaction]').click();
       await page.waitForFunction(() => document.querySelector('[data-post-id="25"] [data-reaction]')?.getAttribute('aria-pressed') === 'false');
       assert.equal(reactionRequests, 2, 'The button works again after the first update');
       assert.equal(await page.locator('[data-toast-message]').filter({hasText: 'Action saved.'}).count(), 1, 'Reaction confirmations do not stack');
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
