# Cleanroom Chrome Extension

<img src="icons/icon128.png" width="128" height="128" alt="Cleanroom Icon">

A Chrome extension that captures and sanitizes webpage content, ensuring a clean viewing experience by removing scripts and trackers.

## Features

- Captures webpage content with a single click
- Sanitizes content by removing scripts and trackers
- Preserves the original layout and styling
- Provides a clean, distraction-free reading experience
- Customizable content filtering using regular expressions
- Works on most websites

## Installation

### From Chrome Web Store (Coming Soon)

1. Visit the Chrome Web Store
2. Search for "Cleanroom"
3. Click "Add to Chrome"

### Manual Installation (Developer Mode)

1. Download or clone this repository
2. Open Chrome and navigate to `chrome://extensions/`
3. Enable "Developer mode" in the top right corner
4. Click "Load unpacked" and select the extension directory

## Usage

1. Navigate to any webpage you want to capture
2. Click the Cleanroom icon in your Chrome toolbar
3. The extension will capture and sanitize the page content
4. View the clean version of the page in a new tab

### Optional Content Filtering

Cleanroom allows you to filter out specific elements from captured pages using regular expressions:

1. Right-click the Cleanroom icon and select "Options"
2. Enter your filter patterns, one per line
3. Each pattern will be matched against element IDs and classes
4. Elements matching any pattern will be removed from the sanitized page

Example filters:
```
^ad-container
sidebar
newsletter-signup
social-share
popup
```

These filters would remove:
- Elements with IDs/classes starting with "ad-container"
- Any elements with "sidebar" in their ID/class
- Newsletter signup forms
- Social media sharing widgets
- Popup elements

Tips for effective filtering:
- Patterns are case-sensitive
- Use `^` to match the start of an ID/class
- Use `$` to match the end of an ID/class
- Keep patterns simple and specific
- Test your patterns on a few pages to ensure they're not too aggressive

## How It Works

Cleanroom uses a combination of techniques to sanitize webpage content:

1. Captures the current page's HTML content
2. Removes scripts, iframes, and other potentially harmful elements
3. Preserves the original layout and styling
4. Creates a clean, readable version of the page

## Development

### Prerequisites

- Node.js (for development tools)
- Chrome browser

### Setup

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/cleanroom.git
   cd cleanroom
   ```

2. Install dependencies:
   ```
   npm install
   ```

3. Load the extension in Chrome:
   - Open Chrome and go to `chrome://extensions/`
   - Enable "Developer mode"
   - Click "Load unpacked" and select the extension directory

### Building

To generate the icon files:

```
node generate-icons.js
```

### Packaging for Chrome Web Store

To package the extension for distribution on the Chrome Web Store:

1. Create a ZIP file of the extension:
   ```
   # On Windows
   powershell Compress-Archive -Path manifest.json,background.js,content.js,icons -DestinationPath cleanroom.zip
   
   # On macOS/Linux
   zip -r cleanroom.zip manifest.json background.js content.js icons/
   ```

2. Sign up for a Chrome Web Store Developer account:
   - Visit the [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole)
   - Pay the one-time registration fee ($5)

3. Submit your extension:
   - Log in to the [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole)
   - Click "New Item" and upload your ZIP file
   - Fill in the required information:
     - Detailed description
     - At least one screenshot of the extension in action
     - Icon (128x128)
     - Category (Productivity)
     - Language
     - Privacy policy URL
   - Pay the registration fee if you haven't already
   - Submit for review

4. Wait for review:
   - Google will review your extension (typically takes a few business days)
   - You'll receive an email when the review is complete
   - If approved, your extension will be published on the Chrome Web Store

5. Updates:
   - To update your extension, make your changes
   - Increment the version number in manifest.json
   - Create a new ZIP file
   - Upload the new ZIP file through the Developer Dashboard
   - Submit for review

## Project Structure

```
cleanroom/
├── icons/                  # Extension icons
│   ├── icon.svg           # Source SVG icon
│   ├── icon16.png         # 16x16 icon
│   ├── icon32.png         # 32x32 icon
│   ├── icon48.png         # 48x48 icon
│   └── icon128.png        # 128x128 icon
├── background.js          # Background script (main extension logic)
├── content.js             # Content script for page interaction
├── content-display.js     # Script for displaying sanitized content
├── template.html          # Template for displaying sanitized pages
├── options.html          # Options page for filter settings
├── options.js            # Options page logic
├── manifest.json         # Extension manifest
├── generate-icons.js     # Icon generation script
├── package.json         # Node.js dependencies
├── package-lock.json    # Locked Node.js dependencies
├── .eslintrc.json      # ESLint configuration
├── cleanroom.zip       # Packaged extension for Chrome Web Store
└── README.md            # Documentation
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Icon design by Cursor
- Built with modern web technologies 