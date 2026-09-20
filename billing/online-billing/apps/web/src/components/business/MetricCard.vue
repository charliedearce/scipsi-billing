<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5"
  >
    <div class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ label }}</div>
    <div
      class="mt-2 text-xl font-bold"
      :class="warning ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'"
      >{{ value }}</div
    >
    <div v-if="detail" class="mt-1 text-xs text-slate-500">{{ detail }}</div>
  </div>
</template>
<script setup lang="ts">
  defineProps<{ label: string; value: string; detail?: string; icon?: string; warning?: boolean }>()
</script>
