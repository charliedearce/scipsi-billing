<template>
  <div class="p-4 sm:p-6">
    <ElCard shadow="never" class="!rounded-xl">
      <template #header
        ><div class="flex flex-wrap items-center justify-between gap-3"
          ><div
            ><h1 class="text-lg font-semibold">Accounting Periods</h1
            ><p class="mt-1 text-sm text-gray-500"
              >Periods are explicit financial master data. Close only after the reconciliation and
              retention process is complete.</p
            ></div
          ><div class="flex gap-2"
            ><ElButton :loading="loading" @click="load">Refresh</ElButton
            ><ElButton type="primary" @click="createVisible = true">Open period</ElButton></div
          ></div
        ></template
      >
      <ElAlert
        class="mb-4"
        type="warning"
        :closable="false"
        show-icon
        title="No automatic cutoff rule"
        description="The legacy day-25 rollover has not been copied as an implicit web rule. Create non-overlapping periods only after the approved operating-period mapping is available."
      />
      <ElTable
        v-loading="loading"
        :data="periods"
        stripe
        empty-text="No accounting periods are configured."
      >
        <ElTableColumn prop="period_code" label="Period" min-width="145" /><ElTableColumn
          prop="starts_on"
          label="Starts"
          min-width="125"
        /><ElTableColumn prop="ends_on" label="Ends" min-width="125" />
        <ElTableColumn label="Status" min-width="110"
          ><template #default="{ row }"
            ><ElTag size="small" :type="row.status === 'OPEN' ? 'success' : 'info'">{{
              row.status
            }}</ElTag></template
          ></ElTableColumn
        >
        <ElTableColumn prop="notes" label="Notes" min-width="210" show-overflow-tooltip />
        <ElTableColumn label="" width="115" fixed="right"
          ><template #default="{ row }"
            ><ElButton v-if="row.status === 'OPEN'" link type="danger" @click="close(row)"
              >Close period</ElButton
            ></template
          ></ElTableColumn
        >
      </ElTable>
    </ElCard>
    <ElDialog
      v-model="createVisible"
      title="Open accounting period"
      width="min(520px, 94vw)"
      @closed="reset"
      ><ElForm label-position="top"
        ><ElFormItem label="Period code" required
          ><ElInput v-model="form.period_code" placeholder="Example: 2026-10" /></ElFormItem
        ><ElFormItem label="Date range" required
          ><ElDatePicker
            v-model="range"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="to"
            start-placeholder="Start date"
            end-placeholder="End date"
            class="!w-full" /></ElFormItem
        ><ElFormItem label="Notes"
          ><ElInput
            v-model="form.notes"
            type="textarea"
            :rows="3"
            maxlength="1000"
            show-word-limit /></ElFormItem></ElForm
      ><template #footer
        ><ElButton @click="createVisible = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="create">Open period</ElButton></template
      ></ElDialog
    >
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    closeAccountingPeriod,
    createAccountingPeriod,
    fetchAccountingPeriods,
    type AccountingPeriod
  } from '@/api/accountingPeriods'
  defineOptions({ name: 'AccountingPeriods' })
  const loading = ref(false)
  const saving = ref(false)
  const createVisible = ref(false)
  const periods = ref<AccountingPeriod[]>([])
  const range = ref<string[]>([])
  const form = reactive({ period_code: '', notes: '' })
  const load = async () => {
    loading.value = true
    try {
      periods.value = await fetchAccountingPeriods()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load accounting periods')
    } finally {
      loading.value = false
    }
  }
  const reset = () => {
    Object.assign(form, { period_code: '', notes: '' })
    range.value = []
  }
  const create = async () => {
    if (!form.period_code.trim() || range.value.length !== 2)
      return ElMessage.warning('Enter a code and complete non-overlapping date range.')
    saving.value = true
    try {
      await createAccountingPeriod({
        period_code: form.period_code.trim(),
        starts_on: range.value[0],
        ends_on: range.value[1],
        notes: form.notes.trim() || undefined
      })
      ElMessage.success('Accounting period opened')
      createVisible.value = false
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not open the accounting period')
    } finally {
      saving.value = false
    }
  }
  const close = async (period: AccountingPeriod) => {
    try {
      const { value } = await ElMessageBox.prompt(
        'Record why this accounting period is ready to close.',
        `Close ${period.period_code}`,
        {
          inputType: 'textarea',
          inputValidator: (value) =>
            value?.trim().length >= 10 || 'Provide at least 10 characters of closure reason.'
        }
      )
      await closeAccountingPeriod(period.id, value.trim())
      ElMessage.success('Accounting period closed')
      await load()
    } catch {
      /* user cancelled or API reported an error */
    }
  }
  onMounted(load)
</script>
