import request from '@/utils/http'

export interface ContactPoint {
  id: number
  type: string
  value_masked: string
  is_verified: boolean
  verified_at?: string | null
  status: string
}

export interface NotificationPreferencesResponse {
  preferences: Record<string, { sms?: boolean; in_app?: boolean }>
  version: number
  contacts: ContactPoint[]
}

export function fetchNotificationPreferences() {
  return request.get<NotificationPreferencesResponse>({
    url: '/api/v1/portal/notification-preferences'
  })
}

export function updateNotificationPreferences(preferences: Record<string, any>) {
  return request.put<{ message: string; data: { preferences: Record<string, any>; version: number } }>({
    url: '/api/v1/portal/notification-preferences',
    data: { preferences }
  })
}
