import request from '@/utils/http'

export type DocumentKind =
  | 'SERVICE'
  | 'SERVICE_NSCL'
  | 'PPA'
  | 'COLLECTION_RECEIPT'
  | 'ACKNOWLEDGEMENT_RECEIPT'
  | 'ACCOUNT_STATEMENT'
  | 'YELLOW_INVOICE'
  | 'WHITE_RECEIPT'
export type TemplateVersionStatus = 'DRAFT' | 'VALIDATED' | 'PUBLISHED' | 'RETIRED'

export interface DocumentTemplateVersion {
  id: number
  template_id: number
  version_number: number
  status: TemplateVersionStatus
  layout_schema_version: string
  layout_definition: Record<string, any>
  validation_summary?: {
    structure_valid: boolean
    fiscal_valid: boolean
    missing_fields: string[]
    missing_elements: string[]
    errors: string[]
    validated_at: string
  } | null
  created_by_user_id?: number | null
  published_by_user_id?: number | null
  published_at?: string | null
  retired_at?: string | null
  created_at: string
  updated_at: string
}

export interface DocumentTemplate {
  id: number
  organization_id: number
  document_kind: DocumentKind
  code: string
  name: string
  description?: string | null
  is_system: boolean
  latest_version?: DocumentTemplateVersion | null
  published_version?: DocumentTemplateVersion | null
  versions?: DocumentTemplateVersion[]
  created_at: string
  updated_at: string
}

export interface DocumentTemplateActivation {
  id: number
  organization_id: number
  document_kind: DocumentKind
  template_version_id: number
  location_id?: number | null
  series_id?: number | null
  effective_from: string
  effective_to?: string | null
  is_active: boolean
  template_version?: DocumentTemplateVersion
  location?: { id: number; name: string; code: string } | null
  series?: { id: number; series_code: string; prefix: string } | null
  activated_by?: { id: number; name: string } | null
  created_at: string
}

export type DocumentTemplateAssetType = 'LOGO' | 'WATERMARK' | 'SIGNATURE'
export type DocumentTemplateAssetStatus = 'ACTIVE' | 'RETIRED'

export interface DocumentTemplateAsset {
  id: number
  asset_type: DocumentTemplateAssetType
  name: string
  mime_type: 'image/png' | 'image/jpeg'
  file_size_bytes: number
  sha256_hash: string
  width_px: number | null
  height_px: number | null
  status: DocumentTemplateAssetStatus
  uploaded_by_user_id: number | null
  retired_at: string | null
  retired_by_user_id: number | null
  retirement_reason: string | null
  created_at: string | null
  updated_at: string | null
}

export function fetchTemplates() {
  return request.get<DocumentTemplate[]>({
    url: '/api/v1/admin/document-studio/templates'
  })
}

export function fetchTemplate(id: number) {
  return request.get<DocumentTemplate>({
    url: `/api/v1/admin/document-studio/templates/${id}`
  })
}

export function createTemplate(data: {
  document_kind: DocumentKind
  code: string
  name: string
  description?: string
  layout_definition?: Record<string, any>
}) {
  return request.post<DocumentTemplate>({
    url: '/api/v1/admin/document-studio/templates',
    data
  })
}

export function forkDraftVersion(templateId: number) {
  return request.post<DocumentTemplateVersion>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions`
  })
}

export function updateDraftVersion(
  templateId: number,
  versionId: number,
  layoutDefinition: Record<string, any>
) {
  return request.put<DocumentTemplateVersion>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions/${versionId}`,
    data: { layout_definition: layoutDefinition }
  })
}

export function validateTemplateVersion(templateId: number, versionId: number) {
  return request.post<NonNullable<DocumentTemplateVersion['validation_summary']>>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions/${versionId}/validate`
  })
}

export function previewTemplateVersion(
  templateId: number,
  versionId: number,
  layoutDefinition?: Record<string, unknown>
) {
  return request.post<{ pdf_base64: string; mime_type: string }>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions/${versionId}/preview?format=base64`,
    data: layoutDefinition ? { layout_definition: layoutDefinition } : undefined,
    timeout: 30000,
    showErrorMessage: false
  })
}

export function downloadDocumentTemplateAsset(id: number) {
  return request.get<Blob>({
    url: `/api/v1/admin/document-studio/assets/${id}/download`,
    responseType: 'blob',
    showErrorMessage: false
  })
}

export function publishTemplateVersion(templateId: number, versionId: number) {
  return request.post<DocumentTemplateVersion>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions/${versionId}/publish`
  })
}

export function retireTemplateVersion(templateId: number, versionId: number, reason: string) {
  return request.post<DocumentTemplateVersion>({
    url: `/api/v1/admin/document-studio/templates/${templateId}/versions/${versionId}/retire`,
    data: { reason }
  })
}

export function fetchActivations() {
  return request.get<DocumentTemplateActivation[]>({
    url: '/api/v1/admin/document-studio/activations'
  })
}

export function activateTemplateVersion(data: {
  template_version_id: number
  location_id?: number | null
  series_id?: number | null
  effective_from?: string
  effective_to?: string | null
}) {
  return request.post<DocumentTemplateActivation>({
    url: '/api/v1/admin/document-studio/activations',
    data
  })
}

export function fetchDocumentTemplateAssets() {
  return request.get<DocumentTemplateAsset[]>({
    url: '/api/v1/admin/document-studio/assets'
  })
}

export function uploadDocumentTemplateAsset(data: FormData) {
  return request.post<DocumentTemplateAsset>({
    url: '/api/v1/admin/document-studio/assets',
    data
  })
}

export function retireDocumentTemplateAsset(id: number, reason: string) {
  return request.post<DocumentTemplateAsset>({
    url: `/api/v1/admin/document-studio/assets/${id}/retire`,
    data: { reason }
  })
}
