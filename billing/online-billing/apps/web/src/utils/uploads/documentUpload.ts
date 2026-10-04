import type { DocumentTypeItem } from '@/api/documentRequirements'

const MIME_ACCEPT: Record<string, string> = {
  'application/pdf': '.pdf',
  'image/jpeg': '.jpg,.jpeg',
  'image/png': '.png',
  'image/webp': '.webp'
}

export function acceptFromMimeTypes(mimeTypes?: string[] | null): string {
  if (!mimeTypes || mimeTypes.length === 0) {
    return '.pdf,.jpg,.jpeg,.png'
  }

  return mimeTypes
    .map((mime) => MIME_ACCEPT[mime] || mime)
    .filter(Boolean)
    .join(',')
}

export function formatMaxUploadSize(maxKb?: number | null): string {
  if (!maxKb || maxKb <= 0) return 'configured limit'
  if (maxKb >= 1024) {
    const mb = maxKb / 1024
    return Number.isInteger(mb) ? `${mb} MB` : `${mb.toFixed(1)} MB`
  }
  return `${maxKb} KB`
}

export function validateUploadAgainstDocumentType(
  file: File,
  docType: Pick<DocumentTypeItem, 'name' | 'allowed_mime_types' | 'max_file_size_kb'>
): string | null {
  const allowed = docType.allowed_mime_types || []
  if (allowed.length > 0 && file.type && !allowed.includes(file.type)) {
    return `${docType.name} accepts ${allowed.join(', ')} only.`
  }

  const sizeKb = Math.ceil(file.size / 1024)
  if (docType.max_file_size_kb && sizeKb > docType.max_file_size_kb) {
    return `${docType.name} is limited to ${formatMaxUploadSize(docType.max_file_size_kb)}. This file is ${formatMaxUploadSize(sizeKb)}.`
  }

  return null
}
