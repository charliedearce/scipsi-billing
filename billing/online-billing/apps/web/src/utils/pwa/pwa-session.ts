/**
 * PWA session purge utilities (P1-11 / W33).
 *
 * These helpers are called on logout, permission revocation, or account switch
 * to clear any permitted non-financial recovery state from the browser.
 *
 * ## Contract boundaries (W33)
 * - Session tokens, auth material, OTPs, CSRF values, customer contacts,
 *   financial commands and API responses must NEVER be stored in
 *   Cache Storage, IndexedDB, or localStorage for PWA convenience.
 * - Only explicitly approved non-financial draft keys may be recovered;
 *   v1 has no approved recovery keys — the list is intentionally empty.
 * - purgePwaCaches() removes only session-bound runtime cache entries,
 *   NOT the versioned shell precache (Workbox manages that lifecycle).
 */

/**
 * Keys that are explicitly approved for non-financial local recovery.
 * This list must be reviewed and extended only through the W33 approval process.
 * V1: empty — no non-financial form state recovery is approved yet.
 */
const APPROVED_RECOVERY_KEYS: string[] = []

/**
 * Clear all explicitly permitted non-financial recovery state from storage.
 * Called on logout, session revocation, and account/organization switch.
 */
export function clearPwaRecoveryState(): void {
  for (const key of APPROVED_RECOVERY_KEYS) {
    try {
      localStorage.removeItem(key)
      sessionStorage.removeItem(key)
    } catch {
      // Storage unavailable or quota exceeded — safe to continue
    }
  }
}

/**
 * Purge user-session-scoped runtime cache entries from Cache Storage.
 * This removes any dynamic (non-precached) entries from named caches
 * while leaving the versioned Workbox shell precache untouched.
 *
 * Called on logout to prevent stale user-visible state persisting
 * across sessions in the installed PWA.
 */
export async function purgePwaCaches(): Promise<void> {
  if (!('caches' in window)) {
    return
  }

  try {
    const cacheNames = await caches.keys()
    // Only purge runtime caches (not the workbox precache versioned by build hash).
    // Workbox precache names follow the pattern: workbox-precache-v2-<origin>
    const runtimeCaches = cacheNames.filter(
      (name) => !name.startsWith('workbox-precache-v2')
    )

    await Promise.allSettled(runtimeCaches.map((name) => caches.delete(name)))
  } catch {
    // Cache API failure is non-fatal — session revocation via API is the authority
  }
}
