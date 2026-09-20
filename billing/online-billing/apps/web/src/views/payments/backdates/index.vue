<template>
  <div class="max-w-7xl mx-auto space-y-6 p-4 sm:p-6">
    <section
      class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <div class="flex items-center gap-2"
          ><h1 class="text-xl font-bold sm:text-2xl">Backdate Authorization</h1
          ><ElTag size="small" effect="dark" type="warning">One use</ElTag></div
        >
        <p class="mt-1 max-w-2xl text-sm text-slate-300"
          >A prior business date needs independent approval and is consumed only when the authorized
          invoice or collection receipt is issued.</p
        >
      </div>
      <div class="flex gap-2"
        ><ElButton
          :loading="loading"
          plain
          class="!border-white/20 !bg-white/10 !text-white"
          @click="load"
          ><ElIcon class="mr-1"><Refresh /></ElIcon>Refresh</ElButton
        ><ElButton type="primary" @click="requestVisible = true"
          ><ElIcon class="mr-1"><Plus /></ElIcon>Request backdate</ElButton
        ></div
      >
    </section>

    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Issuance safeguard"
      description="A request does not change the device date, invoice, receipt, number series, or accounting period. Posting rechecks the open period, requester, expiry, document type, and date inside the financial transaction."
    />

    <ElCard shadow="never" class="!rounded-xl">
      <div class="mb-4 flex items-center justify-between gap-3"
        ><ElSelect
          v-model="statusFilter"
          clearable
          placeholder="All statuses"
          class="!w-44"
          @change="load"
          ><ElOption
            v-for="status in statuses"
            :key="status"
            :label="status"
            :value="status" /></ElSelect
        ><span class="text-xs text-slate-500">{{ page.total }} request(s)</span></div
      >
      <ElTable
        v-loading="loading"
        :data="items"
        stripe
        empty-text="No backdate authorization requests match this view."
      >
        <ElTableColumn prop="id" label="#" width="78" /><ElTableColumn
          label="Document"
          min-width="125"
          ><template #default="{ row }"
            ><ElTag size="small" effect="plain">{{ row.document_type }}</ElTag></template
          ></ElTableColumn
        >
        <ElTableColumn prop="business_date" label="Business date" min-width="132" /><ElTableColumn
          label="Period"
          min-width="120"
          ><template #default="{ row }">{{
            row.period?.period_code || '—'
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Status" min-width="112"
          ><template #default="{ row }"
            ><ElTag size="small" :type="statusType(row.status)">{{ row.status }}</ElTag></template
          ></ElTableColumn
        >
        <ElTableColumn label="Requested by" min-width="155"
          ><template #default="{ row }">{{
            row.requested_by?.name || '—'
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Expires" min-width="170"
          ><template #default="{ row }">{{ formatDate(row.expires_at) }}</template></ElTableColumn
        >
        <ElTableColumn v-if="isAdministrator" label="" width="110" fixed="right"
          ><template #default="{ row }"
            ><ElButton
              v-if="row.status === 'PENDING' && row.requested_by?.id !== userStore.info?.userId"
              link
              type="primary"
              @click="openReview(row)"
              >Review</ElButton
            ></template
          ></ElTableColumn
        >
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="requestVisible"
      title="Request a backdate authorization"
      width="min(520px, 94vw)"
      @closed="resetRequest"
    >
      <ElForm label-position="top"
        ><ElFormItem label="Document type" required
          ><ElRadioGroup v-model="requestForm.document_type"
            ><ElRadioButton value="INVOICE">Invoice</ElRadioButton
            ><ElRadioButton value="RECEIPT">Collection receipt</ElRadioButton></ElRadioGroup
          ></ElFormItem
        ><ElFormItem label="Prior business date" required
          ><ElDatePicker
            v-model="requestForm.business_date"
            type="date"
            value-format="YYYY-MM-DD"
            class="!w-full"
            :disabled-date="disableTodayAndFuture" /></ElFormItem
        ><ElFormItem label="Reason" required
          ><ElInput
            v-model="requestForm.reason"
            type="textarea"
            :rows="4"
            maxlength="1000"
            show-word-limit
            placeholder="Explain why this prior business date is required." /></ElFormItem
      ></ElForm>
      <template #footer
        ><ElButton @click="requestVisible = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="submitRequest"
          >Submit request</ElButton
        ></template
      >
    </ElDialog>

    <ElDialog
      v-model="reviewVisible"
      title="Independent backdate review"
      width="min(560px, 94vw)"
      @closed="resetReview"
    >
      <p class="mb-4 text-sm text-slate-600"
        >Approve only after validating the source evidence. Approval does not issue a document; it
        permits this requester to use the prior date once before the selected expiry.</p
      >
      <ElForm label-position="top"
        ><ElFormItem label="Decision notes" required
          ><ElInput
            v-model="reviewForm.decision_notes"
            type="textarea"
            :rows="4"
            maxlength="1000"
            show-word-limit /></ElFormItem
        ><ElFormItem label="Authorization expires at" required
          ><ElDatePicker
            v-model="reviewForm.expires_at"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full" /></ElFormItem
      ></ElForm>
      <template #footer
        ><ElButton :loading="saving" @click="decide('reject')">Reject</ElButton
        ><ElButton type="primary" :loading="saving" @click="decide('approve')"
          >Approve one use</ElButton
        ></template
      >
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { Plus, Refresh } from '@element-plus/icons-vue'
  import { useUserStore } from '@/store/modules/user'
  import {
    approveBackdateAuthorization,
    fetchBackdateAuthorizations,
    rejectBackdateAuthorization,
    requestBackdateAuthorization,
    type BackdateAuthorization,
    type BackdateStatus
  } from '@/api/accountingPeriods'

  defineOptions({ name: 'BackdateAuthorizations' })
  const userStore = useUserStore()
  const loading = ref(false)
  const saving = ref(false)
  const items = ref<BackdateAuthorization[]>([])
  const page = reactive({ total: 0 })
  const statusFilter = ref<BackdateStatus | undefined>()
  const statuses: BackdateStatus[] = ['PENDING', 'APPROVED', 'REJECTED', 'CONSUMED', 'EXPIRED']
  const requestVisible = ref(false)
  const reviewVisible = ref(false)
  const selected = ref<BackdateAuthorization | null>(null)
  const requestForm = reactive({ document_type: 'INVOICE' as const, business_date: '', reason: '' })
  const reviewForm = reactive({ decision_notes: '', expires_at: '' })
  const isAdministrator = computed(() => userStore.info?.roles?.includes('Administrator') === true)
  const load = async () => {
    loading.value = true
    try {
      const result = await fetchBackdateAuthorizations(statusFilter.value)
      items.value = result.data || []
      page.total = result.total || 0
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load backdate authorizations')
    } finally {
      loading.value = false
    }
  }
  const disableTodayAndFuture = (date: Date) => date >= new Date(new Date().setHours(0, 0, 0, 0))
  const resetRequest = () =>
    Object.assign(requestForm, { document_type: 'INVOICE', business_date: '', reason: '' })
  const submitRequest = async () => {
    if (!requestForm.business_date || requestForm.reason.trim().length < 10)
      return ElMessage.warning('Choose a prior date and provide at least 10 characters of reason.')
    saving.value = true
    try {
      await requestBackdateAuthorization({ ...requestForm, reason: requestForm.reason.trim() })
      ElMessage.success('Backdate request submitted')
      requestVisible.value = false
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not submit the request')
    } finally {
      saving.value = false
    }
  }
  const openReview = (item: BackdateAuthorization) => {
    selected.value = item
    reviewVisible.value = true
  }
  const resetReview = () => {
    selected.value = null
    Object.assign(reviewForm, { decision_notes: '', expires_at: '' })
  }
  const decide = async (action: 'approve' | 'reject') => {
    if (!selected.value || reviewForm.decision_notes.trim().length < 10)
      return ElMessage.warning('Record at least 10 characters of decision notes.')
    if (action === 'approve' && !reviewForm.expires_at)
      return ElMessage.warning('Choose an expiry time.')
    try {
      await ElMessageBox.confirm(`Record this ${action} decision?`, 'Confirm independent review', {
        type: 'warning'
      })
    } catch {
      return
    }
    saving.value = true
    try {
      if (action === 'approve')
        await approveBackdateAuthorization(selected.value.id, {
          decision_notes: reviewForm.decision_notes.trim(),
          expires_at: reviewForm.expires_at
        })
      else await rejectBackdateAuthorization(selected.value.id, reviewForm.decision_notes.trim())
      ElMessage.success(`Request ${action}d`)
      reviewVisible.value = false
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || `Could not ${action} request`)
    } finally {
      saving.value = false
    }
  }
  const statusType = (status: BackdateStatus) =>
    ({
      PENDING: 'warning',
      APPROVED: 'success',
      REJECTED: 'danger',
      CONSUMED: 'info',
      EXPIRED: 'info'
    })[status] as 'success' | 'warning' | 'danger' | 'info'
  const formatDate = (value?: string | null) =>
    value
      ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(
          new Date(value)
        )
      : '—'
  onMounted(load)
</script>
