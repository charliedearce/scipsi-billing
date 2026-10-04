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
 * Role filtering removes the groups a user cannot reach, so a customer sees the
 * portal groups, a teller sees the lifecycle groups, and only an administrator
 * sees the configuration groups at the end.
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
