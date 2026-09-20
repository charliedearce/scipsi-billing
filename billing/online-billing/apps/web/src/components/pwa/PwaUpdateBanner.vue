<template>
  <!-- Update banner: shown only when a new service worker is waiting and not dismissed -->
  <Transition name="pwa-banner">
    <div
      v-if="showBanner"
      class="pwa-update-banner"
      role="status"
      aria-live="polite"
      aria-label="Application update available"
    >
      <div class="pwa-update-banner__content">
        <span class="pwa-update-banner__icon" aria-hidden="true">
          <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.24a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z"
              clip-rule="evenodd"
            />
          </svg>
        </span>
        <span class="pwa-update-banner__text">A new version is available.</span>
        <button
          id="pwa-update-reload-btn"
          class="pwa-update-banner__action"
          @click="handleUpdate"
        >
          Reload now
        </button>
        <button
          id="pwa-update-dismiss-btn"
          class="pwa-update-banner__dismiss"
          aria-label="Dismiss update notice"
          @click="dismissed = true"
        >
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
            <path
              d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
            />
          </svg>
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
  import { usePwaUpdate } from '@/composables/usePwaUpdate'

  const { needRefresh, updateServiceWorker } = usePwaUpdate()
  const dismissed = ref(false)

  // Override needRefresh to respect user dismissal
  const showBanner = computed(() => needRefresh.value && !dismissed.value)

  // Re-expose as needRefresh for the template (use showBanner in v-if)
  // Template uses needRefresh directly to show the outer component;
  // dismissed is handled by not calling update and hiding via v-if override.
  // We override via watch to keep template simple.
  watch(dismissed, (val) => {
    if (val) {
      // Reset dismissed state if a new update arrives later
      const unwatch = watch(needRefresh, (fresh) => {
        if (fresh) {
          dismissed.value = false
          unwatch()
        }
      })
    }
  })

  async function handleUpdate() {
    await updateServiceWorker(true)
  }
</script>

<script lang="ts">
  export default { name: 'PwaUpdateBanner' }
</script>

<style scoped>
  .pwa-update-banner {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    width: max-content;
    max-width: calc(100vw - 32px);
  }

  .pwa-update-banner__content {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: #1a2744;
    color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.22);
    font-size: 14px;
    font-family: inherit;
  }

  .pwa-update-banner__icon {
    display: flex;
    align-items: center;
    color: #3b82f6;
    flex-shrink: 0;
  }

  .pwa-update-banner__text {
    flex: 1;
    white-space: nowrap;
  }

  .pwa-update-banner__action {
    padding: 5px 14px;
    background: #1a56db;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
  }

  .pwa-update-banner__action:hover {
    background: #1648c0;
  }

  .pwa-update-banner__dismiss {
    display: flex;
    align-items: center;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    padding: 2px;
    transition: color 0.15s;
    flex-shrink: 0;
  }

  .pwa-update-banner__dismiss:hover {
    color: #fff;
  }

  /* Transition */
  .pwa-banner-enter-active,
  .pwa-banner-leave-active {
    transition:
      opacity 0.25s ease,
      transform 0.25s ease;
  }

  .pwa-banner-enter-from,
  .pwa-banner-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(12px);
  }
</style>
