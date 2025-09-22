<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { Toaster } from 'vue-sonner'

import { Button } from '@/components/ui/button'
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
