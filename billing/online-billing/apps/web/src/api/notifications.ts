import request from '@/utils/http'

export interface InAppNotification {
  id: number
  organization_id: number
  user_id: number
  event_id?: string | null
  type: string
  title: string
  body: string
  data?: Record<string, any> | null
  is_read: boolean
  read_at?: string | null
  created_at: string
}

export interface FetchNotificationsParams {
  page?: number
  per_page?: number
  unread_only?: boolean
  type?: string
}

export interface PaginatedNotificationsResponse {
  data: InAppNotification[]
  unread_count: number
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export function fetchNotifications(params?: FetchNotificationsParams) {
  return request.get<PaginatedNotificationsResponse>({
    url: '/api/v1/notifications',
    params
  })
}

export function fetchUnreadNotificationCount() {
  return request.get<{ unread_count: number }>({
    url: '/api/v1/notifications/unread-count'
  })
}

export function markNotificationAsRead(id: number) {
  return request.post<{ message: string; data: InAppNotification }>({
    url: `/api/v1/notifications/${id}/read`
  })
}

export function markAllNotificationsAsRead() {
  return request.post<{ message: string; updated_count: number }>({
    url: '/api/v1/notifications/read-all'
  })
}
