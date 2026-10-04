import request from '@/utils/http'

export type DocumentPurpose =
  | 'BILLING_SUPPORT'
  | 'WITHHOLDING_CERTIFICATE'
  | 'EXEMPTION_EVIDENCE'
  | 'PAYMENT_PROOF'
  | 'PROFILE_AVATAR'

export interface DocumentTypeItem {
  id: number
  organization_id: number
  code: string
  name: string
  description?: string | null
  purpose: DocumentPurpose
  allowed_mime_types: string[]
  max_file_size_kb: number
  max_files: number
  is_active: boolean
  created_at: string
}

export interface DocumentRequirementItem {
  id: number
  organization_id: number
  location_id?: number | null
  service_type: string
  document_type_id: number
  document_type?: DocumentTypeItem
  is_required: boolean
  effective_from?: string | null
  effective_to?: string | null
  version: number
  lock_version: number
  created_at: string
}

export interface PrivateFileVersionItem {
  id: number
  private_file_id: number
  version_number: number
  original_name: string
  mime_type: string
  file_size_bytes: number
  sha256_checksum: string
  scan_status: 'PENDING' | 'CLEAN' | 'QUARANTINED'
  scan_details?: Record<string, any> | null
  uploaded_by: number
  replacement_reason?: string | null
  created_at: string
}

export interface PrivateFileItem {
  id: number
  organization_id: number
  location_id?: number | null
  document_type_id: number
  document_type?: DocumentTypeItem
  purpose: DocumentPurpose
  uploaded_by: number
  owner_id?: number | null
  current_version: number
  status: 'PENDING_SCAN' | 'CLEAN' | 'QUARANTINED' | 'REPLACED' | 'REJECTED'
  versions?: PrivateFileVersionItem[]
  latest_version?: PrivateFileVersionItem
  created_at: string
}

export function fetchDocumentTypes(params?: { purpose?: DocumentPurpose; is_active?: boolean }) {
  return request.get<DocumentTypeItem[]>({
    url: '/api/v1/document-types',
    params
  })
}

export function fetchDocumentTypeDetail(id: number) {
  return request.get<DocumentTypeItem>({
    url: `/api/v1/document-types/${id}`
  })
}

export function fetchCreateDocumentType(data: Partial<DocumentTypeItem>) {
  return request.post<DocumentTypeItem>({
    url: '/api/v1/document-types',
    data
  })
}

export function fetchUpdateDocumentType(id: number, data: Partial<DocumentTypeItem>) {
  return request.put<DocumentTypeItem>({
    url: `/api/v1/document-types/${id}`,
    data
  })
}

export function fetchDocumentRequirements(params?: {
  service_type?: string
  location_id?: number
}) {
  return request.get<DocumentRequirementItem[]>({
    url: '/api/v1/document-requirements',
    params
  })
}

export function fetchCreateDocumentRequirement(data: {
  service_type: string
  document_type_id: number
  location_id?: number | null
  is_required?: boolean
  effective_from?: string | null
  effective_to?: string | null
}) {
  return request.post<DocumentRequirementItem>({
    url: '/api/v1/document-requirements',
    data
  })
}

export function fetchUpdateDocumentRequirement(
  id: number,
  data: {
    is_required?: boolean
    effective_from?: string | null
    effective_to?: string | null
    lock_version: number
  }
) {
  return request.put<DocumentRequirementItem>({
    url: `/api/v1/document-requirements/${id}`,
    data
  })
}

export function uploadPrivateFile(formData: FormData) {
  return request.post<PrivateFileItem>({
    url: '/api/v1/files/upload',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

export function replacePrivateFile(fileId: number, formData: FormData) {
  return request.post<PrivateFileVersionItem>({
    url: `/api/v1/files/${fileId}/replace`,
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

export function fetchPrivateFileDetail(fileId: number) {
  return request.get<PrivateFileItem>({
    url: `/api/v1/files/${fileId}`
  })
}

/**
 * Retrieves a private document through the authenticated API client.  A plain
 * browser link would omit the bearer token, so callers must render the returned
 * blob locally rather than exposing a reusable storage URL.
 */
export async function downloadPrivateFile(fileId: number, version?: number) {
  const versionPath = version ? `/${version}` : ''

  try {
    return await request.get<Blob>({
      url: `/api/v1/files/${fileId}/download${versionPath}`,
      responseType: 'blob',
      showErrorMessage: false
    })
  } catch (error: any) {
    const payload = error?.data
    if (payload instanceof Blob) {
      try {
        const text = await payload.text()
        const json = JSON.parse(text) as { message?: string; error?: { message?: string } }
        const message = json.error?.message || json.message
        if (message) {
          throw new Error(message)
        }
      } catch (parsed) {
        if (parsed instanceof Error && parsed.message && parsed.message !== error?.message) {
          throw parsed
        }
      }
    }
    throw error instanceof Error ? error : new Error('Unable to download private file.')
  }
}
