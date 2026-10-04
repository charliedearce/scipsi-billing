import request from '@/utils/http'

export type NotificationChannel = 'work' | 'chat' | 'all'

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
  /** work = transactional only (default for bell); chat = chat_message; all = both */
  channel?: NotificationChannel
}

export interface PaginatedNotificationsResponse {
  data: InAppNotification[]
  unread_count: number
  channel?: NotificationChannel
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export function fetchNotifications(
  params?: FetchNotificationsParams,
  options?: { showErrorMessage?: boolean }
) {
  return request.get<PaginatedNotificationsResponse>({
    url: '/api/v1/notifications',
    params,
    showErrorMessage: options?.showErrorMessage
  })
}

export function fetchUnreadNotificationCount(channel: NotificationChannel = 'work') {
  return request.get<{ unread_count: number; channel?: NotificationChannel }>({
    url: '/api/v1/notifications/unread-count',
    params: { channel }
  })
}

export function markNotificationAsRead(id: number) {
  return request.post<{ message: string; data: InAppNotification }>({
    url: `/api/v1/notifications/${id}/read`
  })
}

export function markAllNotificationsAsRead(channel: NotificationChannel = 'work') {
  return request.post<{ message: string; updated_count: number; channel?: NotificationChannel }>({
    url: '/api/v1/notifications/read-all',
    params: { channel },
    data: { channel }
  })
}

export function isChatNotification(item: Pick<InAppNotification, 'type'>): boolean {
  return item.type === 'chat_message'
}
