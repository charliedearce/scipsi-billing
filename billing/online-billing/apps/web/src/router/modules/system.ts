import { AppRouteRecord } from '@/types/router'

export const systemRoutes: AppRouteRecord = {
  path: '/system',
  name: 'System',
  component: '/index/index',
  meta: {
    title: 'menus.system.title',
    icon: 'ri:user-3-line',
    roles: ['Administrator']
  },
  children: [
    {
      path: 'user',
      name: 'User',
      component: '/system/user',
      meta: {
        title: 'menus.system.user',
        icon: 'ri:user-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'role',
      name: 'Role',
      component: '/system/role',
      meta: {
        title: 'menus.system.role',
        icon: 'ri:user-settings-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'settings',
      name: 'Settings',
      component: '/system/settings',
      meta: {
        title: 'menus.system.settings',
        icon: 'ri:settings-4-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'accounting-periods',
      name: 'AccountingPeriods',
      component: '/system/accounting-periods/index',
      meta: {
        title: 'Accounting Periods',
        icon: 'ri:calendar-2-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'audit',
      name: 'Audit',
      component: '/system/audit',
      meta: {
        title: 'menus.system.audit',
        icon: 'ri:file-list-3-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'sms',
      name: 'TransactionalSMS',
      component: '/communication/sms/index',
      meta: {
        title: 'Transactional SMS',
        icon: 'ri:message-2-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'announcements',
      name: 'InAppAnnouncements',
      component: '/communication/announcements/index',
      meta: {
        title: 'Announcements',
        icon: 'ri:broadcast-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'communications-operations',
      name: 'CommunicationOperations',
      component: '/communication/operations/index',
      meta: {
        title: 'Communications Operations',
        icon: 'ri:bar-chart-grouped-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'customers',
      name: 'CustomerAccounts',
      component: '/customer/accounts/index',
      meta: {
        title: 'Customer Accounts',
        icon: 'ri:building-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'document-studio',
      name: 'DocumentStudio',
      component: '/admin/document-studio/index',
      meta: {
        title: 'Document Studio',
        icon: 'ri:layout-masonry-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'user-center',
      name: 'UserCenter',
      component: '/system/user-center',
      meta: {
        title: 'menus.system.userCenter',
        icon: 'ri:user-line',
        isHide: true,
        keepAlive: true,
        isHideTab: true
      }
    }
  ]
}
