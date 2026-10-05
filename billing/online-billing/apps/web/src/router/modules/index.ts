import { AppRouteRecord } from '@/types/router'
import { dashboardRoutes } from './dashboard'
import { portalRoutes } from './portal'
import { operationRoutes } from './operations'
import { ppaRoutes } from './ppa'
import { inboxRoutes } from './inbox'
import { pricingRoutes } from './pricing'
import { administrationRoutes } from './administration'
import { systemRoutes } from './system'

/**
 * Active domain routes for SCIPSI Online Billing, ordered by workflow.
 *
 * Role filtering removes the groups a user cannot reach. A customer sees the
 * portal, a teller sees the billing lifecycle, a PPA user sees PPA, and an
 * administrator sees configuration plus corrections and reports.
 */
export const routeModules: AppRouteRecord[] = [
  dashboardRoutes,
  ...portalRoutes,
  ...operationRoutes,
  ppaRoutes,
  ...inboxRoutes,
  pricingRoutes,
  ...administrationRoutes,
  systemRoutes
]
