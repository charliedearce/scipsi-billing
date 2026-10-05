import { PDFDocument } from 'pdf-lib'

export type PhotoPage = {
  bytes: Uint8Array
  mimeType: 'image/jpeg' | 'image/png'
  width: number
  height: number
}

export function canCreatePhotoPdf(mimeTypes?: string[] | null): boolean {
  return !!mimeTypes?.some((type) => type.toLowerCase() === 'application/pdf')
}

export async function buildPhotoPdf(photos: PhotoPage[]): Promise<Uint8Array> {
  if (!photos.length) throw new Error('Choose at least one photo.')
  const pdf = await PDFDocument.create()
  for (const photo of photos) {
    const image =
      photo.mimeType === 'image/png'
        ? await pdf.embedPng(photo.bytes)
        : await pdf.embedJpg(photo.bytes)
    const portrait = photo.height >= photo.width
    const [width, height] = portrait ? [595, 842] : [842, 595]
    const page = pdf.addPage([width, height])
    const scaled = image.scaleToFit(width - 40, height - 40)
    page.drawImage(image, {
      x: (width - scaled.width) / 2,
      y: (height - scaled.height) / 2,
      width: scaled.width,
      height: scaled.height
    })
  }
  return pdf.save()
}

export async function photoFilesToPdf(files: File[]): Promise<File> {
  if (!files.length) throw new Error('Choose at least one photo.')
  const photos: PhotoPage[] = []
  for (const file of files) {
    const url = URL.createObjectURL(file)
    try {
      const image = new Image()
      image.src = url
      await image.decode()
      const ratio = Math.min(1, 2000 / Math.max(image.naturalWidth, image.naturalHeight))
      const canvas = document.createElement('canvas')
      canvas.width = Math.round(image.naturalWidth * ratio)
      canvas.height = Math.round(image.naturalHeight * ratio)
      const context = canvas.getContext('2d')
      if (!context || !canvas.width || !canvas.height) {
        throw new Error(`Could not read ${file.name}. Choose a JPEG or PNG photo.`)
      }
      context.drawImage(image, 0, 0, canvas.width, canvas.height)
      const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.82)
      )
      if (!blob) throw new Error(`Could not read ${file.name}. Choose a JPEG or PNG photo.`)
      photos.push({
        bytes: new Uint8Array(await blob.arrayBuffer()),
        mimeType: 'image/jpeg',
        width: canvas.width,
        height: canvas.height
      })
    } finally {
      URL.revokeObjectURL(url)
    }
  }
  const bytes = await buildPhotoPdf(photos)
  return new File([new Uint8Array(bytes)], 'camera-documents.pdf', { type: 'application/pdf' })
}
