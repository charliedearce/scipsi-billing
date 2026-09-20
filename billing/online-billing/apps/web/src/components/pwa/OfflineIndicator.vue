<template>
  <!-- Offline indicator: rendered globally, visible only when navigator.onLine is false -->
  <Transition name="offline-bar">
    <div
      v-if="!isOnline"
      id="pwa-offline-indicator"
      class="offline-indicator"
      role="status"
      aria-live="assertive"
      aria-label="Application is offline"
    >
      <span class="offline-indicator__icon" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
          <path
            fill-rule="evenodd"
            d="M.22 2.22a.75.75 0 011.06 0L4.47 5.41A10.45 10.45 0 0110 4c2.394 0 4.598.8 6.36 2.134l1.312-1.312a.75.75 0 111.06 1.06l-1.523 1.523A10.444 10.444 0 0119.5 10c0 1.99-.558 3.847-1.528 5.424l.809.808a.75.75 0 11-1.06 1.06l-.916-.915A10.455 10.455 0 0110 19.5a10.455 10.455 0 01-6.805-2.123l-.916.915a.75.75 0 11-1.06-1.06l.809-.808A10.444 10.444 0 01.5 10c0-1.99.558-3.847 1.528-5.424L.22 3.28a.75.75 0 010-1.06zm2.824 4.945A8.946 8.946 0 001.5 10c0 1.78.52 3.44 1.418 4.836L13.836 3.918A8.952 8.952 0 0010 2.5c-1.78 0-3.44.52-4.836 1.418L4.75 4.33l-.706-.707.706.707-1.706 1.83zm11.912 8.666A8.946 8.946 0 0018.5 10a8.946 8.946 0 00-1.418-4.836L6.164 16.082A8.952 8.952 0 0010 17.5c1.78 0 3.44-.52 4.836-1.418l.12-.249z"
            clip-rule="evenodd"
          />
        </svg>
      </span>
      <span class="offline-indicator__text">
        Offline — reconnect to refresh protected data
      </span>
    </div>
  </Transition>
</template>

<script setup lang="ts">
  /**
   * OfflineIndicator — global connectivity status strip (P1-11 / W33).
   *
   * Listens to browser online/offline events and shows a clearly visible
   * strip when the device loses connectivity. Server-authoritative actions
   * (billing, authentication, payment) remain in the app and blocked by
   * the API layer; this indicator is informational only.
   *
   * W33 constraint: when offline, the app must visibly communicate
   * "Offline — reconnect to refresh protected data" and must NOT present
   * cached bills, balances, payment status, or API results as current.
   */
  import { useOnline } from '@vueuse/core'

  const isOnline = useOnline()
</script>

<script lang="ts">
  export default { name: 'OfflineIndicator' }
</script>

<style scoped>
  .offline-indicator {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    background: #92400e;
    color: #fef3c7;
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  }

  .offline-indicator__icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    opacity: 0.9;
  }

  .offline-indicator__text {
    letter-spacing: 0.01em;
  }

  /* Transition */
  .offline-bar-enter-active,
  .offline-bar-leave-active {
    transition:
      opacity 0.2s ease,
      transform 0.2s ease;
  }

  .offline-bar-enter-from,
  .offline-bar-leave-to {
    opacity: 0;
    transform: translateY(-100%);
  }
</style>
