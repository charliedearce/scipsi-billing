import request from '@/utils/http'

export type DocumentCorrectionStatus =
  | 'PENDING'
  | 'APPROVED'
  | 'REJECTED'
  | 'CANCELLED'
  | 'EXECUTED'
export type DocumentCorrectionAction = 'INVOICE_CORRECTION' | 'RECEIPT_REVERSAL'
export type DocumentCorrectionType = 'INVOICE' | 'RECEIPT'

export interface DocumentCorrectionRequest {
  id: number
  document_type: DocumentCorrectionType
  requested_action: DocumentCorrectionAction
  status: DocumentCorrectionStatus
  reason: string
  decision_notes?: string | null
  requested_at: string
  reviewed_at?: string | null
  target_lock_version: number
  target?: {
    id: number
    location_id?: number | null
    document_number: string
    status: string
    business_date: string
    currency: string
    amount: string
    unapplied_amount?: string | null
    lock_version: number
  } | null
  target_revision?: {
    id: number
    revision_number: number
    lock_version: number
    created_at: string
  } | null
  requested_by?: { id: number; name: string } | null
  reviewed_by?: { id: number; name: string } | null
  events: Array<{
    id: number
    event_type: string
    from_status?: string | null
    to_status: string
    notes?: string | null
    created_at: string
    actor?: { id: number; name: string } | null
  }>
}

export interface DocumentCorrectionRequestPage {
  current_page: number
  data: DocumentCorrectionRequest[]
  total: number
  last_page: number
  per_page: number
}

export function fetchDocumentCorrectionRequests(status?: DocumentCorrectionStatus) {
  return request.get<DocumentCorrectionRequestPage>({
    url: '/api/v1/document-correction-requests',
    params: status ? { status } : undefined
  })
}

export function createDocumentCorrectionRequest(data: {
  document_type: DocumentCorrectionType
  document_number: string
  requested_action: DocumentCorrectionAction
  reason: string
}) {
  return request.post<DocumentCorrectionRequest>({
    url: '/api/v1/document-correction-requests',
    data
  })
}

export function approveDocumentCorrectionRequest(id: number, decisionNotes: string) {
  return request.post<DocumentCorrectionRequest>({
    url: `/api/v1/document-correction-requests/${id}/approve`,
    data: { decision_notes: decisionNotes }
  })
}

export function rejectDocumentCorrectionRequest(id: number, decisionNotes: string) {
  return request.post<DocumentCorrectionRequest>({
    url: `/api/v1/document-correction-requests/${id}/reject`,
    data: { decision_notes: decisionNotes }
  })
}
