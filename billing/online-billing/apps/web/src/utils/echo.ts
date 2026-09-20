import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo?: Echo<'reverb'>
  }
}

window.Pusher = Pusher

export interface RealtimeConfig {
  driver: string
  app_key: string | null
  host: string
  port: number
  scheme: string
  auth_endpoint: string
  is_enabled: boolean
}

let echoInstance: Echo<'reverb'> | null = null
let debounceTimer: ReturnType<typeof setTimeout> | null = null

/**
 * Initialize or retrieve the global Laravel Echo instance.
 */
export function initEcho(config?: Partial<RealtimeConfig>): Echo<'reverb'> | null {
  if (echoInstance) {
    return echoInstance
  }

  const key = config?.app_key || import.meta.env.VITE_REVERB_APP_KEY || ''
  const host = config?.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname
  const port = config?.port || Number(import.meta.env.VITE_REVERB_PORT || 8080)
  const scheme = config?.scheme || import.meta.env.VITE_REVERB_SCHEME || 'http'
  const isEnabled = config?.is_enabled ?? (Boolean(key) && key.length > 0)

  if (!isEnabled || !key) {
    return null
  }

  const token = localStorage.getItem('token') || sessionStorage.getItem('token') || ''

  try {
    echoInstance = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/v1/broadcasting/auth',
      auth: {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json'
        }
      }
    })

    window.Echo = echoInstance
    return echoInstance
  } catch (error) {
    console.warn('[Echo] Realtime WebSockets unavailable, falling back to polling:', error)
    return null
  }
}

/**
 * Disconnect and destroy the Echo instance.
 */
export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
    delete window.Echo
  }
}

/**
 * Coalesced data refresh listener.
 * Debounces rapid bursts of data-refresh events into a single callback invocation.
 */
export function onDataRefresh(
  orgId: number,
  scope: string,
  callback: (payload: any) => void,
  debounceMs = 400
): () => void {
  const echo = initEcho()
  if (!echo) {
    return () => {}
  }

  const channel = echo.private(`org.${orgId}`)
  const handler = (event: any) => {
    if (!scope || event.scope === scope || event.scope === 'all') {
      if (debounceTimer) {
        clearTimeout(debounceTimer)
      }
      debounceTimer = setTimeout(() => {
        callback(event)
      }, debounceMs)
    }
  }

  channel.listen('.data.refresh', handler)

  return () => {
    channel.stopListening('.data.refresh', handler)
  }
}

/**
 * Listen for user private notifications.
 */
export function onUserNotification(
  userId: number,
  callback: (notification: any) => void
): () => void {
  const echo = initEcho()
  if (!echo) {
    return () => {}
  }

  const channel = echo.private(`user.${userId}`)
  const handler = (event: any) => {
    callback(event)
  }

  channel.listen('.notification.created', handler)

  return () => {
    channel.stopListening('.notification.created', handler)
  }
}

/**
 * Listen for chat messages on a specific conversation.
 */
export function onConversationMessage(
  conversationId: number,
  callback: (message: any) => void
): () => void {
  const echo = initEcho()
  if (!echo) {
    return () => {}
  }

  const channel = echo.private(`conversation.${conversationId}`)
  const handler = (event: any) => {
    callback(event)
  }

  channel.listen('.message.created', handler)

  return () => {
    channel.stopListening('.message.created', handler)
  }
}
