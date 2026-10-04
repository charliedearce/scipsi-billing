<template>
  <ElButton
    size="small"
    :loading="requesting"
    :disabled="permission === 'unsupported'"
    @click="enableOrTest"
  >
    <ArtSvgIcon icon="ri:notification-3-line" class="mr-1 text-base" />
    {{ label }}
  </ElButton>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import {
    browserChatAlertPermission,
    requestBrowserChatAlerts,
    showBrowserChatAlertTest
  } from '@/utils/browserChatNotifications'

  const permission = ref(browserChatAlertPermission())
  const requesting = ref(false)
  const label = computed(() => {
    if (permission.value === 'granted') return 'Test browser alert'
    if (permission.value === 'denied') return 'Browser alerts blocked'
    if (permission.value === 'unsupported') return 'Browser alerts unavailable'
    return 'Enable browser alerts'
  })

  function refreshPermission(): void {
    permission.value = browserChatAlertPermission()
  }

  async function enableOrTest(): Promise<void> {
    if (permission.value === 'denied') {
      ElMessage.info('Allow notifications for this site in your browser settings, then try again.')
      return
    }

    requesting.value = true
    try {
      permission.value = await requestBrowserChatAlerts()
      if (permission.value === 'granted') {
        if (!showBrowserChatAlertTest()) {
          ElMessage.warning('The browser could not display a notification on this device.')
        }
      } else if (permission.value === 'denied') {
        ElMessage.info('Browser alerts were blocked. You can allow them in your browser settings.')
      } else {
        ElMessage.info('Allow notifications for this site in the browser prompt to enable alerts.')
      }
    } catch {
      ElMessage.warning('The browser could not request notification permission.')
    } finally {
      requesting.value = false
    }
  }

  onMounted(() => window.addEventListener('focus', refreshPermission))
  onUnmounted(() => window.removeEventListener('focus', refreshPermission))
</script>
