import request from '@/utils/http'

export interface DocumentRevisionItem {
  id: number
  organization_id: number
  location_id?: number | null
  document_type: string
  document_id: number
  revision_number: number
  actor_id?: number | null
  actor?: {
    id: number
    name: string
    email: string
  } | null
  reason?: string | null
  changed_fields: string[]
  snapshot?: Record<string, any>
  snapshot_hash: string
  integrity_verified?: boolean
  lock_version: number
  created_at: string
}

export interface RevisionComparisonResult {
  revision_a: {
    id: number
    revision_number: number
    lock_version: number
    created_at: string
    actor?: { id: number; name: string; email: string } | null
    reason?: string | null
    snapshot_hash: string
  }
  revision_b: {
    id: number
    revision_number: number
    lock_version: number
    created_at: string
    actor?: { id: number; name: string; email: string } | null
    reason?: string | null
    snapshot_hash: string
  }
  diff: Record<string, { old: any; new: any }>
  changed_field_count: number
}

export interface BusinessAuditEventItem {
  id: number
  organization_id: number
  location_id?: number | null
  event_type: string
  aggregate_type: string
  aggregate_id: number
  aggregate_version: number
  actor_type?: string | null
  actor_id?: number | null
  actor?: {
    id: number
    name: string
    email: string
  } | null
  permission_snapshot?: string | null
  occurred_at: string
  business_date?: string | null
  reason?: string | null
  request_id?: string | null
  correlation_id?: string | null
  idempotency_key?: string | null
  parent_event_id?: number | null
  before_snapshot?: Record<string, any> | null
  after_snapshot?: Record<string, any> | null
  metadata?: Record<string, any> | null
}

export interface AuditEventSearchParams {
  page?: number
  per_page?: number
  event_type?: string
  aggregate_type?: string
  aggregate_id?: number
  actor_id?: number
  correlation_id?: string
  date_from?: string
  date_to?: string
}

export function fetchDocumentRevisions(type: string, id: number, params?: { page?: number; per_page?: number }) {
  return request.get<{
    current_page: number
    data: DocumentRevisionItem[]
    total: number
    last_page: number
  }>({
    url: `/api/v1/document-revisions/${type}/${id}`,
    params
  })
}

export function fetchDocumentRevisionDetail(id: number) {
  return request.get<DocumentRevisionItem>({
    url: `/api/v1/document-revisions/${id}`
  })
}

export function fetchCompareRevisions(from: number, to: number) {
  return request.get<RevisionComparisonResult>({
    url: '/api/v1/document-revisions/compare',
    params: { from, to }
  })
}

export function fetchBusinessAuditEvents(params?: AuditEventSearchParams) {
  return request.get<{
    current_page: number
    data: BusinessAuditEventItem[]
    total: number
    last_page: number
  }>({
    url: '/api/v1/audit-events',
    params
  })
}

export function fetchBusinessAuditEventDetail(id: number) {
  return request.get<BusinessAuditEventItem>({
    url: `/api/v1/audit-events/${id}`
  })
}
