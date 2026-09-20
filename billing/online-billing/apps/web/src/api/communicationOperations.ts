import request from '@/utils/http'

export interface CommunicationOperationsReport {
  report_code: 'COMMUNICATION_OPERATIONS'
  timezone: 'Asia/Manila'
  generated_at: string
  period: {
    date_from: string
    date_to: string
  }
  delivery_scope: {
    type: 'organization' | 'assigned_locations'
    announcement_operations_available: boolean
    message_content_included: false
  }
  deliveries: {
    total: number
    follow_up_required_count: number
    statuses: Array<{ status: string; count: number }>
    event_keys: Array<{ event_key: string; count: number }>
  }
  announcements: null | {
    current_effective_statuses: Record<string, number>
    interaction_actions: {
      seen: number
      acknowledged: number
      dismissed: number
    }
  }
}

/** Read-only aggregate dashboard. It deliberately returns no recipients, message bodies, or financial data. */
export function fetchCommunicationOperationsReport(params?: {
  date_from?: string
  date_to?: string
}) {
  return request.get<{ data: CommunicationOperationsReport }>({
    url: '/api/v1/reports/communications-operations',
    params
  })
}
