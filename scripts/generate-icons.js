const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const sizes = [16, 32, 48, 128];
const faviconSizes = [16, 32, 48];
const svgBuffer = fs.readFileSync(path.join(__dirname, '..', 'extension', 'icons', 'icon.svg'));
const serverDir = path.join(__dirname, '..', 'server');

/*
 * Generates the extension icons and the server favicons from a single SVG file.
 */
async function generateIcons() {
  for (const size of sizes) {
    await sharp(svgBuffer)
      .resize(size, size)
      .png({ background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .toFile(path.join(__dirname, '..', 'extension', 'icons', `icon${size}.png`));
    console.log(`Generated ${size}x${size} icon with transparent background`);
  }
}

// ICO container with embedded PNGs (supported since Windows Vista)
async function generateFavicons() {
  const pngs = await Promise.all(faviconSizes.map((size) =>
    sharp(svgBuffer)
      .resize(size, size)
      .png({ background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .toBuffer()));

  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0); // reserved
  header.writeUInt16LE(1, 2); // type: icon
  header.writeUInt16LE(pngs.length, 4);

  const entries = [];
  let offset = 6 + 16 * pngs.length;
  pngs.forEach((png, i) => {
    const size = faviconSizes[i];
    const entry = Buffer.alloc(16);
    entry.writeUInt8(size, 0); // width
    entry.writeUInt8(size, 1); // height
    entry.writeUInt16LE(1, 4); // color planes
    entry.writeUInt16LE(32, 6); // bits per pixel
    entry.writeUInt32LE(png.length, 8);
    entry.writeUInt32LE(offset, 12);
    offset += png.length;
    entries.push(entry);
  });

  fs.writeFileSync(path.join(serverDir, 'favicon.ico'), Buffer.concat([header, ...entries, ...pngs]));
  console.log('Generated favicon.ico (16, 32, 48)');

  await sharp(svgBuffer)
    .resize(32, 32)
    .gif()
    .toFile(path.join(serverDir, 'favicon.gif'));
  console.log('Generated favicon.gif (32)');
}

generateIcons().then(generateFavicons).catch(console.error);
