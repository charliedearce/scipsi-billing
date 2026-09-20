/**
 * generate-icons.mjs
 * Generates PWA icon PNGs from the SVG source using @resvg/resvg-js.
 * Run: node scripts/generate-icons.mjs
 */
import { readFileSync, writeFileSync, mkdirSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..')

// Dynamic import for the ESM-first package
const { Resvg } = await import('@resvg/resvg-js')

const svgPath = resolve(root, 'public/icons/icon.svg')
const svgData = readFileSync(svgPath, 'utf8')
const outDir = resolve(root, 'public/icons')
mkdirSync(outDir, { recursive: true })

const sizes = [
  { name: 'icon-192x192.png', size: 192 },
  { name: 'icon-512x512.png', size: 512 },
  { name: 'icon-maskable-512x512.png', size: 512 }
]

for (const { name, size } of sizes) {
  const resvg = new Resvg(svgData, {
    fitTo: { mode: 'width', value: size }
  })
  const pngData = resvg.render()
  const pngBuffer = pngData.asPng()
  const outPath = resolve(outDir, name)
  writeFileSync(outPath, pngBuffer)
  console.log(`✓ ${name} (${size}x${size}) → ${outPath}`)
}

console.log('PWA icons generated.')
