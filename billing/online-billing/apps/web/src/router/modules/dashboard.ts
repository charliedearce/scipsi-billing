import { AppRouteRecord } from '@/types/router'

export const dashboardRoutes: AppRouteRecord = {
  name: 'Dashboard',
  path: '/dashboard',
  component: '/index/index',
  meta: {
    title: 'Dashboard',
    icon: 'ri:dashboard-3-line',
    roles: ['Administrator', 'Teller', 'Customer', 'PPA User']
  },
  children: [
    {
      path: 'console',
      name: 'Console',
      component: '/dashboard/console',
      meta: {
        title: 'Overview',
        icon: 'ri:home-smile-2-line',
        keepAlive: false,
        fixedTab: true
      }
    }
  ]
}
