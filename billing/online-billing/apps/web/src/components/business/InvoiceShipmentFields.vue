<template>
  <div class="space-y-3">
    <p class="text-xs text-g-500">
      Vessel, voyage, type and route are required. Domestic vs foreign selects matching tariffs.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2 space-y-1.5">
        <label class="block text-sm text-g-700 font-medium">Vessel name</label>
        <ElSelect
          v-model="vesselId"
          class="w-full"
          filterable
          remote
          reserve-keyword
          clearable
          placeholder="Search vessel"
          :remote-method="searchVessels"
          :loading="searching"
          @change="onVesselPicked"
        >
          <ElOption v-for="opt in options" :key="opt.id" :label="opt.name" :value="opt.id" />
        </ElSelect>
      </div>
      <div class="sm:col-span-2 space-y-1.5">
        <label class="block text-sm text-g-700 font-medium">Voyage</label>
        <ElInput
          v-model="voyage"
          class="w-full"
          maxlength="10"
          inputmode="numeric"
          placeholder="Numbers only"
          @input="onVoyageInput"
        />
      </div>
      <div class="space-y-1.5">
        <label class="block text-sm text-g-700 font-medium">Type</label>
        <ElRadioGroup v-model="movementType" class="invoice-toggle">
          <ElRadioButton value="IN">IN</ElRadioButton>
          <ElRadioButton value="OUT">OUT</ElRadioButton>
        </ElRadioGroup>
      </div>
      <div class="space-y-1.5">
        <label class="block text-sm text-g-700 font-medium">Route</label>
        <ElRadioGroup v-model="routeType" class="invoice-toggle">
          <ElRadioButton value="DOMESTIC">Domestic</ElRadioButton>
          <ElRadioButton value="FOREIGN">Foreign</ElRadioButton>
        </ElRadioGroup>
      </div>
      <div class="sm:col-span-2 space-y-1.5">
        <label class="block text-sm text-g-700 font-medium">Notes</label>
        <ElInput
          v-model="notes"
          type="textarea"
          :rows="2"
          maxlength="150"
          show-word-limit
          placeholder="Alphanumeric notes"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, ref, watch } from 'vue'
  import { fetchVessels, type VesselOption } from '@/api/vessels'
  import type { MovementType, RouteType } from '@/api/invoices'

  const vesselId = defineModel<number | null>('vesselId', { required: true })
  const vesselName = defineModel<string>('vesselName', { required: true })
  const voyage = defineModel<string>('voyage', { required: true })
  const notes = defineModel<string>('notes', { required: true })
  const movementType = defineModel<MovementType | ''>('movementType', { required: true })
  const routeType = defineModel<RouteType | ''>('routeType', { required: true })

  const searching = ref(false)
  const options = ref<VesselOption[]>([])

  async function searchVessels(query: string) {
    searching.value = true
    try {
      const rows = await fetchVessels(query)
      const selected =
        vesselId.value && vesselName.value
          ? [{ id: vesselId.value, name: vesselName.value } satisfies VesselOption]
          : []
      const rest = rows.filter((row) => row.id !== vesselId.value)
      options.value = [...selected, ...rest]
    } catch {
      options.value =
        vesselId.value && vesselName.value ? [{ id: vesselId.value, name: vesselName.value }] : []
    } finally {
      searching.value = false
    }
  }

  function onVoyageInput(value: string) {
    voyage.value = value.replace(/\D/g, '').slice(0, 10)
  }

  function onVesselPicked(id: number | null) {
    const picked = options.value.find((row) => row.id === id) || null
    vesselName.value = picked?.name || ''
    const typical = picked?.typical_route
    if (!routeType.value && (typical === 'DOMESTIC' || typical === 'FOREIGN')) {
      routeType.value = typical
    }
  }

  watch(
    () => [vesselId.value, vesselName.value] as const,
    ([id, name]) => {
      if (id && name && !options.value.some((row) => row.id === id)) {
        options.value = [{ id, name }, ...options.value]
      }
    },
    { immediate: true }
  )

  onMounted(() => {
    void searchVessels('')
  })
</script>

<style scoped>
  .invoice-toggle {
    display: flex;
    width: 100%;
  }

  .invoice-toggle :deep(.el-radio-button) {
    flex: 1;
  }

  .invoice-toggle :deep(.el-radio-button__inner) {
    width: 100%;
  }
</style>
