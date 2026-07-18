// Save options to chrome.storage
function saveOptions() {
  const filterPatterns = document.getElementById('filterPatterns').value;
  const serverUrl = document.getElementById('serverUrl').value.trim();

  // Split the textarea content by newlines and filter out empty lines
  const patterns = filterPatterns
    .split('\n')
    .map(pattern => pattern.trim())
    .filter(pattern => pattern.length > 0);

  chrome.storage.local.set({
    filterPatterns: patterns,
    serverUrl: serverUrl
  }, () => {
    // Show status message
    const status = document.getElementById('status');
    status.textContent = 'Options saved.';
    status.className = 'status success';
    status.style.display = 'block';

    // Hide the status message after 2 seconds
    setTimeout(() => {
      status.style.display = 'none';
    }, 2000);
  });
}

// Restore options from chrome.storage
function restoreOptions() {
  chrome.storage.local.get({
    // Defaults if nothing is set
    filterPatterns: [],
    serverUrl: ''
  }, (items) => {
    // Join the patterns with newlines and set the textarea value
    document.getElementById('filterPatterns').value = items.filterPatterns.join('\n');
    document.getElementById('serverUrl').value = items.serverUrl;
  });
}

// Add event listeners when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', restoreOptions);
document.getElementById('save').addEventListener('click', saveOptions);
