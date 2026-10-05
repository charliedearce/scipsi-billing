<template>
  <div class="page-content mx-auto max-w-6xl !p-0 overflow-hidden">
    <header
      class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
    >
      <div class="flex items-center gap-3.5">
        <div class="size-11 flex-cc rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:megaphone-line" class="text-2xl" />
        </div>
        <div>
          <h1 class="text-xl font-medium text-g-900">Bulletin Board</h1>
          <p class="mt-1 text-sm text-g-500">Announcements shared with you</p>
        </div>
      </div>
      <ElButton :loading="loading" @click="loadNotices">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />Refresh
      </ElButton>
    </header>

    <div class="border-y border-g-200 bg-g-100/40 px-5 py-3.5 sm:px-6">
      <ElRadioGroup v-model="selectedTab" aria-label="Announcement period">
        <ElRadioButton value="current">Current ({{ currentNotices.length }})</ElRadioButton>
        <ElRadioButton value="past">Past ({{ pastNotices.length }})</ElRadioButton>
      </ElRadioGroup>
    </div>

    <section class="space-y-3 p-5 sm:p-6" aria-live="polite" :aria-busy="loading">
      <div v-if="error" class="rounded-lg border border-error/30 bg-error/5 p-5 text-sm text-error">
        {{ error }}
      </div>
      <ElEmpty
        v-else-if="!loading && visibleNotices.length === 0"
        :description="
          selectedTab === 'current' ? 'No current announcements' : 'No past announcements'
        "
      />
      <article
        v-for="notice in visibleNotices"
        :key="notice.version_id"
        class="rounded-lg border border-g-200 bg-g-100/40 p-4 sm:p-5"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <ElTag :type="severityType(notice.severity)" effect="plain" size="small">
              {{ severityLabel(notice.severity) }}
            </ElTag>
            <h2 class="text-base font-medium text-g-900">{{ notice.title }}</h2>
          </div>
          <span class="text-xs text-g-500">
            {{
              notice.user_state.acknowledged
                ? 'Acknowledged'
                : notice.user_state.seen
                  ? 'Seen'
                  : 'New'
            }}
          </span>
        </div>
        <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-g-700">{{
          notice.body
        }}</p>
        <div
          class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-g-200 pt-3 text-xs text-g-500"
        >
          <span>From {{ formatDateTimeManila(notice.effective_start_at) }}</span>
          <span v-if="notice.effective_end_at"
            >Until {{ formatDateTimeManila(notice.effective_end_at) }}</span
          >
        </div>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import {
    fetchBulletinBoard,
    type ActiveAnnouncement,
    type AnnouncementSeverity
  } from '@/api/announcements'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'

  defineOptions({ name: 'BulletinBoard' })

  const notices = ref<ActiveAnnouncement[]>([])
  const selectedTab = ref<'current' | 'past'>('current')
  const loading = ref(false)
  const error = ref('')
  const currentNotices = computed(() => notices.value.filter((notice) => notice.is_current))
  const pastNotices = computed(() => notices.value.filter((notice) => !notice.is_current))
  const visibleNotices = computed(() =>
    selectedTab.value === 'current' ? currentNotices.value : pastNotices.value
  )

  function severityLabel(severity: AnnouncementSeverity): string {
    return severity === 'INFO' ? 'Info' : severity.charAt(0) + severity.slice(1).toLowerCase()
  }

  function severityType(severity: AnnouncementSeverity): 'info' | 'warning' | 'danger' {
    if (severity === 'CRITICAL') return 'danger'
    if (severity === 'IMPORTANT') return 'warning'
    return 'info'
  }

  async function loadNotices() {
    loading.value = true
    error.value = ''
    try {
      notices.value = await fetchBulletinBoard()
    } catch {
      error.value = 'Announcements could not be loaded. Please try again.'
    } finally {
      loading.value = false
    }
  }

  onMounted(loadNotices)
</script>
