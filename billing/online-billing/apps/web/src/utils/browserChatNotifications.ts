import type { InAppNotification } from '@/api/notifications'
import { mittBus } from '@/utils/sys'

const activeNotifications = new Set<Notification>()

export function browserChatAlertsAvailable(): boolean {
  return typeof window !== 'undefined' && window.isSecureContext && 'Notification' in window
}

export function browserChatAlertPermission(): NotificationPermission | 'unsupported' {
  return browserChatAlertsAvailable() ? Notification.permission : 'unsupported'
}

export async function requestBrowserChatAlerts(): Promise<NotificationPermission | 'unsupported'> {
  if (!browserChatAlertsAvailable()) return 'unsupported'
  if (Notification.permission !== 'default') return Notification.permission
  return Notification.requestPermission()
}

function createBrowserAlert(title: string, body: string, tag: string): Notification | null {
  if (browserChatAlertPermission() !== 'granted') return null

  try {
    const notification = new Notification(title, {
      body,
      icon: `${import.meta.env.BASE_URL}icons/icon-192x192.png`,
      tag,
      requireInteraction: false
    })
    activeNotifications.add(notification)
    notification.onclose = () => activeNotifications.delete(notification)
    window.setTimeout(() => notification.close(), 7000)
    return notification
  } catch {
    return null
  }
}

export function showBrowserChatAlert(item: InAppNotification, conversationId: number): boolean {
  const notification = createBrowserAlert(
    'SCIPSI Billing: new chat message',
    'Open Chat to read your message.',
    `scipsi-chat-${item.id}`
  )
  if (!notification) return false

  notification.onclick = () => {
    window.focus()
    mittBus.emit('openChat', { conversationId })
    notification.close()
  }
  return true
}

export function showBrowserChatAlertTest(): boolean {
  return Boolean(
    createBrowserAlert(
      'SCIPSI Billing browser alerts',
      'Browser alerts are ready for new chat and work notifications.',
      'scipsi-chat-test'
    )
  )
}

export function showBrowserWorkAlert(item: InAppNotification): boolean {
  const notification = createBrowserAlert(
    'SCIPSI Billing: new work notification',
    'Open Notifications to view the update.',
    `scipsi-work-${item.id}`
  )
  if (!notification) return false

  notification.onclick = () => {
    window.focus()
    window.location.hash = '#/notifications'
    notification.close()
  }
  return true
}

export function closeBrowserChatAlerts(): void {
  for (const notification of activeNotifications) notification.close()
  activeNotifications.clear()
}
