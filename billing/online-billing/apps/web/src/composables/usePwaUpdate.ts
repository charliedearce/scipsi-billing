/**
 * usePwaUpdate — PWA update and offline-ready composable (P1-11 / W33).
 *
 * Wraps vite-plugin-pwa's virtual registration module to expose
 * reactive update and offline-ready state for the UI layer.
 *
 * ## Behavior
 * - `needRefresh`: true when a waiting service worker update is available.
 * - `offlineReady`: true when the shell assets are fully precached.
 * - `updateServiceWorker()`: accepts the waiting SW, reloads the page.
 *   Only called by user intent (e.g., clicking "Reload now" in the banner).
 *
 * ## W33 constraints
 * - `registerType: 'prompt'` — the SW never silently skipWaiting().
 * - The composable does not trigger install prompts automatically.
 * - No push-subscription, VAPID or notification permission logic here.
 */
import { useRegisterSW } from 'virtual:pwa-register/vue'

export interface PwaUpdateState {
  /** True when a new service worker is waiting to activate */
  needRefresh: Ref<boolean>
  /** True when the app shell is fully cached for offline use */
  offlineReady: Ref<boolean>
  /** Accept the waiting SW and reload the page */
  updateServiceWorker: (reloadPage?: boolean) => Promise<void>
}

export function usePwaUpdate(): PwaUpdateState {
  const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW({
    // Checks for a new SW on each page focus (rate-limited by the browser to ~24h for same URL).
    onRegisteredSW(swUrl, registration) {
      if (registration) {
        // Check for updates on every app focus, capped by browser.
        document.addEventListener('visibilitychange', () => {
          if (document.visibilityState === 'visible') {
            registration.update().catch(() => {
              // Update check failure is non-fatal
            })
          }
        })
      }
    },
    onRegisterError(error) {
      // SW registration failed — app continues as normal browser page.
      // Failure is expected in non-HTTPS environments (local dev).
      if (import.meta.env.DEV) {
        console.warn('[PWA] Service worker registration skipped in dev mode:', error)
      }
    }
  })

  return { needRefresh, offlineReady, updateServiceWorker }
}
