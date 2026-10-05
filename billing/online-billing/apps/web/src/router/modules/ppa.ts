import { AppRouteRecord } from '@/types/router'

/**
 * Philippine Ports Authority workspace. Children keep their original absolute
 * paths so existing deep links resolve unchanged.
 */
export const ppaRoutes: AppRouteRecord = {
  path: '/ppa',
  name: 'Ppa',
  component: '/index/index',
  meta: {
    title: 'PPA',
    icon: 'ri:ship-line',
    roles: ['PPA user'],
    absoluteChildPaths: true
  },
  children: [
    {
      path: '/ppa-verification',
      name: 'PpaBillVerification',
      component: '/payments/ppa-verification/index',
      meta: {
        title: 'PPA Bill Verification',
        description:
          'Look up a bill or official receipt to confirm it was issued and whether it is paid. Read-only; it never posts money or releases cargo.',
        icon: 'ri:shield-check-line',
        keepAlive: true,
        roles: ['PPA user']
      }
    },
    {
      path: '/ppa-share-report',
      name: 'PpaShareReport',
      component: '/payments/ppa-share-report/index',
      meta: {
        title: 'PPA Share Report',
        description:
          'Fully paid bills in a date range with the PPA share stored on each bill and the receipts that settled them. Defaults to the current month; widen the range to see earlier bills.',
        icon: 'ri:pie-chart-2-line',
        keepAlive: true,
        roles: ['PPA user']
      }
    }
  ]
}
