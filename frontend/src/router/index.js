import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCoachAuthStore } from '../stores/coachAuth'

const routes = [
  // Member
  { path: '/',               component: () => import('../views/HomeView.vue') },
  { path: '/courses',        component: () => import('../views/CoursesView.vue') },
  { path: '/courses/:id',    component: () => import('../views/CourseDetailView.vue') },
  { path: '/login',          component: () => import('../views/LoginView.vue') },
  { path: '/register',       component: () => import('../views/RegisterView.vue') },
  { path: '/auth/callback',  component: () => import('../views/AuthCallbackView.vue') },
  { path: '/profile',        component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },

  // Coach (public)
  { path: '/coach/login',    component: () => import('../views/coach/LoginView.vue') },
  { path: '/coach/register', component: () => import('../views/coach/RegisterView.vue') },
  // Coach (protected) — wrapped in CoachLayout
  {
    path: '/coach',
    component: () => import('../layouts/CoachLayout.vue'),
    meta: { requiresCoach: true },
    children: [
      { path: 'dashboard',       component: () => import('../views/coach/DashboardView.vue') },
      { path: 'offers/new',      component: () => import('../views/coach/OfferFormView.vue') },
      { path: 'offers/:id/edit', component: () => import('../views/coach/OfferFormView.vue') },
      { path: 'profile',         component: () => import('../views/coach/ProfileView.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth      = useAuthStore()
  const coachAuth = useCoachAuthStore()

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { path: '/login' }
  }
  if (to.meta.requiresCoach && !coachAuth.isLoggedIn) {
    return { path: '/coach/login' }
  }
})

export default router
