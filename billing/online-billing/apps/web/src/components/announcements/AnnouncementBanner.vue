<template>
  <section
    v-if="activeNotices.length > 0"
    class="flex flex-col gap-2 px-4 pt-3"
    role="region"
    aria-label="In-app announcements"
  >
    <div
      v-for="notice in activeNotices"
      :key="`${notice.id}-v${notice.version_number}`"
      class="flex flex-col gap-3 rounded-xl border border-l-4 border-g-200 bg-box px-4 py-3 sm:flex-row sm:items-center"
      :class="severityClass(notice.severity)"
    >
      <div class="flex min-w-0 flex-1 items-start gap-3">
        <span
          class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-g-100 text-g-700"
        >
          <ArtSvgIcon icon="ri:megaphone-line" class="text-lg" />
        </span>
        <div class="min-w-0">
          <div class="mb-1 flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-g-600">
              {{ notice.severity }}
            </span>
            <span v-if="notice.version_number > 1" class="text-xs text-g-500">
              Updated v{{ notice.version_number }}
            </span>
          </div>
          <h4 class="m-0 text-sm font-semibold text-g-900">{{ notice.title }}</h4>
          <p class="m-0 mt-1 whitespace-pre-line break-words text-sm leading-5 text-g-700">
            {{ notice.body }}
          </p>
          <p v-if="notice.change_reason" class="m-0 mt-1 text-xs text-g-500">
            Update reason: {{ notice.change_reason }}
          </p>
        </div>
      </div>

      <div class="flex shrink-0 items-center self-end sm:self-center">
        <ElButton
          v-if="notice.is_dismissible"
          size="small"
          type="primary"
          plain
          :loading="closingId === notice.id"
          title="Acknowledge and close announcement"
          @click="handleDismiss(notice)"
        >
          Got it
        </ElButton>
        <ElButton
          v-else-if="!notice.user_state.acknowledged"
          size="small"
          type="primary"
          plain
          @click="handleAcknowledge(notice)"
        >
          Acknowledge
        </ElButton>
        <span v-else class="text-xs font-medium text-g-600"> Acknowledged · remains visible </span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted } from 'vue'
  import { useUserStore } from '@/store/modules/user'
  import {
    ActiveAnnouncement,
    fetchActiveAnnouncements,
    markAnnouncementSeen,
    acknowledgeAnnouncement,
    dismissAnnouncement
  } from '@/api/announcements'
  import { ElMessage } from 'element-plus'

  defineOptions({ name: 'AnnouncementBanner' })

  const userStore = useUserStore()
  const activeNotices = ref<ActiveAnnouncement[]>([])
  const closingId = ref<number | null>(null)
  let refreshInterval: any = null

  function severityClass(severity: string): string {
    switch (severity) {
      case 'CRITICAL':
        return 'border-l-error'
      case 'MAINTENANCE':
        return 'border-l-warning'
      case 'IMPORTANT':
        return 'border-l-theme'
      default:
        return 'border-l-info'
    }
  }

  async function loadAnnouncements() {
    if (!userStore.isLogin) return

    try {
      const res = await fetchActiveAnnouncements()
      if (res) {
        activeNotices.value = res

        // Automatically mark unseen notices as seen
        activeNotices.value.forEach((notice) => {
          if (!notice.user_state.seen) {
            markAnnouncementSeen(notice.id).catch(() => {})
          }
        })
      }
    } catch {
      // Non-blocking for normal page operations
    }
  }

  async function handleAcknowledge(notice: ActiveAnnouncement) {
    try {
      await acknowledgeAnnouncement(notice.id)
      notice.user_state.acknowledged = true
      ElMessage.success('Announcement acknowledged')
    } catch {
      ElMessage.error('Failed to acknowledge announcement')
    }
  }

  async function handleDismiss(notice: ActiveAnnouncement) {
    if (closingId.value === notice.id) return
    closingId.value = notice.id
    try {
      await dismissAnnouncement(notice.id)
      activeNotices.value = activeNotices.value.filter((n) => n.id !== notice.id)
    } catch {
      ElMessage.error('Failed to acknowledge and close announcement')
    } finally {
      closingId.value = null
    }
  }

  function setupEchoListener() {
    // Listen for WebSocket Reverb updates if window.Echo exists
    const win = window as any
    const userInfo = userStore.getUserInfo as any
    const orgId = userInfo?.organization_id || 1

    if (win.Echo && orgId) {
      try {
        win.Echo.private(`org.${orgId}`)
          .listen('.announcement.changed', () => {
            loadAnnouncements()
          })
          .listen('.data.refresh', (e: any) => {
            if (e.scope === 'announcements') {
              loadAnnouncements()
            }
          })
      } catch {
        // Fallback to polling
      }
    }
  }

  onMounted(() => {
    loadAnnouncements()
    setupEchoListener()

    // Poll periodically every 60 seconds as fallback per W34
    refreshInterval = setInterval(() => {
      loadAnnouncements()
    }, 60000)
  })

  onUnmounted(() => {
    if (refreshInterval) {
      clearInterval(refreshInterval)
    }
  })
</script>
