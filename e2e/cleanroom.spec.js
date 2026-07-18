const { test, expect } = require('@playwright/test');
const http = require('http');

// The container reaches this fixture server on the host via host.docker.internal
const FIXTURE_PORT = 18093;
const FIXTURE_HOST = process.env.FIXTURE_HOST || 'host.docker.internal';
const fixtureUrl = `http://${FIXTURE_HOST}:${FIXTURE_PORT}`;

let fixture;

test.beforeAll(async () => {
  fixture = http.createServer((req, res) => {
    if (req.url === '/page') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('<html><head><title>Fixture</title></head><body><script>document.title="HACKED"</script><div class="sidebar">SIDEBAR</div><p>CONTENT</p></body></html>');
    } else if (req.url === '/image') {
      res.writeHead(200, { 'Content-Type': 'image/png' });
      res.end('notreallyapng');
    } else {
      res.writeHead(404, { 'Content-Type': 'text/html' });
      res.end('<html><body>nope</body></html>');
    }
  });
  await new Promise((resolve) => fixture.listen(FIXTURE_PORT, '0.0.0.0', resolve));
});

test.afterAll(() => {
  fixture.close();
});

test('landing page shows the form', async ({ page }) => {
  const response = await page.goto('/');
  expect(response.status()).toBe(200);
  await expect(page.locator('input[name=url]')).toBeVisible();
});

test('submitting the form opens a sanitized page', async ({ page }) => {
  await page.goto('/');
  await page.fill('input[name=url]', `${fixtureUrl}/page`);
  await page.click('button');
  await expect(page.locator('p', { hasText: 'CONTENT' })).toBeVisible();
  expect(await page.locator('script').count()).toBe(0);
  await expect(page).toHaveTitle('Fixture');
});

test('sanitized page carries CSP header and base tag', async ({ page }) => {
  const response = await page.goto(`/?url=${encodeURIComponent(`${fixtureUrl}/page`)}`);
  expect(response.status()).toBe(200);
  expect(response.headers()['content-security-policy']).toContain('script-src \'none\'');
  await expect(page.locator('base')).toHaveAttribute('href', `${fixtureUrl}/page`);
});

test('filters remove matching divs', async ({ page }) => {
  await page.goto(`/?url=${encodeURIComponent(`${fixtureUrl}/page`)}&filters=sidebar`);
  await expect(page.locator('p', { hasText: 'CONTENT' })).toBeVisible();
  expect(await page.locator('div.sidebar').count()).toBe(0);
});

test('invalid URLs are rejected', async ({ page }) => {
  const response = await page.goto(`/?url=${encodeURIComponent('ftp://example.com/x')}`);
  expect(response.status()).toBe(400);
  await expect(page.locator('body')).toContainText('Invalid URL');
});

test('non-HTML content redirects to the original', async ({ request }) => {
  const response = await request.get(`/?url=${encodeURIComponent(`${fixtureUrl}/image`)}`, {
    maxRedirects: 0
  });
  expect(response.status()).toBe(302);
  expect(response.headers()['location']).toBe(`${fixtureUrl}/image`);
});

test('sanitizes a real page from the internet', async ({ page }) => {
  await page.goto(`/?url=${encodeURIComponent('https://example.com/')}`);
  await expect(page.locator('h1')).toContainText('Example Domain');
  expect(await page.locator('script').count()).toBe(0);
});

test('schemeless url param works', async ({ page }) => {
  await page.goto('/?url=example.com');
  await expect(page.locator('h1')).toContainText('Example Domain');
});

test('path-style url works', async ({ page }) => {
  await page.goto('/example.com');
  await expect(page.locator('h1')).toContainText('Example Domain');
});
