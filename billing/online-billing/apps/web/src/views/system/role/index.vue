<template>
  <div class="page-content space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-medium text-g-900">Roles &amp; permissions</h1>
        <p class="mt-1 text-sm text-g-500">
          Built-in roles are fixed. Create organization roles to add permission bundles, then assign
          them in User Administration alongside a built-in role for navigation.
        </p>
      </div>
      <div class="flex gap-2">
        <ElButton :loading="loading" @click="load">Refresh</ElButton>
        <ElButton type="primary" @click="openCreate">New role</ElButton>
      </div>
    </header>

    <section class="art-card p-5">
      <ElInput
        v-model="search"
        clearable
        placeholder="Search roles or permissions"
        class="mb-4 !w-full sm:!w-80"
      />
      <ElTable v-loading="loading" :data="filteredRoles" row-key="id" stripe>
        <ElTableColumn type="expand" width="48">
          <template #default="{ row }">
            <div class="space-y-4 px-4 py-3">
              <div v-for="group in permissionGroups(row.permissions)" :key="group.category">
                <h3 class="mb-2 text-sm font-medium text-g-800">{{ group.category }}</h3>
                <div class="flex flex-wrap gap-2">
                  <ElTooltip
                    v-for="permission in group.permissions"
                    :key="permission.id"
                    :content="permission.description || permission.name"
                  >
                    <ElTag type="info" effect="plain">{{ permission.name }}</ElTag>
                  </ElTooltip>
                </div>
              </div>
              <p v-if="!row.permissions.length" class="text-sm text-g-500">
                No permissions assigned.
              </p>
            </div>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="name" label="Role" min-width="150" />
        <ElTableColumn prop="label" label="Description" min-width="220" />
        <ElTableColumn label="Scope" width="145">
          <template #default="{ row }">
            {{ row.organization_id === null ? 'Built-in' : 'Organization' }}
          </template>
        </ElTableColumn>
        <ElTableColumn label="Permissions" width="120">
          <template #default="{ row }">{{ row.permissions.length }}</template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="170" fixed="right">
          <template #default="{ row }">
            <template v-if="!row.is_system">
              <ElButton link type="primary" @click="openEdit(row)">Edit</ElButton>
              <ElButton link type="danger" @click="remove(row)">Delete</ElButton>
            </template>
            <span v-else class="text-g-500">Protected</span>
          </template>
        </ElTableColumn>
      </ElTable>
      <p v-if="loadError" class="mt-3 text-sm text-error">{{ loadError }}</p>
    </section>

    <ElDialog
      v-model="dialogVisible"
      :title="editing ? 'Edit organization role' : 'Create organization role'"
      width="min(720px, 94vw)"
      destroy-on-close
    >
      <ElForm label-position="top">
        <ElFormItem label="Role name" required>
          <ElInput v-model="form.name" maxlength="64" placeholder="e.g. Billing Supervisor" />
          <p class="text-xs text-g-500">Unique role name used for assignment and access checks.</p>
        </ElFormItem>
        <ElFormItem label="Description" required>
          <ElInput v-model="form.label" maxlength="100" placeholder="What this role is for" />
        </ElFormItem>
        <ElFormItem label="Permissions">
          <ElCheckboxGroup v-model="form.permission_ids" class="w-full space-y-4">
            <div v-for="group in allPermissionGroups" :key="group.category">
              <h3 class="mb-2 text-sm font-medium text-g-800">{{ group.category }}</h3>
              <div class="grid gap-2 sm:grid-cols-2">
                <ElCheckbox
                  v-for="permission in group.permissions"
                  :key="permission.id"
                  :label="permission.id"
                >
                  <span :title="permission.description || permission.name">{{
                    permission.name
                  }}</span>
                </ElCheckbox>
              </div>
            </div>
          </ElCheckboxGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="saving" @click="save">Save role</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    createRole,
    deleteRole,
    fetchPermissionList,
    fetchRoleList,
    updateRole,
    type PermissionItem,
    type RoleItem,
    type RolePayload
  } from '@/api/roles'

  defineOptions({ name: 'Role' })

  const roles = ref<RoleItem[]>([])
  const permissions = ref<PermissionItem[]>([])
  const search = ref('')
  const loading = ref(false)
  const saving = ref(false)
  const loadError = ref('')
  const dialogVisible = ref(false)
  const editing = ref<RoleItem | null>(null)
  const form = reactive<RolePayload>({ name: '', label: '', permission_ids: [] })

  const filteredRoles = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return roles.value
    return roles.value.filter((role) =>
      [role.name, role.label, ...role.permissions.map((permission) => permission.name)]
        .join(' ')
        .toLowerCase()
        .includes(query)
    )
  })
  const allPermissionGroups = computed(() => permissionGroups(permissions.value))

  function permissionGroups(items: PermissionItem[]) {
    const groups = new Map<string, PermissionItem[]>()
    for (const item of items) {
      const category = item.category || 'Other'
      groups.set(category, [...(groups.get(category) || []), item])
    }
    return Array.from(groups, ([category, groupItems]) => ({
      category,
      permissions: groupItems
    }))
  }

  async function load() {
    loading.value = true
    loadError.value = ''
    try {
      const [roleList, permissionGroups] = await Promise.all([
        fetchRoleList(),
        fetchPermissionList()
      ])
      roles.value = roleList
      permissions.value = Object.values(permissionGroups).flat()
    } catch (error: any) {
      loadError.value = error?.message || 'Unable to load roles.'
    } finally {
      loading.value = false
    }
  }

  function openCreate() {
    editing.value = null
    Object.assign(form, { name: '', label: '', permission_ids: [], lock_version: undefined })
    dialogVisible.value = true
  }

  function openEdit(role: RoleItem) {
    editing.value = role
    Object.assign(form, {
      name: role.name,
      label: role.label,
      permission_ids: role.permissions.map((permission) => permission.id),
      lock_version: role.lock_version
    })
    dialogVisible.value = true
  }

  async function save() {
    if (!form.name.trim() || !form.label.trim()) {
      ElMessage.warning('Enter a role name and description.')
      return
    }
    saving.value = true
    try {
      if (editing.value) await updateRole(editing.value.id, { ...form })
      else await createRole({ ...form })
      dialogVisible.value = false
      ElMessage.success('Role saved.')
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to save role. Refresh and try again.')
    } finally {
      saving.value = false
    }
  }

  async function remove(role: RoleItem) {
    try {
      await ElMessageBox.confirm(
        `Delete ${role.name}? This is allowed only when no users have this role.`,
        'Delete organization role',
        { type: 'warning', confirmButtonText: 'Delete', confirmButtonClass: 'el-button--danger' }
      )
      await deleteRole(role.id, role.lock_version)
      ElMessage.success('Role deleted.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close') {
        ElMessage.error(error?.message || 'Unable to delete role.')
      }
    }
  }

  onMounted(load)
</script>
