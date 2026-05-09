import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/',               component: () => import('../views/HomeView.vue') },
  { path: '/courses',        component: () => import('../views/CoursesView.vue') },
  { path: '/courses/:id',    component: () => import('../views/CourseDetailView.vue') },
  { path: '/login',          component: () => import('../views/LoginView.vue') },
  { path: '/register',       component: () => import('../views/RegisterView.vue') },
  { path: '/auth/callback',  component: () => import('../views/AuthCallbackView.vue') },
  { path: '/profile',        component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { path: '/login' }
  }
})

export default router
