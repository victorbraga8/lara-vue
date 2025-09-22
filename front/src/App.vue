<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { Toaster } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Sun, Moon } from 'lucide-vue-next'
type Theme = 'light' | 'dark'
const theme = ref<Theme>('light')

function applyTheme(t: Theme) {
  const root = document.documentElement
  if (t === 'dark') root.classList.add('dark')
  else root.classList.remove('dark')
  localStorage.setItem('theme', t)
  theme.value = t
}

function toggleTheme() {
  applyTheme(theme.value === 'dark' ? 'light' : 'dark')
}

onMounted(() => {
  const saved = (localStorage.getItem('theme') as Theme | null)
  if (saved) return applyTheme(saved)
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  applyTheme(prefersDark ? 'dark' : 'light')
})
</script>

<template>

  <header class="border-b bg-background/60 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
      <RouterLink to="/" class="font-semibold tracking-tight">LaraVue</RouterLink>

      <nav class="flex items-center gap-2">
        <RouterLink to="/" class="text-sm px-3 py-2 rounded-md hover:bg-muted">Dashboard</RouterLink>
        <RouterLink to="/produtos" class="text-sm px-3 py-2 rounded-md hover:bg-muted">Produtos</RouterLink>
        <RouterLink to="/compras" class="text-sm px-3 py-2 rounded-md hover:bg-muted">Compras</RouterLink>
        <RouterLink to="/vendas" class="text-sm px-3 py-2 rounded-md hover:bg-muted">Vendas</RouterLink>      
        <Button
          variant="outline"
          size="icon"
          class="h-8 w-8"
          @click="toggleTheme"
          :aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
          title="Alternar tema"
        >
          <Sun v-if="theme === 'light'" class="h-4 w-4" />
          <Moon v-else class="h-4 w-4" />
        </Button>
        <RouterLink to="/about">
          <Button variant="outline" size="sm">Sobre</Button>
        </RouterLink>
      </nav>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
    <RouterView />
  </main>

  <Toaster richColors position="top-right" />
</template>
