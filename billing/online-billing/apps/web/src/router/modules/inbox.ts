import { AppRouteRecord } from '@/types/router'

/**
 * Cross-role inbox entries. These are not part of any single workflow, so they
 * stay as top-level items between the workflow groups and the admin groups.
 */
export const inboxRoutes: AppRouteRecord[] = [
  {
    path: '/bulletin-board',
    name: 'BulletinBoard',
    component: '/communication/bulletin-board/index',
    meta: {
      title: 'Bulletin Board',
      description: 'Current and past announcements for your account and role.',
      icon: 'ri:megaphone-line',
      keepAlive: false,
      roles: ['Customer', 'Teller', 'PPA user']
    }
  },
  {
    path: '/chat',
    name: 'PortalChat',
    component: '/communication/chat/index',
    meta: {
      title: 'Messages',
      description:
        'Chat between customers and SCIPSI staff about a bill, payment or request. Messages are for conversation only and never change a financial record.',
      icon: 'ri:message-3-line',
      keepAlive: false,
      roles: ['Customer', 'Teller', 'Administrator']
    }
  },
  {
    path: '/notifications',
    name: 'PortalNotifications',
    component: '/communication/notifications/index',
    meta: {
      title: 'Notifications',
      description:
        'Work alerts about your bills, payments, claims and reviews, kept separate from chat. Open an alert to jump to the related record.',
      icon: 'ri:notification-2-line',
      keepAlive: true,
      roles: ['Customer', 'Teller', 'Administrator', 'PPA user']
    }
  }
]
