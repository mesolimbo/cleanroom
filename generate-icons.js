const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const sizes = [16, 32, 48, 128];
const svgBuffer = fs.readFileSync(path.join(__dirname, 'icons', 'icon.svg'));

/*
 * This script generates icons of different sizes from a single SVG file.
 */
async function generateIcons() {
  for (const size of sizes) {
    await sharp(svgBuffer)
      .resize(size, size)
      .png({ background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .toFile(path.join(__dirname, 'icons', `icon${size}.png`));
    console.log(`Generated ${size}x${size} icon with transparent background`);
  }
}

generateIcons().catch(console.error);
