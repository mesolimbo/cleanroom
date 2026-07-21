# Cleanroom

<img src="extension/icons/icon128.png" width="128" height="128" alt="Cleanroom Icon">

Cleanroom serves sanitized, script-free versions of webpages at `https://cleanroom.whileyou.work`. A single-file PHP app fetches the requested page, strips scripts and trackers, and returns the clean HTML. The Chrome extension is a thin shortcut that opens the current page (or a right-clicked link) through the service.

Because sanitization happens server-side, it works anywhere a browser works: on mobile, just visit `https://cleanroom.whileyou.work/?url=<page-url>` (or use the landing page form), no extension needed. Sanitized pages are plain URLs, so they survive restarts and can be bookmarked or shared.

## Features

- One click opens the current page in Cleanroom
- Right-click any link to open it directly in Cleanroom
- Removes scripts, iframes, inline event handlers, and tracking attributes
- Custom content filtering with regular expressions (removes matching divs)
- Works on mobile by editing the URL or using the landing page form
- Response carries a `script-src 'none'` Content-Security-Policy as a second line of defense

## Usage

### Extension
1. Click the Cleanroom icon to open the current page sanitized, or
2. Right-click a link and select "Open in Cleanroom"

### Anywhere (including mobile)
All of these work (no scheme needed — https is assumed):

- `https://cleanroom.whileyou.work/cnn.com` (shortest: target as the path)
- `https://cleanroom.whileyou.work/?url=cnn.com`
- `https://cleanroom.whileyou.work/?url=https://example.com/article`
- Visit `https://cleanroom.whileyou.work` and paste a URL into the form

### Content Filtering

Filter patterns are regexes matched against div IDs and class names; matching divs are removed. Configure them in the extension options (right-click the icon, "Options", one pattern per line). The extension passes them along as a `filters` query parameter. When hand-editing URLs you can pass comma-separated patterns: `&filters=sidebar,^ad-container`.

Example filters:
```
^ad-container
sidebar
newsletter-signup
```

## Architecture

- **Extension** (`extension/`): Manifest V3 extension that builds the Cleanroom URL and opens it in a new tab. No content scripts, no page capture.
- **Server** (`server/index.php`): a single PHP file with no dependencies beyond stock extensions (DOMDocument, cURL), targeting PHP 8.0+. It validates the target URL (http/https only, public hosts only), fetches it with a 15s timeout and 20MB cap, sanitizes the HTML, injects a `<base>` tag so relative links keep working, and returns it with a restrictive CSP. Non-HTML targets get a redirect to the original.

## Local development

Requires PHP 8+ on the PATH (or pass `PHP=/path/to/php`); Docker and Node are needed for the Playwright suite. Everything goes through the Makefile (run `make` or `make help` to see targets):

```
make try         # open a browser with the extension loaded against a local Dockerized server
make test        # run the server test suite (plain PHP, fast)
make e2e         # build the Docker image and run the Playwright suite against it (headless)
make build       # build everything into dist/ (extension zip + server file)
make run         # build, then serve the built server locally on port 8080 (PORT=... to override)
make docker-run  # serve via Docker (Apache + PHP, closest to Dreamhost)
make dev         # serve the server source directly
make lint        # syntax-check the PHP and lint the JS
```

`make try` is the interactive way to check the whole thing: it starts the server in Docker (on port 18081; `TRY_PORT=...` to override) and opens a Chromium window with the extension installed and pointed at it (via the extension's "Server URL" option) — click the Cleanroom icon or right-click a link to see sanitized pages served locally. Close the window to shut everything down.

With the server running locally (`make run` or `make docker-run`), try `http://localhost:8080/?url=https://example.com/`. Set `CLEANROOM_ALLOW_PRIVATE=1` to let it fetch from localhost/private hosts (the tests do this).

The Playwright suite (`e2e/`) drives a real browser against the Dockerized server: landing page, form submission, script stripping, filters, CSP header, base tag, redirect behavior, and one live fetch of example.com.

## Deploying to Dreamhost

1. In the Dreamhost panel, add `cleanroom.whileyou.work` as a fully hosted subdomain (PHP 8.x FastCGI, HTTPS via the free Let's Encrypt cert).
2. `make build`, then upload the contents of `dist/server/` (`index.php`, `.htaccess`, `favicon.ico`, `favicon.gif`, `logo.svg`) to the subdomain's web directory (e.g. `~/cleanroom.whileyou.work/`).

That's it — no daemon, no database. The `.htaccess` enables the path-style URLs (`/cnn.com`); without it only the `?url=` form works.

The endpoint is public, so anyone who finds it can use it as a fetch proxy. On shared hosting the practical exposure is Dreamhost's fair-use policy rather than a bill.

## Extension installation (Developer Mode)

1. Clone this repository
2. Open Chrome and navigate to `chrome://extensions/`
3. Enable "Developer mode"
4. Click "Load unpacked" and select the `extension/` directory

## Packaging for Chrome Web Store

`make build-extension` produces `dist/cleanroom-extension.zip`, ready to upload through the [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole). Bump the version in `extension/manifest.json` for updates.

For the store's Privacy tab: the extension's single purpose is opening pages through the Cleanroom sanitizing service; it sends the page URL to the server only on explicit user action (declare this as "website content"/browsing-activity data used for app functionality, not sold or shared). The privacy policy URL is `https://cleanroom.whileyou.work/privacy` (served by the app itself). Permission justifications: `activeTab` reads the current tab's URL when the icon is clicked; `contextMenus` provides the "Open in Cleanroom" link menu item; `storage` keeps filter patterns and the optional server URL locally.

To regenerate the icon files from the SVG source: `npm run generate-icons`.

## Project Structure

```
cleanroom/
├── extension/                # Chrome extension (load this dir unpacked)
│   ├── manifest.json
│   ├── background.js         # Opens cleanroom URLs
│   ├── options.html/.js      # Filter pattern settings
│   └── icons/
├── server/                   # Sanitizer app for Dreamhost
│   ├── index.php             # The whole server: validation, fetch, sanitize
│   ├── Dockerfile            # Local Apache+PHP container for testing
│   └── tests/                # Test suite (plain PHP, no framework)
├── e2e/                      # Playwright suite against the Docker container
├── scripts/generate-icons.js # Icon generation from icons/icon.svg
├── artwork/                  # Store listing assets
├── Makefile                  # All tasks (run `make help`)
└── README.md
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Icon design by Cursor
- Built with modern web technologies
