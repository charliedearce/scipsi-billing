<template>
  <div class="notifications-page p-4">
    <ElCard shadow="never" class="mb-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-800">In-App Notifications</h2>
            <ElBadge v-if="unreadCount > 0" :value="unreadCount" :max="99" type="danger" />
          </div>
          <p class="text-sm text-gray-500">
            Realtime notifications for queue events, billing status, and payment updates.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <ElTag :type="wsConnected ? 'success' : 'info'" size="small" effect="plain">
            <span class="inline-block w-2 h-2 rounded-full mr-1" :class="wsConnected ? 'bg-emerald-500' : 'bg-gray-400'"></span>
            {{ wsConnected ? 'Realtime Connected' : 'Polling Sync' }}
          </ElTag>
          <ElButton
            type="primary"
            plain
            :disabled="unreadCount === 0 || loading"
            @click="handleMarkAllRead"
          >
            Mark All as Read
          </ElButton>
          <ElButton @click="loadNotifications">Refresh</ElButton>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <ElRadioGroup v-model="filterUnread" @change="handleTabChange">
          <ElRadioButton :value="false">All</ElRadioButton>
          <ElRadioButton :value="true">Unread Only</ElRadioButton>
        </ElRadioGroup>

        <ElSelect
          v-model="filterType"
          placeholder="Filter by type"
          clearable
          class="!w-48"
          @change="loadNotifications"
        >
          <ElOption label="All Types" value="" />
          <ElOption label="Queue Update" value="queue_update" />
          <ElOption label="Bill Ready" value="bill_ready" />
          <ElOption label="Payment Reminder" value="payment_reminder" />
          <ElOption label="Proof Review" value="proof_review" />
          <ElOption label="System Notice" value="system" />
        </ElSelect>
      </div>
    </ElCard>

    <ElCard shadow="never" v-loading="loading">
      <div v-if="notificationList.length === 0" class="py-12 text-center text-gray-400">
        <ElEmpty description="No notifications found" />
      </div>

      <div v-else class="divide-y divide-gray-100">
        <div
          v-for="item in notificationList"
          :key="item.id"
          class="py-3 px-2 flex items-start justify-between gap-4 transition hover:bg-gray-50 rounded"
          :class="{ 'bg-blue-50/40': !item.is_read }"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-2.5 h-2.5 rounded-full mt-2 shrink-0"
              :class="item.is_read ? 'bg-transparent' : 'bg-blue-600'"
            ></div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-sm text-gray-800">{{ item.title }}</span>
                <ElTag size="small" :type="getTypeTag(item.type)">{{ formatType(item.type) }}</ElTag>
              </div>
              <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ item.body }}</p>
              <div v-if="item.data && Object.keys(item.data).length > 0" class="mt-2 text-xs bg-gray-100 p-2 rounded text-gray-700 font-mono">
                <pre class="whitespace-pre-wrap">{{ JSON.stringify(item.data, null, 2) }}</pre>
              </div>
              <div class="text-xs text-gray-400 mt-1.5">
                {{ formatTimestamp(item.created_at) }}
                <span v-if="item.is_read && item.read_at" class="ml-2 text-gray-400">
                  (Read: {{ formatTimestamp(item.read_at) }})
                </span>
              </div>
            </div>
          </div>
          <div class="shrink-0 pt-1">
            <ElButton
              v-if="!item.is_read"
              size="small"
              type="primary"
              text
              @click="handleMarkRead(item)"
            >
              Mark read
            </ElButton>
          </div>
        </div>
      </div>

      <div class="mt-4 flex justify-end">
        <ElPagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @current-change="loadNotifications"
          @size-change="loadNotifications"
        />
      </div>
    </ElCard>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  fetchNotifications,
  markNotificationAsRead,
  markAllNotificationsAsRead,
  type InAppNotification
} from '@/api/notifications'
import { initEcho, onUserNotification } from '@/utils/echo'
import { useUserStore } from '@/store/modules/user'

const userStore = useUserStore()
const notificationList = ref<InAppNotification[]>([])
const unreadCount = ref(0)
const loading = ref(false)
const filterUnread = ref(false)
const filterType = ref('')
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const wsConnected = ref(false)

let unsubscribeNotif: (() => void) | null = null
let pollTimer: ReturnType<typeof setInterval> | null = null

async function loadNotifications() {
  loading.value = true
  try {
    const res = await fetchNotifications({
      page: currentPage.value,
      per_page: pageSize.value,
      unread_only: filterUnread.value,
      type: filterType.value || undefined
    })
    notificationList.value = res.data || []
    unreadCount.value = res.unread_count ?? 0
    total.value = res.meta?.total ?? 0
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to load notifications.')
  } finally {
    loading.value = false
  }
}

function handleTabChange() {
  currentPage.value = 1
  loadNotifications()
}

async function handleMarkRead(item: InAppNotification) {
  try {
    await markNotificationAsRead(item.id)
    item.is_read = true
    item.read_at = new Date().toISOString()
    if (unreadCount.value > 0) {
      unreadCount.value--
    }
    ElMessage.success('Marked as read')
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to mark notification as read.')
  }
}

async function handleMarkAllRead() {
  try {
    const res = await markAllNotificationsAsRead()
    ElMessage.success(res.message || 'All notifications marked as read.')
    loadNotifications()
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to mark all as read.')
  }
}

function getTypeTag(type: string): 'primary' | 'success' | 'warning' | 'danger' | 'info' {
  switch (type) {
    case 'bill_ready':
      return 'success'
    case 'queue_update':
      return 'warning'
    case 'payment_reminder':
      return 'danger'
    default:
      return 'info'
  }
}

function formatType(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatTimestamp(isoStr?: string | null): string {
  if (!isoStr) return '-'
  try {
    const d = new Date(isoStr)
    return d.toLocaleString()
  } catch {
    return isoStr
  }
}

onMounted(() => {
  loadNotifications()

  // Initialize Echo listener if user is logged in
  const echo = initEcho()
  if (echo) {
    wsConnected.value = true
    const userId = (userStore.info as any)?.id
    if (userId) {
      unsubscribeNotif = onUserNotification(userId, () => {
        unreadCount.value++
        loadNotifications()
      })
    }
  } else {
    // Polling fallback when WebSockets are unavailable
    wsConnected.value = false
    pollTimer = setInterval(() => {
      loadNotifications()
    }, 15000)
  }
})

onUnmounted(() => {
  if (unsubscribeNotif) {
    unsubscribeNotif()
  }
  if (pollTimer) {
    clearInterval(pollTimer)
  }
})
</script>

<style scoped>
.notifications-page {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
