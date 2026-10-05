import assert from 'node:assert/strict'
import { test } from 'node:test'
import { PDFDocument } from 'pdf-lib'
import { buildPhotoPdf, canCreatePhotoPdf } from './photoPdf'

const onePixelPng = Uint8Array.from(
  Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=',
    'base64'
  )
)

test('photo PDF is offered only when the document accepts PDF', () => {
  assert.equal(canCreatePhotoPdf(['application/pdf', 'image/jpeg']), true)
  assert.equal(canCreatePhotoPdf(['image/jpeg', 'image/png']), false)
})

test('each selected photo becomes one PDF page in order', async () => {
  const bytes = await buildPhotoPdf([
    { bytes: onePixelPng, mimeType: 'image/png', width: 100, height: 200 },
    { bytes: onePixelPng, mimeType: 'image/png', width: 200, height: 100 }
  ])
  const pdf = await PDFDocument.load(bytes)
  assert.equal(pdf.getPageCount(), 2)
  assert.ok(pdf.getPage(0).getHeight() > pdf.getPage(0).getWidth())
  assert.ok(pdf.getPage(1).getWidth() > pdf.getPage(1).getHeight())
})

test('an empty photo list cannot create a PDF', async () => {
  await assert.rejects(buildPhotoPdf([]), /photo/i)
})
