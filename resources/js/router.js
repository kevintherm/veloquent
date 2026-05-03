import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from './pages/Dashboard/Dashboard.vue'
import Login from './pages/Login.vue'
import NotFound from './pages/NotFound.vue'
import Register from './pages/Register.vue'
import Settings from './pages/Settings/Settings.vue'
import LogsOverview from './pages/Logs/LogsOverview.vue'
import { useAuth } from './lib/auth'
import { isOnboardingInitialized } from './lib/onboarding'
import { getAuthToken } from './lib/tokenAuth'
import { VELO_CONFIG } from './lib/config'

const routes = [
  { path: '/', component: Dashboard },
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/settings', component: Settings },
  { path: '/logs', component: LogsOverview },
  { path: '/:collection', component: Dashboard },
  {
    path: '/:pathMatch(.*)*',
    component: NotFound,
  }
]

const adminPrefix = VELO_CONFIG.admin_prefix || '/';
const routerBase = adminPrefix.startsWith('/') ? adminPrefix : `/${adminPrefix}`;

const router = createRouter({
  history: createWebHistory(routerBase),
  routes,
})

router.beforeEach(async (to) => {
  const { state, fetchUser } = useAuth()
  const token = getAuthToken()
  const publicPaths = ['/login', '/register']

  if (!state.initialized || (token && !state.user)) {
    await fetchUser()
  }

  const isAuthenticated = Boolean(state.user && getAuthToken())

  if (to.path === '/register') {
    const initialized = await isOnboardingInitialized()

    if (initialized) {
      return { path: '/login' }
    }

    return true
  }

  if (to.path === '/login' && isAuthenticated) {
    return { path: '/' }
  }

  if (!publicPaths.includes(to.path) && !isAuthenticated) {
    const initialized = await isOnboardingInitialized()

    return { path: initialized ? '/login' : '/register' }
  }

  return true
})

export default router
