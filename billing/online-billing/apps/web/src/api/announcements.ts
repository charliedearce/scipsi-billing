import request from '@/utils/http'

export type AnnouncementSeverity = 'INFO' | 'MAINTENANCE' | 'IMPORTANT' | 'CRITICAL'
export type AnnouncementAudienceType = 'all' | 'targeted'
export type AnnouncementStatus = 'draft' | 'scheduled' | 'published' | 'expired' | 'retired'

export interface ActiveAnnouncementUserState {
  seen: boolean
  acknowledged: boolean
  dismissed: boolean
  seen_at?: string | null
  acknowledged_at?: string | null
}

export interface ActiveAnnouncement {
  id: number
  version_id: number
  version_number: number
  title: string
  body: string
  severity: AnnouncementSeverity
  is_dismissible: boolean
  effective_start_at: string
  effective_end_at?: string | null
  change_reason?: string | null
  published_at?: string | null
  user_state: ActiveAnnouncementUserState
}

export interface AnnouncementMetrics {
  seen_count: number
  acknowledged_count: number
  dismissed_count: number
}

export interface RoleReference {
  id: number
  name: string
  label?: string
}

export interface LocationReference {
  id: number
  name: string
  code: string
}

export interface AnnouncementVersionData {
  id: number
  version_number: number
  title: string
  body: string
  severity: AnnouncementSeverity
  audience_type: AnnouncementAudienceType
  effective_start_at: string
  effective_end_at?: string | null
  is_dismissible: boolean
  change_reason?: string | null
  content_hash: string
  published_at?: string | null
  roles?: RoleReference[]
  locations?: LocationReference[]
  author?: {
    id: number
    name: string
    email: string
  }
  metrics?: AnnouncementMetrics
}

export interface AnnouncementAdminItem {
  id: number
  organization_id: number
  status: AnnouncementStatus
  effective_status: AnnouncementStatus
  lock_version: number
  created_at: string
  creator?: {
    id: number
    name: string
    email: string
  }
  retired_by?: {
    id: number
    name: string
    email: string
  } | null
  retired_at?: string | null
  retirement_reason?: string | null
  current_version?: AnnouncementVersionData | null
  metrics: AnnouncementMetrics
}

export interface FetchAnnouncementsParams {
  page?: number
  per_page?: number
  status?: string
  severity?: string
}

export interface CreateAnnouncementPayload {
  title: string
  body: string
  severity: AnnouncementSeverity
  audience_type?: AnnouncementAudienceType
  effective_start_at?: string | null
  effective_end_at?: string | null
  is_dismissible?: boolean
  role_ids?: number[]
  location_ids?: number[]
  publish_now?: boolean
}

export interface UpdateDraftPayload {
  title?: string
  body?: string
  severity?: AnnouncementSeverity
  audience_type?: AnnouncementAudienceType
  effective_start_at?: string | null
  effective_end_at?: string | null
  is_dismissible?: boolean
  role_ids?: number[]
  location_ids?: number[]
}

export interface PublishRevisionPayload {
  change_reason: string
  title?: string
  body?: string
  severity?: AnnouncementSeverity
  audience_type?: AnnouncementAudienceType
  effective_start_at?: string | null
  effective_end_at?: string | null
  is_dismissible?: boolean
  role_ids?: number[]
  location_ids?: number[]
}

export interface RetireAnnouncementPayload {
  retirement_reason: string
}

// User-facing active notices
export function fetchActiveAnnouncements() {
  return request.get<{ data: ActiveAnnouncement[]; total: number }>({
    url: '/api/v1/announcements/active'
  })
}

export function markAnnouncementSeen(id: number) {
  return request.post<{ message: string; data: any }>({
    url: `/api/v1/announcements/${id}/seen`
  })
}

export function acknowledgeAnnouncement(id: number) {
  return request.post<{ message: string; data: any }>({
    url: `/api/v1/announcements/${id}/acknowledge`
  })
}

export function dismissAnnouncement(id: number) {
  return request.post<{ message: string; data: any }>({
    url: `/api/v1/announcements/${id}/dismiss`
  })
}

// Admin-facing management
export function fetchAdminAnnouncements(params?: FetchAnnouncementsParams) {
  return request.get<{
    data: AnnouncementAdminItem[]
    meta: {
      current_page: number
      per_page: number
      total: number
      last_page: number
    }
  }>({
    url: '/api/v1/admin/announcements',
    params
  })
}

export function fetchAdminAnnouncement(id: number) {
  return request.get<{ data: AnnouncementAdminItem }>({
    url: `/api/v1/admin/announcements/${id}`
  })
}

export function createAnnouncement(data: CreateAnnouncementPayload) {
  return request.post<{ message: string; data: any }>({
    url: '/api/v1/admin/announcements',
    data
  })
}

export function updateDraftAnnouncement(id: number, data: UpdateDraftPayload) {
  return request.put<{ message: string; data: any }>({
    url: `/api/v1/admin/announcements/${id}/draft`,
    data
  })
}

export function publishAnnouncement(id: number, data?: PublishRevisionPayload) {
  return request.post<{ message: string; data: any }>({
    url: `/api/v1/admin/announcements/${id}/publish`,
    data
  })
}

export function retireAnnouncement(id: number, data: RetireAnnouncementPayload) {
  return request.post<{ message: string; data: any }>({
    url: `/api/v1/admin/announcements/${id}/retire`,
    data
  })
}

export function fetchAnnouncementHistory(id: number) {
  return request.get<{
    data: {
      announcement_id: number
      status: AnnouncementStatus
      effective_status: AnnouncementStatus
      retired_by?: any
      retired_at?: string | null
      retirement_reason?: string | null
      versions: AnnouncementVersionData[]
    }
  }>({
    url: `/api/v1/admin/announcements/${id}/history`
  })
}
