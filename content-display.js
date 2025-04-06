// This script runs in the context of the template.html page
document.addEventListener('DOMContentLoaded', function() {
  // Get the page ID from the URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const pageId = urlParams.get('pageId');
  
  if (pageId) {
    // Retrieve the sanitized content from Chrome storage
    chrome.storage.local.get([pageId], function(result) {
      const pageData = result[pageId];
      
      if (pageData) {
        // Insert the content into the page
        document.getElementById('sanitized-content').innerHTML = pageData.content;
        
        // Update the page title to match the original
        document.title = pageData.title;
        
        // Delete the content from storage to free up space
        chrome.storage.local.remove([pageId], function() {
          console.log('Content deleted from storage:', pageId);
        });
      } else {
        document.getElementById('sanitized-content').innerHTML = 
          '<p>Content not found.</p>';
      }
    });
  } else {
    document.getElementById('sanitized-content').innerHTML = 
      '<p>No page ID provided.</p>';
  }
}); 