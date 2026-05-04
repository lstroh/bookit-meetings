/**
 * Consistent color utilities for the dashboard.
 */

export const getStatusColor = (status) => {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    pending_payment: 'bg-orange-100 text-orange-800 border-orange-200',
    confirmed: 'bg-green-100 text-green-800 border-green-200',
    completed: 'bg-blue-100 text-blue-800 border-blue-200',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
    no_show: 'bg-gray-100 text-gray-800 border-gray-200'
  }
  return colors[status] || 'bg-gray-100 text-gray-800 border-gray-200'
}

export const getRoleColor = (role) => {
  const colors = {
    admin: 'bg-purple-100 text-purple-800 border-purple-200',
    staff: 'bg-blue-100 text-blue-800 border-blue-200',
    customer: 'bg-green-100 text-green-800 border-green-200'
  }
  return colors[role] || 'bg-gray-100 text-gray-800 border-gray-200'
}

export const getAvatarColor = (name) => {
  const colors = [
    '#3B82F6', '#8B5CF6', '#EC4899', '#10B981',
    '#F59E0B', '#EF4444', '#6366F1', '#14B8A6'
  ]

  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  return colors[Math.abs(hash) % colors.length]
}

export const getStatusLabel = (status) => {
  const labels = {
    pending: 'Pending',
    pending_payment: 'Pending Payment',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No Show'
  }
  return labels[status] || status
}

export const getPaymentMethodLabel = (method) => {
  const labels = {
    pay_on_arrival: 'Pay on Arrival',
    cash: 'Cash',
    card_external: 'Card',
    check: 'Check',
    complimentary: 'Complimentary',
    stripe: 'Stripe'
  }
  return labels[method] || method || 'Unknown'
}
