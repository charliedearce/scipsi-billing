import { onBeforeUnmount } from 'vue'
import {
  onDataRefresh,
  onUserDataRefresh,
  type DataRefreshPayload
} from '@/utils/echo'

type RefreshOptions = {
  scope: string
  refresh: (payload?: DataRefreshPayload) => void | Promise<void>
  isBusy?: () => boolean
  recoveryIntervalMs?: number
}

/**
 * Treat Reverb as a wake-up hint only. Every signal refetches the authoritative
 * API state, while a quiet polling interval recovers from websocket outages.
 */
export function useAuthoritativeRealtimeRefresh(options: RefreshOptions) {
  let stopRealtime: (() => void) | null = null
  let recoveryTimer: ReturnType<typeof setInterval> | null = null
  let refreshing = false

  async function run(payload?: DataRefreshPayload) {
    if (refreshing || options.isBusy?.()) return

    refreshing = true
    try {
      await options.refresh(payload)
    } finally {
      refreshing = false
    }
  }

  function startRecoveryTimer() {
    if (recoveryTimer) return
    recoveryTimer = setInterval(() => {
      if (document.visibilityState === 'visible') void run()
    }, options.recoveryIntervalMs ?? 30_000)
  }

  function startForOrganization(organizationId: number) {
    if (stopRealtime || !organizationId) return
    stopRealtime = onDataRefresh(organizationId, options.scope, run)
    startRecoveryTimer()
  }

  function startForUser(userId: number) {
    if (stopRealtime || !userId) return
    stopRealtime = onUserDataRefresh(userId, options.scope, run)
    startRecoveryTimer()
  }

  function stop() {
    stopRealtime?.()
    stopRealtime = null
    if (recoveryTimer) clearInterval(recoveryTimer)
    recoveryTimer = null
  }

  onBeforeUnmount(stop)

  return { startForOrganization, startForUser, stop }
}
