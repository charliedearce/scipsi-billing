<template>
  <div
    class="art-card relative flex h-28 flex-col justify-center px-5"
    :class="icon ? 'pr-20' : ''"
  >
    <span class="text-sm text-g-700">{{ label }}</span>
    <span class="mt-1.5 text-xl font-medium" :class="warning ? 'text-danger' : 'text-g-900'">{{
      value
    }}</span>
    <span v-if="detail" class="mt-1 text-xs text-g-600">{{ detail }}</span>
    <div
      v-if="icon"
      class="absolute bottom-0 right-5 top-0 m-auto size-11 flex-cc rounded-xl"
      :class="warning ? 'bg-danger/10 text-danger' : 'bg-g-200/70 text-g-700'"
    >
      <ArtSvgIcon :icon="icon" class="text-xl" />
    </div>
  </div>
</template>
<script setup lang="ts">
  defineProps<{ label: string; value: string; detail?: string; icon?: string; warning?: boolean }>()
</script>
