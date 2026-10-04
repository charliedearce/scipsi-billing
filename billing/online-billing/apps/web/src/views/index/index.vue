<!-- 布局容器 -->
<template>
  <div class="app-layout">
    <aside id="app-sidebar">
      <ArtSidebarMenu />
    </aside>

    <main id="app-main">
      <div id="app-header">
        <ArtHeaderBar />
      </div>
      <!-- In-App Announcements Banner (Decision W34 / P1-12) -->
      <AnnouncementBanner />
      <div id="app-content">
        <ArtPageContent />
      </div>
    </main>

    <div id="app-global">
      <ArtGlobalComponent />
    </div>
  </div>
</template>

<script setup lang="ts">
  import AnnouncementBanner from '@/components/announcements/AnnouncementBanner.vue'
  import { useRealtimeStore } from '@/store/modules/realtime'
  import { useUserStore } from '@/store/modules/user'

  defineOptions({ name: 'AppLayout' })

  const userStore = useUserStore()
  const realtimeStore = useRealtimeStore()

  onMounted(() => {
    if (userStore.isLogin) {
      realtimeStore.bootstrap().catch(() => {
        // Non-fatal: polling fallback inside store when Echo is unavailable
      })
    }
  })
</script>

<style lang="scss" scoped>
  @use './style';
</style>
