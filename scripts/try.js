// Opens a browser with the extension loaded and pointed at a local server.
// Used by `make try`; set CLEANROOM_TRY_SMOKE=1 for a non-interactive check.
/* global cleanroomUrl */ // defined in the extension service worker, used inside evaluate()
const { chromium } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const serverUrl = process.env.BASE_URL || 'http://localhost:8080/';
const smoke = process.env.CLEANROOM_TRY_SMOKE === '1';

(async () => {
  const extensionPath = path.resolve(__dirname, '..', 'extension');
  const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'cleanroom-try-'));

  const context = await chromium.launchPersistentContext(userDataDir, {
    // Extensions need a real Chromium; headless only works in smoke mode
    channel: 'chromium',
    headless: smoke,
    viewport: null,
    args: [
      `--disable-extensions-except=${extensionPath}`,
      `--load-extension=${extensionPath}`
    ]
  });

  let [worker] = context.serviceWorkers();
  if (!worker) {
    worker = await context.waitForEvent('serviceworker');
  }

  // Point the extension at the local server
  await worker.evaluate((url) => chrome.storage.local.set({ serverUrl: url }), serverUrl);

  const page = context.pages()[0] || (await context.newPage());
  await page.goto('https://example.com/');

  if (smoke) {
    // Exercise the same code path as an icon click, then verify the result
    const cleanUrl = await worker.evaluate((target) =>
      new Promise((resolve) => {
        chrome.storage.local.get({ filterPatterns: [], serverUrl: '' }, (items) => {
          resolve(cleanroomUrl(items.serverUrl, target, items.filterPatterns));
        });
      }), 'https://example.com/');
    if (!cleanUrl.startsWith(serverUrl)) {
      throw new Error(`Extension built ${cleanUrl}, expected it to start with ${serverUrl}`);
    }
    await page.goto(cleanUrl);
    const scripts = await page.locator('script').count();
    const base = await page.locator('base').getAttribute('href');
    if (scripts !== 0 || base !== 'https://example.com/') {
      throw new Error(`Sanitized page looks wrong (scripts: ${scripts}, base: ${base})`);
    }
    console.log('Smoke check passed: extension opens sanitized pages via ' + serverUrl);
    await context.close();
    return;
  }

  console.log('');
  console.log('Cleanroom is ready to try. The extension is loaded and pointed at ' + serverUrl);
  console.log('  - Click the Cleanroom icon (under the puzzle-piece menu) to sanitize the current page');
  console.log('  - Or right-click any link and choose "Open in Cleanroom"');
  console.log('  - Or browse to ' + serverUrl + ' and paste a URL');
  console.log('Close the browser window to stop.');
  await new Promise((resolve) => context.on('close', resolve));
})();
