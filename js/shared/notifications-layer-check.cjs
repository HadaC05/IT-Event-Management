'use strict';

// UI-only regression checks: real page markup/styles, no login or API writes.
// Run with Node and Playwright available on NODE_PATH.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const scripts = ['notifications.js', 'interactions.js'].map(file => fs.readFileSync(path.join(__dirname, file), 'utf8'));
const styles = ['tailwind.css', 'adviser.css', 'adviser-modals.css'].map(file => fs.readFileSync(path.join(root, 'css', file), 'utf8')).join('\n');
let checks = 0;
function assert(ok, label) {
    if (!ok) throw new Error(`FAIL: ${label}`);
    checks++;
}

async function run() {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 844 }]) {
            const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
            await context.route('**/*', route => route.abort());
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            for (const file of fs.readdirSync(path.join(root, 'pages/adviser')).filter(file => file.endsWith('.html'))) {
                const html = fs.readFileSync(path.join(root, 'pages/adviser', file), 'utf8')
                    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
                    .replace(/<link\b[^>]*>/gi, '')
                    .replace('</head>', `<style>${styles}</style></head>`)
                    .replace('</body>', scripts.map(source => `<script>${source}</script>`).join('') + '</body>');
                await page.setContent(html, { waitUntil: 'domcontentloaded' });
                const result = await page.evaluate(async () => {
                    const failures = [];
                    let tested = 0;
                    const verify = (ok, label) => { tested++; if (!ok) failures.push(label); };
                    const tick = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                    const visible = element => {
                        const rect = element.getBoundingClientRect();
                        const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + Math.min(15, rect.height / 2));
                        return rect.width > 0 && rect.left >= 0 && rect.right <= innerWidth + 1 && rect.top >= 0 && rect.bottom <= innerHeight + 1 && element.contains(hit);
                    };
                    document.querySelectorAll('[data-page-loading-cloak]').forEach(element => element.remove());
                    document.querySelectorAll('dialog[id^="edit-"]').forEach(element => element.remove());
                    const toast = Notifications.info('UI-only layering check', { duration: 60000 });
                    await tick();
                    verify(visible(toast), 'page toast visible');
                    const obstruction = document.createElement('div');
                    obstruction.style.cssText = 'position:fixed;inset:0;z-index:1000;background:white';
                    document.querySelector('main')?.append(obstruction);
                    verify(visible(toast), 'toast above map/control-like layer');
                    obstruction.remove();
                    const persistentUndo = Notifications.undo('Persistent undo', () => false, { duration: 60000 });
                    await tick();
                    verify(visible(persistentUndo), 'page Undo visible');
                    for (const dialog of document.querySelectorAll('dialog')) {
                        dialog.showModal();
                        await tick();
                        verify(dialog.contains(toast) && visible(toast), 'existing toast follows modal');
                        verify(dialog.contains(persistentUndo) && visible(persistentUndo), 'existing Undo follows modal');
                        const error = Notifications.error('UI-only validation message', { duration: 60000 });
                        await tick();
                        verify(visible(error), 'new toast above modal header');
                        await new Promise(resolve => setTimeout(resolve, 180));
                        const hostBounds = error.parentElement.getBoundingClientRect();
                        verify(hostBounds.bottom <= dialog.getBoundingClientRect().top - 8, `notification lane does not cover modal content (${dialog.id || dialog.dataset.modalKind}: toast ${hostBounds.bottom}, dialog ${dialog.getBoundingClientRect().top}, space ${dialog.style.getPropertyValue('--dialog-notification-space')}, margin ${getComputedStyle(dialog).marginTop}, class ${dialog.className})`);
                        for (const button of dialog.querySelectorAll('footer [data-dialog-close]')) {
                            const box = button.getBoundingClientRect();
                            const text = document.createRange();
                            text.selectNodeContents(button);
                            verify(box.width >= text.getBoundingClientRect().width + 16, 'footer dismiss text has room');
                            verify(getComputedStyle(button).fontSize !== '20px', 'footer dismiss is not an icon close');
                        }
                        error.remove();
                        dialog.scrollTop = dialog.scrollHeight;
                        await tick();
                        verify(visible(toast), 'toast survives dialog scrolling');
                        dialog.close();
                        await tick();
                        verify(!toast.closest('dialog') && visible(toast), 'toast returns to page');
                        verify(!persistentUndo.closest('dialog') && visible(persistentUndo), 'Undo returns to page');
                        verify(!document.querySelector('[data-dialog-notification-region], [data-dialog-snackbar-region]'), 'closed modal hosts cleaned up');
                    }
                    persistentUndo.remove();
                    let undone = false;
                    const undo = Notifications.undo('UI-only undo message', () => { undone = true; return true; }, { duration: 60000 });
                    await tick();
                    undo.querySelector('button').click();
                    await tick();
                    verify(undone, 'Undo callback retained');
                    undo.remove();
                    toast.remove();
                    await tick();
                    verify(!document.querySelector('[data-notification-layer]:popover-open'), 'empty hosts leave top layer');
                    // The second dialog is first in DOM order, but last in open order.
                    const first = document.createElement('dialog');
                    const second = document.createElement('dialog');
                    first.innerHTML = '<button>First</button><button>Last</button>';
                    second.innerHTML = '<button>First</button><button>Last</button>';
                    document.body.append(second, first);
                    first.showModal();
                    second.showModal();
                    await tick();
                    verify(AppDialogs.topModal() === second, 'nested dialogs use open order');
                    const nestedToast = Notifications.warning('Nested dialog check', { duration: 60000 });
                    await tick();
                    verify(second.contains(nestedToast) && visible(nestedToast), 'toast belongs to top modal');
                    const buttons = second.querySelectorAll('button');
                    const lastButton = buttons[buttons.length - 1];
                    lastButton.focus();
                    lastButton.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
                    verify(document.activeElement === second.querySelector('button'), 'focus wraps in top modal');
                    second.close();
                    await tick();
                    verify(first.contains(nestedToast) && visible(nestedToast), 'toast returns to underlying modal');
                    first.close();
                    await tick();
                    nestedToast.remove();
                    first.remove();
                    second.remove();
                    const confirmation = document.querySelector('[data-confirm-dialog]');
                    if (confirmation) {
                        const accepted = Notifications.confirm({ message: 'UI-only accepted confirmation' });
                        confirmation.querySelector('[data-confirm-accept]').click();
                        verify(await accepted === true, 'accepted confirmation resolves');
                        const answer = Notifications.confirm({ message: 'UI-only confirmation' });
                        let settled = false;
                        answer.then(() => { settled = true; });
                        await tick();
                        verify(confirmation.open && !settled, 'previous close event does not dismiss reopened confirmation');
                        confirmation.close('dismissed');
                        verify(await answer === false, 'dismissed confirmation resolves');
                    }
                    return { tested, failures };
                });
                assert(!result.failures.length, `${viewport.width}px ${file}: ${result.failures.join(', ')}`);
                checks += result.tested;
                console.log(`PASS ${viewport.width}px ${file}: ${result.tested} checks`);
            }
            assert(!errors.length, `browser errors: ${errors.join('; ')}`);
            await context.close();
        }
    } finally {
        await browser.close();
    }
    console.log(`PASS: ${checks} checks. No API requests or database changes.`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
