import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCoachAuthStore } from '../stores/coachAuth'
import { useAdminAuthStore } from '../stores/adminAuth'

const routes = [
  // Member
  { path: '/',               component: () => import('../views/HomeView.vue') },
  { path: '/courses',        component: () => import('../views/CoursesView.vue') },
  { path: '/courses/:id',    component: () => import('../views/CourseDetailView.vue') },
  { path: '/login',          component: () => import('../views/LoginView.vue') },
  { path: '/register',       component: () => import('../views/RegisterView.vue') },
  { path: '/auth/callback',  component: () => import('../views/AuthCallbackView.vue') },
  { path: '/profile',     component: () => import('../views/ProfileView.vue'),    meta: { requiresAuth: true } },
  { path: '/my-bookings', component: () => import('../views/MyBookingsView.vue'), meta: { requiresAuth: true } },

  // Coach (public)
  { path: '/coach/login',    component: () => import('../views/coach/LoginView.vue') },
  { path: '/coach/register', component: () => import('../views/coach/RegisterView.vue') },
  // Coach (protected)
  {
    path: '/coach',
    component: () => import('../layouts/CoachLayout.vue'),
    meta: { requiresCoach: true },
    children: [
      { path: 'dashboard',       component: () => import('../views/coach/DashboardView.vue') },
      { path: 'offers/new',      component: () => import('../views/coach/OfferFormView.vue') },
      { path: 'offers/:id/edit', component: () => import('../views/coach/OfferFormView.vue') },
      { path: 'profile',         component: () => import('../views/coach/ProfileView.vue') },
      { path: 'schedules',       component: () => import('../views/coach/ScheduleManagerView.vue') },
      { path: 'bookings',        component: () => import('../views/coach/BookingManagerView.vue') },
    ],
  },

  // Admin (public)
  { path: '/admin/login', component: () => import('../views/admin/LoginView.vue') },
  // Admin (protected)
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { requiresAdmin: true },
    children: [
      { path: 'dashboard', component: () => import('../views/admin/DashboardView.vue') },
      { path: 'members',   component: () => import('../views/admin/MembersView.vue') },
      { path: 'providers', component: () => import('../views/admin/ProvidersView.vue') },
      { path: 'offers',    component: () => import('../views/admin/OffersView.vue') },
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
  const adminAuth = useAdminAuthStore()

  if (to.meta.requiresAuth  && !auth.isLoggedIn)      return { path: '/login' }
  if (to.meta.requiresCoach && !coachAuth.isLoggedIn) return { path: '/coach/login' }
  if (to.meta.requiresAdmin && !adminAuth.isLoggedIn) return { path: '/admin/login' }
})

export default router
