// Keep track of the number of tabs opened
let tabCounter = 0;

// Maximum number of pages to keep in storage
const MAX_STORED_PAGES = 5;

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
  // Inject the content script to capture and sanitize the page
  chrome.scripting.executeScript({
    target: { tabId: tab.id },
    function: captureAndSanitizePage
  });
});

// Handle context menu clicks
chrome.contextMenus.onClicked.addListener((info, tab) => {
  if (info.menuItemId === 'openInCleanroom' && info.linkUrl) {
    // Create a temporary tab to fetch the content
    chrome.tabs.create({ url: info.linkUrl, active: false }, (tempTab) => {
      // Wait for the tab to load
      chrome.tabs.onUpdated.addListener(function listener(tabId, changeInfo) {
        if (tabId === tempTab.id && changeInfo.status === 'complete') {
          // Remove the listener
          chrome.tabs.onUpdated.removeListener(listener);
          
          // Inject the content script to capture and sanitize the page
          chrome.scripting.executeScript({
            target: { tabId: tempTab.id },
            function: captureAndSanitizePage
          }, (results) => {
            if (chrome.runtime.lastError) {
              console.error('Script injection failed:', chrome.runtime.lastError);
              chrome.tabs.remove(tempTab.id);
              return;
            }
          });
        }
      });
    });
  }
});

// Listen for messages from the content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'openSanitizedPage') {
    // Generate a unique ID for this sanitized page
    const pageId = 'cleanroom_page_' + Date.now() + '_' + tabCounter++;
    
    // Clean up old data before storing new content
    cleanupOldData();
    
    // Store the sanitized content in Chrome storage
    chrome.storage.local.set({
      [pageId]: {
        content: message.content,
        title: message.title,
        url: sender.tab.url,
        timestamp: Date.now()
      }
    }, () => {
      // Open the template in a new tab with the page ID
      const templateUrl = chrome.runtime.getURL('template.html');
      const url = `${templateUrl}?pageId=${pageId}`;
      
      // Open the template in a new tab
      chrome.tabs.create({ url: url }, (newTab) => {
        // Close the temporary tab after the sanitized page is opened
        if (sender.tab.id) {
          chrome.tabs.remove(sender.tab.id);
        }
      });
    });
  }
});

// Function to clean up old data
function cleanupOldData() {
  chrome.storage.local.get(null, function(items) {
    // Get all keys that start with 'cleanroom_page_'
    const pageKeys = Object.keys(items).filter(key => key.startsWith('cleanroom_page_'));
    
    // If we have more than the maximum number of pages, remove the oldest ones
    if (pageKeys.length > MAX_STORED_PAGES) {
      // Sort keys by timestamp (newest first)
      pageKeys.sort((a, b) => {
        return items[b].timestamp - items[a].timestamp;
      });
      
      // Get the keys to remove (the oldest ones)
      const keysToRemove = pageKeys.slice(MAX_STORED_PAGES);
      
      // Remove the oldest pages
      chrome.storage.local.remove(keysToRemove);
    }
    
    // Also remove any pages older than 1 hour
    const oneHourAgo = Date.now() - (60 * 60 * 1000);
    const oldPageKeys = pageKeys.filter(key => {
      return items[key].timestamp < oneHourAgo;
    });
    
    if (oldPageKeys.length > 0) {
      chrome.storage.local.remove(oldPageKeys);
    }
  });
}

function captureAndSanitizePage() {
  // Clone the document to avoid modifying the original
  const docClone = document.cloneNode(true);
  
  // Remove all script tags
  const scripts = docClone.getElementsByTagName('script');
  while (scripts.length > 0) {
    scripts[0].parentNode.removeChild(scripts[0]);
  }
  
  // Remove iframes
  const iframes = docClone.getElementsByTagName('iframe');
  while (iframes.length > 0) {
    iframes[0].parentNode.removeChild(iframes[0]);
  }
  
  // Get filter patterns from storage
  chrome.storage.local.get({
    filterPatterns: []
  }, function(items) {
    const filterPatterns = items.filterPatterns;
    
    // Remove inline event handlers and clean up the DOM
    function sanitizeNode(node) {
      if (node.nodeType === 1) { // Element node
        // Check if this is a div with an ID or class that matches any of the filter patterns
        if (node.tagName === 'DIV') {
          // Check ID
          if (node.id) {
            for (const pattern of filterPatterns) {
              try {
                const regex = new RegExp(pattern);
                if (regex.test(node.id)) {
                  // If there's a match, remove the div and stop checking patterns
                  node.parentNode.removeChild(node);
                  return null; // Return null to indicate the node was removed
                }
              } catch (e) {
                // Invalid regex, skip it
                console.error('Invalid regex pattern:', pattern, e);
              }
            }
          }
          
          // Check class
          if (node.className) {
            // Handle both string and SVGAnimatedString (for SVG elements)
            const classNames = typeof node.className === 'string' 
              ? node.className.split(/\s+/) 
              : [node.className.baseVal];
            
            for (const className of classNames) {
              for (const pattern of filterPatterns) {
                try {
                  const regex = new RegExp(pattern);
                  if (regex.test(className)) {
                    // If there's a match, remove the div and stop checking patterns
                    node.parentNode.removeChild(node);
                    return null; // Return null to indicate the node was removed
                  }
                } catch (e) {
                  // Invalid regex, skip it
                  console.error('Invalid regex pattern:', pattern, e);
                }
              }
            }
          }
        }
        
        // Remove all inline event handlers
        const attrs = node.attributes;
        for (let i = attrs.length - 1; i >= 0; i--) {
          const attr = attrs[i];
          if (attr.name.startsWith('on')) {
            node.removeAttribute(attr.name);
          }
        }
        
        // Remove tracking-related attributes
        node.removeAttribute('data-track');
        node.removeAttribute('data-analytics');
        node.removeAttribute('data-ga');
        
        // Process child nodes
        const childNodes = Array.from(node.childNodes);
        for (let i = 0; i < childNodes.length; i++) {
          const child = childNodes[i];
          const result = sanitizeNode(child);
          
          // If the child was removed, adjust the index
          if (result === null) {
            i--;
          }
        }
      }
      return node;
    }
    
    // Sanitize the entire document
    sanitizeNode(docClone.documentElement);
    
    // Get the sanitized content
    const sanitizedContent = docClone.documentElement.innerHTML;
    
    // Send the sanitized content back to the background script
    chrome.runtime.sendMessage({
      action: 'openSanitizedPage',
      content: sanitizedContent,
      title: document.title
    });
  });
} 