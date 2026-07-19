const CLEANROOM_BASE = 'https://cleanroom.whileyou.work/';

// Build the cleanroom URL for a target page, including any filter patterns.
// Used for the GET flow (server fetches the page itself).
function cleanroomUrl(base, targetUrl, filterPatterns) {
  const url = new URL(base || CLEANROOM_BASE);
  url.searchParams.set('url', targetUrl);
  if (filterPatterns && filterPatterns.length > 0) {
    url.searchParams.set('filters', JSON.stringify(filterPatterns));
  }
  return url.toString();
}

function getSettings() {
  return chrome.storage.local.get({ filterPatterns: [], serverUrl: '' });
}

// The server fetches the target itself. Used for links (not open in a tab, so
// no DOM to capture) and as a fallback when DOM capture or POST fails.
function openViaServer(targetUrl, filterPatterns, serverUrl, replaceTabId) {
  return chrome.tabs.create({ url: cleanroomUrl(serverUrl, targetUrl, filterPatterns) })
    .then(() => {
      if (replaceTabId !== undefined) {
        chrome.tabs.remove(replaceTabId);
      }
    });
}

// Grab the DOM the browser already rendered for this tab. Runs in the page,
// so it needs host access (granted by activeTab on an icon click).
async function captureDom(tabId) {
  const [injection] = await chrome.scripting.executeScript({
    target: { tabId },
    func: () => '<!DOCTYPE html>\n' + document.documentElement.outerHTML
  });
  return injection.result;
}

// Sanitize the page the user is already looking at: capture its DOM and POST
// it, so the fetch happens at the user's IP and never trips a bot wall. The
// POST runs here in the worker, not the page, so the origin site's CSP can't
// block it. The sanitized HTML is handed to viewer.html to render, since a
// service worker can't create the blob/document to display it itself.
async function sanitizeCurrentTab(tab) {
  const { filterPatterns, serverUrl } = await getSettings();
  try {
    const html = await captureDom(tab.id);
    const endpoint = cleanroomUrl(serverUrl, tab.url, filterPatterns);
    const response = await fetch(endpoint, {
      method: 'POST',
      // A CORS-safelisted content type keeps this a simple request (no preflight)
      headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
      body: html
    });
    if (!response.ok) {
      throw new Error(`Cleanroom server returned ${response.status}`);
    }
    const sanitized = await response.text();
    const key = 'cleanroom-' + crypto.randomUUID();
    await chrome.storage.session.set({ [key]: sanitized });
    await chrome.tabs.create({ url: chrome.runtime.getURL('viewer.html') + '?k=' + key });
    chrome.tabs.remove(tab.id);
  } catch (error) {
    // Restricted pages (chrome://, the web store) block injection, and the
    // POST can fail; fall back to letting the server fetch the URL instead.
    console.warn('Cleanroom: DOM capture failed, falling back to server fetch', error);
    await openViaServer(tab.url, filterPatterns, serverUrl, tab.id);
  }
}

// Create context menu item
chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({
    id: 'openInCleanroom',
    title: 'Open in Cleanroom',
    contexts: ['link']
  });
});

// Icon click: sanitize the page already open in this tab
chrome.action.onClicked.addListener((tab) => {
  if (tab.id !== undefined && tab.url) {
    sanitizeCurrentTab(tab);
  }
});

// Context menu on a link: the target isn't loaded here, so the server fetches it
chrome.contextMenus.onClicked.addListener((info) => {
  if (info.menuItemId === 'openInCleanroom' && info.linkUrl) {
    getSettings().then(({ filterPatterns, serverUrl }) =>
      openViaServer(info.linkUrl, filterPatterns, serverUrl));
  }
});
