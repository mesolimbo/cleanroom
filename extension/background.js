const CLEANROOM_BASE = 'https://cleanroom.whileyou.work/';

// Build the cleanroom URL for a target page, including any filter patterns
function cleanroomUrl(base, targetUrl, filterPatterns) {
  const url = new URL(base || CLEANROOM_BASE);
  url.searchParams.set('url', targetUrl);
  if (filterPatterns && filterPatterns.length > 0) {
    url.searchParams.set('filters', JSON.stringify(filterPatterns));
  }
  return url.toString();
}

function openInCleanroom(targetUrl, replaceTabId) {
  chrome.storage.local.get({ filterPatterns: [], serverUrl: '' }, (items) => {
    chrome.tabs.create({ url: cleanroomUrl(items.serverUrl, targetUrl, items.filterPatterns) }, () => {
      // Like the original capture flow, close the un-sanitized tab
      if (replaceTabId !== undefined) {
        chrome.tabs.remove(replaceTabId);
      }
    });
  });
}

// Create context menu item
chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({
    id: 'openInCleanroom',
    title: 'Open in Cleanroom',
    contexts: ['link']
  });
});

// Handle extension icon clicks
chrome.action.onClicked.addListener((tab) => {
  if (tab.url) {
    openInCleanroom(tab.url, tab.id);
  }
});

// Handle context menu clicks
chrome.contextMenus.onClicked.addListener((info) => {
  if (info.menuItemId === 'openInCleanroom' && info.linkUrl) {
    openInCleanroom(info.linkUrl);
  }
});
