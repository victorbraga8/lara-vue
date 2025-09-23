import { createRouter, createWebHistory } from 'vue-router'

import DashboardView from '@/views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: DashboardView,
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    { path: '/sandbox', name: 'sandbox', component: () => import('../views/Sandbox.vue') },
    { path: '/produtos', name: 'produtos', component: () => import('../views/ProdutosView.vue') },
    { path: '/vendas', name: 'vendas', component: () => import('../views/VendasView.vue') },
  ],
})

export default router
