export default [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { title: "Today's Schedule" }
  },
  {
    path: '/bookings',
    name: 'bookings',
    component: () => import('../views/Bookings.vue'),
    meta: { title: 'Bookings' }
  },
  {
    path: '/services',
    name: 'services',
    component: () => import('../views/Services.vue'),
    meta: { title: 'Services' }
  },
  {
    path: '/categories',
    name: 'categories',
    component: () => import('../views/Categories.vue'),
    meta: { title: 'Categories' }
  },
  {
    path: '/staff',
    name: 'staff',
    component: () => import('../views/Staff.vue'),
    meta: { title: 'Staff' }
  },
  {
    path: '/staff/:staff_id/hours',
    name: 'StaffHours',
    component: () => import('../views/StaffHours.vue'),
    meta: { title: 'Working Hours' }
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../views/Settings.vue'),
    meta: { title: 'Settings' }
  },
  {
    path: '/profile',
    name: 'MyProfile',
    component: () => import('../views/MyProfile.vue'),
    meta: { title: 'My Profile' }
  },
  {
    path: '/my-schedule',
    name: 'MySchedule',
    component: () => import('../views/MySchedule.vue'),
    meta: { title: 'My Schedule' }
  },
  {
    path: '/my-availability',
    name: 'MyAvailability',
    component: () => import('../views/MyAvailability.vue'),
    meta: { title: 'My Availability' }
  },
  {
    path: '/team-calendar',
    name: 'TeamCalendar',
    component: () => import('../views/TeamCalendar.vue'),
    meta: { title: 'Team Calendar', requiresAdmin: true }
  },
  // Reports (admin only)
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('../views/Reports.vue'),
    meta: { title: 'Reports Overview', requiresAdmin: true }
  },
  {
    path: '/reports/revenue',
    name: 'RevenueReport',
    component: () => import('../views/RevenueReport.vue'),
    meta: { title: 'Revenue Report', requiresAdmin: true }
  },
  {
    path: '/reports/bookings',
    name: 'BookingAnalytics',
    component: () => import('../views/BookingAnalytics.vue'),
    meta: { title: 'Booking Analytics', requiresAdmin: true }
  },
  {
    path: '/reports/staff',
    name: 'StaffPerformance',
    component: () => import('../views/StaffPerformance.vue'),
    meta: { title: 'Staff Performance', requiresAdmin: true }
  },
  {
    path: '/reports/staff/:id',
    name: 'StaffDetail',
    component: () => import('../views/StaffDetail.vue'),
    meta: { title: 'Staff Detail', requiresAdmin: true }
  },
  // Customers (admin only)
  {
    path: '/customers',
    name: 'Customers',
    component: () => import('../views/Customers.vue'),
    meta: { title: 'Customers', requiresAdmin: true }
  },
  {
    path: '/customers/:id',
    name: 'CustomerProfile',
    component: () => import('../views/CustomerProfile.vue'),
    meta: { title: 'Customer Profile', requiresAdmin: true }
  },
  {
    path: '/packages',
    name: 'Packages',
    component: () => import('../views/Packages.vue'),
    meta: { title: 'Packages', requiresAdmin: true }
  },
  {
    path: '/email-queue',
    name: 'EmailQueue',
    component: () => import('../views/EmailQueue.vue'),
    meta: { title: 'Email Queue', requiresAdmin: true }
  },
  {
    path: '/settings/email',
    name: 'EmailSettings',
    component: () => import('../views/EmailSettings.vue'),
    meta: { title: 'Email Configuration', requiresAdmin: true }
  },
  {
    path: '/settings/cancellation',
    name: 'CancellationPolicy',
    component: () => import('../views/CancellationPolicy.vue'),
    meta: { title: 'Cancellation Policy', requiresAdmin: true }
  },
  {
    path: '/settings/payments',
    name: 'PaymentSettings',
    component: () => import('../views/PaymentSettings.vue'),
    meta: { title: 'Payment Gateways', requiresAdmin: true }
  },
  {
    path: '/settings/deposits',
    name: 'DepositSettings',
    component: () => import('../views/DepositSettings.vue'),
    meta: { title: 'Deposit Settings', requiresAdmin: true }
  },
  {
    path: '/settings/templates',
    name: 'EmailTemplates',
    component: () => import('../views/EmailTemplates.vue'),
    meta: { title: 'Email Templates', requiresAdmin: true }
  },
  {
    path: '/settings/bulk-hours',
    name: 'BulkHours',
    component: () => import('../views/BulkHours.vue'),
    meta: { title: 'Bulk Working Hours', requiresAdmin: true }
  },
  {
    path: '/settings/extensions',
    name: 'SettingsExtensions',
    component: () => import('../views/SettingsExtensions.vue'),
    meta: { title: 'Extensions', requiresAdmin: true }
  },
  {
    path: '/audit-log',
    name: 'AuditLog',
    component: () => import('../views/AuditLog.vue'),
    meta: { title: 'Audit Log', requiresAdmin: true }
  }
]
