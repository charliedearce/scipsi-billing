<!-- Current page purpose, read from route meta.description -->
<template>
  <ElPopover
    v-if="description"
    trigger="click"
    placement="bottom-start"
    :width="320"
    popper-class="art-page-info-popper"
  >
    <template #reference>
      <button
        type="button"
        class="ml-2 flex-c h-7 shrink-0 gap-1 leading-none rounded-full border border-g-300 px-2.5 text-xs text-g-600 tad-200 hover:bg-active-color hover:text-theme"
        :aria-label="`About ${title}`"
      >
        <ArtSvgIcon icon="ri:information-line" class="text-sm" />
        <span class="max-md:!hidden">About this page</span>
      </button>
    </template>
    <div class="flex items-start gap-3">
      <div class="size-8 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
        <ArtSvgIcon :icon="icon || 'ri:information-line'" class="text-base" />
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium text-g-900">{{ title }}</p>
        <p class="mt-1 text-xs leading-5 text-g-600">{{ description }}</p>
      </div>
    </div>
  </ElPopover>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { formatMenuTitle } from '@/utils/router'

  defineOptions({ name: 'ArtPageInfo' })

  const route = useRoute()
  const current = computed(() => route.matched[route.matched.length - 1]?.meta)
  const description = computed(() => current.value?.description as string | undefined)
  const title = computed(() => formatMenuTitle((current.value?.title as string) || ''))
  const icon = computed(() => current.value?.icon as string | undefined)
</script>
