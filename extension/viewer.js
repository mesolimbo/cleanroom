// Renders the sanitized HTML the service worker stashed for this tab. Writing
// it into this extension page means the document is governed by the
// extension's CSP (scripts already stripped, styles and images allowed), not
// the original site's, and relative resources resolve via the injected <base>.
async function render() {
  const key = new URLSearchParams(location.search).get('k');
  if (!key) {
    document.body.textContent = 'Cleanroom: nothing to display.';
    return;
  }
  const stored = await chrome.storage.session.get(key);
  const html = stored[key];
  await chrome.storage.session.remove(key);
  if (typeof html !== 'string') {
    document.body.textContent = 'Cleanroom could not load this page.';
    return;
  }
  document.open();
  document.write(html);
  document.close();
}

render();
