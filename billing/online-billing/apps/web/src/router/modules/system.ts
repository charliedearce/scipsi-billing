import { AppRouteRecord } from '@/types/router'

/**
 * Platform administration. Customer data, document design and outbound
 * communications moved to their own groups in `administration.ts`; what stays
 * here is access control, fiscal calendar, audit and data import.
 */
export const systemRoutes: AppRouteRecord = {
  path: '/system',
  name: 'System',
  component: '/index/index',
  meta: {
    title: 'menus.system.title',
    icon: 'ri:settings-3-line',
    roles: ['Administrator']
  },
  children: [
    {
      path: 'user',
      name: 'User',
      component: '/system/user',
      meta: {
        title: 'menus.system.user',
        description:
          'Create and manage staff and portal user logins, assign roles and locations, and activate or deactivate accounts.',
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
        description:
          'Define roles and the permissions each role grants. Permissions control which menus and actions users can reach.',
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
        description:
          'View and edit the allowed operational preferences for the system and each location. Tariffs, financial rules and secrets such as passwords or API keys are never stored here.',
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
        description:
          'Open and close monthly accounting periods. Invoices, receipts and VIP repayments can only be posted with a business date in an open period.',
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
        description:
          'Search the append-only audit trail of who did what and when: postings, approvals, corrections, settings changes and access events.',
        icon: 'ri:file-list-3-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'legacy-imports',
      name: 'LegacyImports',
      component: '/system/legacy-imports/index',
      meta: {
        title: 'Legacy Import & History',
        description:
          'Import and browse records from the old desktop billing system. Imported records keep their original amounts for reference and do not create active balances.',
        icon: 'ri:database-2-line',
        keepAlive: false,
        roles: ['Administrator']
      }
    },
    {
      path: 'user-center',
      name: 'UserCenter',
      component: '/system/user-center',
      meta: {
        title: 'menus.system.userCenter',
        description: 'Your own login profile: name, contact details and password.',
        icon: 'ri:user-line',
        isHide: true,
        keepAlive: true,
        isHideTab: true
      }
    }
  ]
}
