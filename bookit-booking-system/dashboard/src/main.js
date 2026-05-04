import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import App from './App.vue'
import BookitTooltip from './components/BookitTooltip.vue'
import routes from './router'
import './assets/main.css'

// Performance audit: Chart.js is imported only inside report views, not globally in the app bootstrap.
NProgress.configure({ showSpinner: false })

// Create router with base path.
const router = createRouter({
  history: createWebHistory('/bookit-dashboard/app/'),
  routes
})

router.beforeEach((to, from, next) => {
  NProgress.start()

  const currentUser = window.BOOKIT_DASHBOARD?.staff || {}
  const role = currentUser.role || ''
  const isAdmin = role === 'admin' || role === 'bookit_admin'

  if (to.meta?.requiresAdmin && !isAdmin) {
    next({ path: '/' })
    return
  }

  next()
})

router.afterEach(() => {
  NProgress.done()
})

// Create and mount app.
const app = createApp(App)
app.component('BookitTooltip', BookitTooltip)
app.use(router)
app.mount('#app')
