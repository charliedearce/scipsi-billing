import request from '@/utils/http'

export interface AuditLogItem {
  id: number
  organization_id?: number | null
  user_id?: number | null
  action: string
  auditable_type?: string | null
  auditable_id?: string | null
  old_values?: Record<string, any> | null
  new_values?: Record<string, any> | null
  ip_address?: string | null
  user_agent?: string | null
  created_at: string
  user?: {
    id: number
    name: string
    email: string
  }
}

export interface AuditLogParams {
  page?: number
  per_page?: number
  action?: string
  user_id?: number
  auditable_type?: string
}

export interface PaginatedAuditResponse {
  current_page: number
  data: AuditLogItem[]
  total: number
  per_page: number
  last_page: number
}

export function fetchAuditLogs(params?: AuditLogParams) {
  return request.get<PaginatedAuditResponse>({
    url: '/api/v1/audit-logs',
    params
  })
}
