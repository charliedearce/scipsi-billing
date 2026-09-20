<template>
  <div v-if="activeNotices.length > 0" class="announcement-container" role="region" aria-label="In-app Announcements">
    <div
      v-for="notice in activeNotices"
      :key="`${notice.id}-v${notice.version_number}`"
      :class="['announcement-banner', severityClass(notice.severity)]"
    >
      <div class="announcement-content">
        <div class="announcement-header">
          <span :class="['severity-badge', `badge-${notice.severity.toLowerCase()}`]">
            {{ notice.severity }}
          </span>
          <span v-if="notice.version_number > 1" class="version-badge">
            v{{ notice.version_number }} (Updated)
          </span>
          <h4 class="announcement-title">{{ notice.title }}</h4>
        </div>
        <p class="announcement-body">{{ notice.body }}</p>
        <div v-if="notice.change_reason" class="announcement-change-reason">
          <span class="change-label">Update reason:</span> {{ notice.change_reason }}
        </div>
      </div>

      <div class="announcement-actions">
        <el-button
          v-if="!notice.user_state.acknowledged"
          size="small"
          type="primary"
          plain
          @click="handleAcknowledge(notice)"
        >
          Acknowledge
        </el-button>
        <span v-else class="acknowledged-tag">
          ✓ Acknowledged
        </span>

        <el-button
          v-if="notice.is_dismissible"
          size="small"
          text
          class="dismiss-btn"
          @click="handleDismiss(notice)"
          title="Dismiss notification"
        >
          ✕
        </el-button>
        <span v-else class="non-dismissible-pill" title="Critical policy alert cannot be dismissed">
          Locked
        </span>
      </div>
    </div>
  </div>
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
  let refreshInterval: any = null

  function severityClass(severity: string): string {
    switch (severity) {
      case 'CRITICAL':
        return 'severity-critical'
      case 'MAINTENANCE':
        return 'severity-maintenance'
      case 'IMPORTANT':
        return 'severity-important'
      default:
        return 'severity-info'
    }
  }

  async function loadAnnouncements() {
    if (!userStore.isLogin) return

    try {
      const res = await fetchActiveAnnouncements()
      if (res && res.data) {
        activeNotices.value = res.data

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
    try {
      await dismissAnnouncement(notice.id)
      activeNotices.value = activeNotices.value.filter((n) => n.id !== notice.id)
    } catch {
      ElMessage.error('Failed to dismiss announcement')
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

<style scoped>
  .announcement-container {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px 16px 0;
    z-index: 99;
  }

  .announcement-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
  }

  .severity-info {
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
  }

  .severity-maintenance {
    background-color: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
  }

  .severity-important {
    background-color: #f5f3ff;
    border: 1px solid #ddd6fe;
    color: #5b21b6;
  }

  .severity-critical {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
  }

  .announcement-content {
    flex: 1;
    margin-right: 16px;
  }

  .announcement-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
  }

  .severity-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .badge-info {
    background-color: #3b82f6;
    color: #ffffff;
  }

  .badge-maintenance {
    background-color: #f59e0b;
    color: #ffffff;
  }

  .badge-important {
    background-color: #8b5cf6;
    color: #ffffff;
  }

  .badge-critical {
    background-color: #ef4444;
    color: #ffffff;
  }

  .version-badge {
    font-size: 11px;
    background-color: rgba(0, 0, 0, 0.08);
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
  }

  .announcement-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
  }

  .announcement-body {
    font-size: 13px;
    margin: 0;
    opacity: 0.9;
    line-height: 1.4;
  }

  .announcement-change-reason {
    font-size: 12px;
    font-style: italic;
    margin-top: 4px;
    opacity: 0.8;
  }

  .change-label {
    font-weight: 600;
  }

  .announcement-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
  }

  .acknowledged-tag {
    font-size: 12px;
    font-weight: 600;
    color: #10b981;
    padding: 2px 8px;
  }

  .dismiss-btn {
    font-size: 16px;
    font-weight: bold;
    padding: 4px 8px;
  }

  .non-dismissible-pill {
    font-size: 11px;
    background-color: rgba(239, 68, 68, 0.15);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.3);
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
  }
</style>
