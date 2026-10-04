import { AppRouteRecord } from '@/types/router'

export const dashboardRoutes: AppRouteRecord = {
  name: 'Dashboard',
  path: '/dashboard',
  component: '/index/index',
  meta: {
    title: 'Dashboard',
    icon: 'ri:dashboard-3-line',
    roles: ['Administrator', 'Teller', 'Customer', 'PPA user']
  },
  children: [
    {
      path: 'console',
      name: 'Console',
      component: '/dashboard/console',
      meta: {
        title: 'Overview',
        description:
          'Your starting point. Shows the work, balances or queues that matter for your role today, with shortcuts to open each one.',
        icon: 'ri:home-smile-2-line',
        keepAlive: false,
        fixedTab: true
      }
    }
  ]
}
