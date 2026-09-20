<template>
  <ElConfigProvider
    size="default"
    :locale="en"
    :z-index="3000"
    :card="{
      shadow: 'never'
    }"
  >
    <!-- PWA: global offline indicator (W33) — shown when navigator.onLine is false -->
    <OfflineIndicator />
    <RouterView></RouterView>
    <!-- PWA: update available banner (W33) — user-triggered reload only -->
    <PwaUpdateBanner />
  </ElConfigProvider>
</template>

<script setup lang="ts">
  import en from 'element-plus/es/locale/lang/en'
  import { systemUpgrade } from './utils/sys'
  import { toggleTransition } from './utils/ui/animation'
  import { checkStorageCompatibility } from './utils/storage'
  import { initializeTheme } from './hooks/core/useTheme'
  // PWA components (P1-11 / W33)
  import PwaUpdateBanner from './components/pwa/PwaUpdateBanner.vue'
  import OfflineIndicator from './components/pwa/OfflineIndicator.vue'

  onBeforeMount(() => {
    toggleTransition(true)
    initializeTheme()
  })

  onMounted(() => {
    checkStorageCompatibility()
    toggleTransition(false)
    systemUpgrade()
  })
</script>
