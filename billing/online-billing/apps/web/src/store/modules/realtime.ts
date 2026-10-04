/**
 * Shared realtime + in-app notification badge state (W27 / P1-08).
 * Work (transactional) and Chat badges stay separate so tellers are not confused.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ElNotification } from 'element-plus'
import {
  fetchNotifications,
  fetchUnreadNotificationCount,
  isChatNotification,
  markAllNotificationsAsRead,
  markNotificationAsRead,
  type InAppNotification
} from '@/api/notifications'
import { fetchConversations } from '@/api/chat'
import { disconnectEcho, initEcho, onUserNotification, type RealtimeConfig } from '@/utils/echo'
import { useUserStore } from '@/store/modules/user'
import request from '@/utils/http'
import { mittBus } from '@/utils/sys'
import {
  closeBrowserChatAlerts,
  showBrowserChatAlert,
  showBrowserWorkAlert
} from '@/utils/browserChatNotifications'

let unsubscribeNotification: (() => void) | null = null
let pollTimer: ReturnType<typeof setInterval> | null = null
let bootstrapped = false
let chatPreview: ReturnType<typeof ElNotification> | null = null
let latestChatNotificationId: number | null = null
let latestWorkNotificationId: number | null = null
let workPollInFlight = false
const previewedChatIds = new Set<number>()
const alertedWorkIds = new Set<number>()

function showWorkAlert(item: InAppNotification): void {
  if (!item.id || alertedWorkIds.has(item.id)) return
  alertedWorkIds.add(item.id)
  if (alertedWorkIds.size > 100) alertedWorkIds.delete(alertedWorkIds.values().next().value!)
  showBrowserWorkAlert(item)
}

function showChatPreview(item: InAppNotification): void {
  if (!item.id || previewedChatIds.has(item.id)) return

  const conversationId = Number(item.data?.conversation_id)
  if (!Number.isSafeInteger(conversationId) || conversationId <= 0) return

  previewedChatIds.add(item.id)
  // Bound the duplicate guard for long-lived sessions.
  if (previewedChatIds.size > 100) previewedChatIds.delete(previewedChatIds.values().next().value!)

  // The native browser popup is the primary alert after the user opts in.
  // Keep the in-page preview as a fallback when permission is unavailable.
  if (showBrowserChatAlert(item, conversationId)) return
  if (typeof document === 'undefined' || document.visibilityState !== 'visible') return

  chatPreview?.close()
  chatPreview = ElNotification({
    title: item.title || 'New chat message',
    message: item.body || 'Open the conversation to read the message.',
    position: 'bottom-left',
    duration: 5000,
    customClass: 'chat-preview-notification',
    onClick: () => {
      chatPreview?.close()
      mittBus.emit('openChat', { conversationId })
    }
  })
}

async function refreshChatPreviews(): Promise<boolean> {
  try {
    const response = await fetchNotifications(
      { page: 1, per_page: 20, channel: 'chat' },
      { showErrorMessage: false }
    )
    const incoming = response.data || []
    if (latestChatNotificationId === null) {
      latestChatNotificationId = Math.max(0, ...incoming.map((item) => Number(item.id) || 0))
      return false
    }

    const newest = incoming.filter((item) => Number(item.id) > latestChatNotificationId!)
    for (const item of newest.reverse()) {
      showChatPreview(item)
    }
    latestChatNotificationId = Math.max(
      latestChatNotificationId,
      ...incoming.map((item) => Number(item.id) || 0)
    )
    return newest.length > 0
  } catch {
    // A temporary API outage must not interrupt the chat workspace.
    return false
  }
}

export const useRealtimeStore = defineStore('realtimeStore', () => {
  const unreadNotificationCount = ref(0)
  const unreadChatCount = ref(0)
  const recentNotifications = ref<InAppNotification[]>([])
  const wsConnected = ref(false)
  const panelOpenTick = ref(0)

  async function refreshWorkAlerts(): Promise<boolean> {
    if (workPollInFlight) return false
    workPollInFlight = true
    try {
      const response = await fetchNotifications(
        { page: 1, per_page: 20, channel: 'work' },
        { showErrorMessage: false }
      )
      const incoming = (response.data || []).filter((item) => !isChatNotification(item))
      recentNotifications.value = incoming
      unreadNotificationCount.value = response.unread_count ?? unreadNotificationCount.value
      if (latestWorkNotificationId === null) {
        latestWorkNotificationId = Math.max(0, ...incoming.map((item) => Number(item.id) || 0))
        return false
      }
      const newest = incoming.filter((item) => Number(item.id) > latestWorkNotificationId!)
      for (const item of newest.reverse()) showWorkAlert(item)
      latestWorkNotificationId = Math.max(
        latestWorkNotificationId,
        ...incoming.map((item) => Number(item.id) || 0)
      )
      return newest.length > 0
    } catch {
      return false
    } finally {
      workPollInFlight = false
    }
  }

  async function fetchRealtimeConfig(): Promise<Partial<RealtimeConfig>> {
    try {
      return await request.get<RealtimeConfig>({
        url: '/api/v1/realtime/config',
        showErrorMessage: false
      })
    } catch {
      return {}
    }
  }

  async function refreshUnreadCounts(): Promise<void> {
    try {
      const roles = useUserStore().info?.roles
      const canChat =
        Array.isArray(roles) &&
        roles.some((role) => ['Customer', 'Teller', 'Administrator'].includes(String(role)))
      const [notif, conversations] = await Promise.all([
        fetchUnreadNotificationCount('work'),
        canChat ? fetchConversations({ per_page: 50, status: 'open' }).catch(() => null) : null
      ])
      unreadNotificationCount.value = notif.unread_count ?? 0
      if (conversations?.data) {
        unreadChatCount.value = conversations.data.reduce(
          (sum, c) => sum + (c.unread_count ?? 0),
          0
        )
      }
    } catch {
      // Non-fatal: panel can still open with empty state
    }
  }

  async function refreshRecentNotifications(): Promise<void> {
    try {
      const res = await fetchNotifications({ page: 1, per_page: 20, channel: 'work' })
      recentNotifications.value = (res.data || []).filter((n) => !isChatNotification(n))
      unreadNotificationCount.value = res.unread_count ?? unreadNotificationCount.value
    } catch {
      recentNotifications.value = []
    }
  }

  async function markOneRead(id: number): Promise<void> {
    await markNotificationAsRead(id)
    const item = recentNotifications.value.find((n) => n.id === id)
    if (item && !item.is_read) {
      item.is_read = true
      item.read_at = new Date().toISOString()
      if (unreadNotificationCount.value > 0) unreadNotificationCount.value--
    }
  }

  async function markAllRead(): Promise<void> {
    await markAllNotificationsAsRead('work')
    recentNotifications.value = recentNotifications.value.map((n) => ({
      ...n,
      is_read: true,
      read_at: n.read_at || new Date().toISOString()
    }))
    unreadNotificationCount.value = 0
  }

  /**
   * After focusing a conversation, refresh chat unread from conversations.
   * Work badge is independent (chat_message excluded).
   */
  function markChatNotificationsReadLocally(_conversationId: number): void {
    void _conversationId
    void refreshUnreadCounts()
  }

  function stopListeners(): void {
    if (unsubscribeNotification) {
      unsubscribeNotification()
      unsubscribeNotification = null
    }
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  async function bootstrap(): Promise<void> {
    const userStore = useUserStore()
    if (!userStore.isLogin || !userStore.accessToken) {
      return
    }

    const userId = Number((userStore.info as any).userId || (userStore.info as any).id || 0)
    if (!userId) {
      return
    }
    const roles = userStore.info?.roles
    const canChat =
      Array.isArray(roles) &&
      roles.some((role) => ['Customer', 'Teller', 'Administrator'].includes(String(role)))

    if (bootstrapped) {
      await refreshUnreadCounts()
      return
    }

    const apiConfig = await fetchRealtimeConfig()
    // Prefer Vite public host/port for browser → Docker-mapped Reverb
    const echo = initEcho({
      ...apiConfig,
      app_key: import.meta.env.VITE_REVERB_APP_KEY || apiConfig.app_key || null,
      host: import.meta.env.VITE_REVERB_HOST || apiConfig.host || 'localhost',
      port: Number(import.meta.env.VITE_REVERB_PORT || apiConfig.port || 8080),
      scheme: import.meta.env.VITE_REVERB_SCHEME || apiConfig.scheme || 'http',
      is_enabled:
        apiConfig.is_enabled ?? Boolean(import.meta.env.VITE_REVERB_APP_KEY || apiConfig.app_key)
    })

    wsConnected.value = Boolean(echo)
    bootstrapped = true

    await refreshUnreadCounts()
    await refreshWorkAlerts()
    if (canChat) await refreshChatPreviews()

    if (echo) {
      unsubscribeNotification = onUserNotification(userId, (notification) => {
        const item = notification as InAppNotification | undefined
        if (item?.type && isChatNotification(item)) {
          // Chat wake-up: only bump chat badge; Work stream stays transactional.
          unreadChatCount.value++
          panelOpenTick.value++
          showChatPreview(item)
          latestChatNotificationId = Math.max(latestChatNotificationId ?? 0, Number(item.id) || 0)
          return
        }

        unreadNotificationCount.value++
        if (item?.id) {
          if (!isChatNotification(item)) {
            showWorkAlert(item)
            latestWorkNotificationId = Math.max(latestWorkNotificationId ?? 0, Number(item.id) || 0)
          }
          recentNotifications.value = [
            item,
            ...recentNotifications.value.filter((n) => n.id !== item.id)
          ].slice(0, 20)
        } else {
          refreshRecentNotifications()
        }
        panelOpenTick.value++
      })
    }

    // Poll the durable chat stream even when Echo exists: a socket may be
    // disconnected while its client object is still present.
    pollTimer = setInterval(() => {
      void refreshWorkAlerts()
      if (canChat) {
        void refreshChatPreviews().then((hasNewChat) => {
          if (hasNewChat) void refreshUnreadCounts()
        })
      }
      if (!echo) void refreshUnreadCounts()
    }, 5000)
  }

  function teardown(): void {
    stopListeners()
    disconnectEcho()
    chatPreview?.close()
    chatPreview = null
    closeBrowserChatAlerts()
    previewedChatIds.clear()
    alertedWorkIds.clear()
    latestChatNotificationId = null
    latestWorkNotificationId = null
    workPollInFlight = false
    bootstrapped = false
    wsConnected.value = false
    unreadNotificationCount.value = 0
    unreadChatCount.value = 0
    recentNotifications.value = []
  }

  function bumpChatUnread(delta = 1): void {
    unreadChatCount.value = Math.max(0, unreadChatCount.value + delta)
  }

  return {
    unreadNotificationCount,
    unreadChatCount,
    recentNotifications,
    wsConnected,
    panelOpenTick,
    bootstrap,
    teardown,
    refreshUnreadCounts,
    refreshRecentNotifications,
    markOneRead,
    markAllRead,
    markChatNotificationsReadLocally,
    bumpChatUnread
  }
})
