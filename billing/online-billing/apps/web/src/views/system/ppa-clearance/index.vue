<template>
  <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-6">
    <section class="art-card p-6 flex flex-wrap items-center justify-between gap-4"
      ><div
        ><h1 class="text-xl font-bold text-g-900">PPA Clearance Policy</h1
        ><p class="text-sm text-g-500 mt-1"
          >Versioned policy controls whether a qualifying VIP credit status can be accepted by PPA.
          It never changes paid/unpaid status.</p
        ></div
      ><ElButton :loading="loading" @click="load">Refresh</ElButton></section
    >
    <ElAlert
      type="warning"
      :closable="false"
      show-icon
      title="Without an active published policy, PPA verification safely requires full payment. This screen does not issue a formal release or gate pass."
    />
    <ElCard shadow="never"
      ><template #header><h2 class="font-semibold">New PPA clearance policy draft</h2></template
      ><ElForm :model="form" label-position="top" class="grid grid-cols-1 md:grid-cols-2 gap-x-5"
        ><ElFormItem label="On-credit result"
          ><ElSwitch
            v-model="form.accept_qualifying_vip_credit"
            active-text="Accept qualifying VIP credit"
            inactive-text="Require full payment" /></ElFormItem
        ><ElFormItem label="Effective from"
          ><ElDatePicker
            v-model="form.effective_from"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full" /></ElFormItem></ElForm
      ><div class="flex justify-end"
        ><ElButton type="primary" :loading="saving" @click="create">Create draft</ElButton></div
      ></ElCard
    >
    <ElCard shadow="never"
      ><template #header><h2 class="font-semibold">Policy history</h2></template
      ><ElTable :data="policies"
        ><ElTableColumn prop="version_number" label="Version" width="100" /><ElTableColumn
          label="PPA on-credit handling"
          min-width="240"
          ><template #default="{ row }">{{
            row.accept_qualifying_vip_credit
              ? 'Accept qualifying VIP credit'
              : 'Require full payment'
          }}</template></ElTableColumn
        ><ElTableColumn
          prop="effective_from"
          label="Effective from"
          min-width="190"
        /><ElTableColumn prop="status" label="Status" width="120" /><ElTableColumn
          label="Action"
          width="130"
          ><template #default="{ row }"
            ><ElButton
              v-if="row.status === 'DRAFT'"
              type="primary"
              size="small"
              @click="publish(row)"
              >Publish</ElButton
            ></template
          ></ElTableColumn
        ></ElTable
      ></ElCard
    >
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    createPpaClearancePolicy,
    fetchPpaClearancePolicies,
    publishPpaClearancePolicy,
    type PpaClearancePolicy
  } from '@/api/ppaClearance'

  defineOptions({ name: 'PpaClearancePolicies' })
  const loading = ref(false)
  const saving = ref(false)
  const policies = ref<PpaClearancePolicy[]>([])
  const form = reactive({
    accept_qualifying_vip_credit: false,
    effective_from: new Date().toISOString()
  })
  async function load() {
    loading.value = true
    try {
      policies.value = await fetchPpaClearancePolicies()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load PPA clearance policies.')
    } finally {
      loading.value = false
    }
  }
  async function create() {
    saving.value = true
    try {
      await createPpaClearancePolicy(form)
      ElMessage.success('PPA clearance policy draft created.')
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to create PPA clearance policy.')
    } finally {
      saving.value = false
    }
  }
  async function publish(policy: PpaClearancePolicy) {
    try {
      const result = await ElMessageBox.prompt(
        'Explain why this policy is being published. It does not change payment status.',
        'Publish PPA clearance policy',
        { inputPattern: /.{3,}/, inputErrorMessage: 'Give a reason of at least three characters.' }
      )
      await publishPpaClearancePolicy(policy.id, policy.lock_version, result.value)
      ElMessage.success('PPA clearance policy published.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to publish PPA clearance policy.')
    }
  }
  onMounted(load)
</script>
