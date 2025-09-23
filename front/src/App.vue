<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { Toaster } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Sun, Moon, Menu as MenuIcon, X } from 'lucide-vue-next'

type Theme = 'light' | 'dark'
const theme = ref<Theme>('light')
const open = ref(false)
const route = useRoute()

function applyTheme(t: Theme) {
  const root = document.documentElement
  if (t === 'dark') root.classList.add('dark')
  else root.classList.remove('dark')
  localStorage.setItem('theme', t)
  theme.value = t
}
function toggleTheme() { applyTheme(theme.value === 'dark' ? 'light' : 'dark') }
function toggleMenu() { open.value = !open.value }
function closeMenu() { open.value = false }

watch(() => route.fullPath, () => closeMenu())

onMounted(() => {
  const saved = (localStorage.getItem('theme') as Theme | null)
  if (saved) return applyTheme(saved)
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  applyTheme(prefersDark ? 'dark' : 'light')
})
</script>

<template>
  <header
    class="w-full border-b bg-background/60 backdrop-blur supports-[backdrop-filter]:bg-background/60 overflow-x-clip"
  >
    <div
      class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-14 w-full flex items-center justify-between gap-3 min-w-0"
    >
      <RouterLink to="/" class="font-semibold tracking-tight shrink-0">LaraVue</RouterLink>

      <div class="flex items-center gap-2 min-w-0">

        <Button
          variant="outline"
          size="icon"
          class="h-8 w-8 shrink-0"
          @click="toggleTheme"
          :aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
          title="Alternar tema"
        >
          <Sun v-if="theme === 'light'" class="h-4 w-4" />
          <Moon v-else class="h-4 w-4" />
        </Button>

        <Button
          variant="outline"
          size="icon"
          class="h-8 w-8 sm:hidden shrink-0"
          @click="toggleMenu"
          :aria-expanded="open"
          aria-controls="mobile-nav"
          :aria-label="open ? 'Fechar menu' : 'Abrir menu'"
        >
          <MenuIcon v-if="!open" class="h-5 w-5" />
          <X v-else class="h-5 w-5" />
        </Button>

        <nav class="hidden sm:flex items-center gap-2 min-w-0">
          <RouterLink to="/" class="text-sm px-3 py-2 rounded-md hover:bg-muted shrink-0">Dashboard</RouterLink>
          <RouterLink to="/produtos" class="text-sm px-3 py-2 rounded-md hover:bg-muted shrink-0">Produtos</RouterLink>
          <RouterLink to="/compras" class="text-sm px-3 py-2 rounded-md hover:bg-muted shrink-0">Compras</RouterLink>
          <RouterLink to="/vendas" class="text-sm px-3 py-2 rounded-md hover:bg-muted shrink-0">Vendas</RouterLink>
        </nav>
      </div>
    </div>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <nav
        v-if="open"
        id="mobile-nav"
        class="sm:hidden border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 overflow-x-clip"
      >
        <div class="mx-auto max-w-7xl px-4 py-3">
          <ul class="flex flex-col gap-1">
            <li><RouterLink @click="closeMenu" to="/" class="block px-3 py-2 rounded-md hover:bg-muted">Dashboard</RouterLink></li>
            <li><RouterLink @click="closeMenu" to="/produtos" class="block px-3 py-2 rounded-md hover:bg-muted">Produtos</RouterLink></li>
            <li><RouterLink @click="closeMenu" to="/compras" class="block px-3 py-2 rounded-md hover:bg-muted">Compras</RouterLink></li>
            <li><RouterLink @click="closeMenu" to="/vendas" class="block px-3 py-2 rounded-md hover:bg-muted">Vendas</RouterLink></li>
          </ul>
        </div>
      </nav>
    </transition>
  </header>

  <main class="w-full mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 min-w-0 overflow-x-hidden">
    <RouterView />
  </main>

  <Toaster richColors position="top-right" />
</template>

