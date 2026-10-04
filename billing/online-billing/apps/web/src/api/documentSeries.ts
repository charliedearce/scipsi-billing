import request from '@/utils/http'

export type DocumentSeriesKind = 'SALES_INVOICE' | 'COLLECTION_RECEIPT' | 'ACKNOWLEDGEMENT_RECEIPT'

export interface AdminDocumentSeries {
  id: number
  organization_id: number
  location_id: number | null
  document_type: DocumentSeriesKind
  series_code: string
  prefix: string
  current_number: number
  start_number: number
  end_number: number | null
  padding_length: number
  is_active: boolean
  lock_version: number
  location?: { id: number; code: string; name: string } | null
}

export function fetchAdminDocumentSeries() {
  return request.get<AdminDocumentSeries[]>({ url: '/api/v1/admin/document-series' })
}

export function updateDocumentSeriesPrefix(
  id: number,
  data: { prefix: string; expected_lock_version: number; reason: string }
) {
  return request.put<AdminDocumentSeries>({
    url: `/api/v1/admin/document-series/${id}/prefix`,
    data
  })
}
