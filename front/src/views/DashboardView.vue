<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { useQuery } from '@tanstack/vue-query'

import { getOverview } from '@/services/metrics'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'

import {
  Package,
  ShoppingCart,
  HandCoins,
  BarChart3,
  Box,
  DollarSign,
  TrendingUp,
  AlertTriangle,
  RefreshCw,
  Loader2,
} from 'lucide-vue-next'

const router = useRouter()

const { data, isLoading, isFetching, refetch, error } = useQuery<any>({
  queryKey: ['metrics', 'overview'],
  queryFn: getOverview,
})

import { watchEffect } from 'vue'
watchEffect(() => {
  if (error.value) {
    const msg = error.value?.message || 'Falha ao carregar métricas'
    toast.error(msg, { position: 'top-center' })
  }
})

const m = computed<any>(() => data.value ?? {})

function go(path: string) {
  router.push(path)
}
function money(v?: string | number) {
  const n = typeof v === 'string' ? Number(v) : (v ?? 0)
  return isFinite(n) ? n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'
}
function int(v: any, alt = 0) {
  const n = Number(v)
  return Number.isFinite(n) ? n : alt
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p class="text-sm text-muted-foreground">
          Visão geral do período atual
          {{ m?.month_label ? ' (' + m.month_label + ')' : '' }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button class="gap-2" @click="go('/produtos')"><Package class="w-4 h-4" /> Produtos</Button>
        <Button variant="secondary" class="gap-2" @click="go('/compras')"><ShoppingCart class="w-4 h-4" /> Compras</Button>
        <Button variant="outline" class="gap-2" @click="go('/vendas')"><HandCoins class="w-4 h-4" /> Vendas</Button>

        <Button variant="outline" size="sm" class="ml-2 gap-2" @click="refetch()" :disabled="isFetching">
          <Loader2 v-if="isFetching" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          Atualizar
        </Button>
      </div>
    </div>

    <div class="grid gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      <template v-if="isLoading || isFetching">
        <Card v-for="i in 4" :key="'kpi-skel-' + i" class="shadow-sm">
          <CardHeader class="pb-2 bg-accent/40 rounded-t-2xl">
            <Skeleton class="h-4 w-24" />
          </CardHeader>
          <CardContent class="space-y-2">
            <Skeleton class="h-7 w-28" />
            <Skeleton class="h-3 w-32" />
          </CardContent>
        </Card>
      </template>

      <template v-else>
        <Card class="shadow-sm">
          <CardHeader class="flex items-center justify-between pb-2 bg-accent/40 rounded-t-2xl">
            <CardTitle class="text-sm font-medium">Receita (mês)</CardTitle>
            <DollarSign class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ money(m?.revenue_month) }}</div>
            <p class="text-xs text-muted-foreground">Vendas concluídas no mês</p>
          </CardContent>
        </Card>

        <Card class="shadow-sm">
          <CardHeader class="flex items-center justify-between pb-2 bg-accent/40 rounded-t-2xl">
            <CardTitle class="text-sm font-medium">Lucro (mês)</CardTitle>
            <TrendingUp class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ money(m?.profit_month) }}</div>
            <p class="text-xs text-muted-foreground">Com base no custo médio</p>
          </CardContent>
        </Card>

        <Card class="shadow-sm">
          <CardHeader class="flex items-center justify-between pb-2 bg-accent/40 rounded-t-2xl">
            <CardTitle class="text-sm font-medium">Produtos</CardTitle>
            <Box class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ int(m?.products_count ?? m?.products, 0) }}</div>
            <p class="text-xs text-muted-foreground">Itens cadastrados</p>
          </CardContent>
        </Card>

        <Card class="shadow-sm">
          <CardHeader class="flex items-center justify-between pb-2 bg-accent/40 rounded-t-2xl">
            <CardTitle class="text-sm font-medium">Estoque crítico</CardTitle>
            <AlertTriangle class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ int(m?.low_stock ?? m?.critical_stock, 0) }}</div>
            <p class="text-xs text-muted-foreground">≤ 5 unidades</p>
          </CardContent>
        </Card>
      </template>
    </div>

    <div class="grid gap-4 md:gap-6 grid-cols-1 lg:grid-cols-3">
      <Card class="lg:col-span-2 shadow-sm">
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Acumulado geral</CardTitle>
            <Badge variant="secondary" class="font-normal">desde o início</Badge>
          </div>
        </CardHeader>
        <Separator />
        <CardContent class="pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <template v-if="isLoading || isFetching">
            <div class="rounded-xl border p-4">
              <Skeleton class="h-4 w-28 mb-2" />
              <Skeleton class="h-6 w-24" />
            </div>
            <div class="rounded-xl border p-4">
              <Skeleton class="h-4 w-24 mb-2" />
              <Skeleton class="h-6 w-24" />
            </div>
            <div class="rounded-xl border p-4">
              <Skeleton class="h-4 w-36 mb-2" />
              <Skeleton class="h-6 w-10" />
            </div>
            <div class="rounded-xl border p-4">
              <Skeleton class="h-4 w-36 mb-2" />
              <Skeleton class="h-6 w-10" />
            </div>
          </template>

          <template v-else>
            <div class="rounded-xl border p-4">
              <div class="text-sm text-muted-foreground">Receita total</div>
              <div class="text-xl font-semibold">{{ money(m?.revenue_total) }}</div>
            </div>
            <div class="rounded-xl border p-4">
              <div class="text-sm text-muted-foreground">Lucro total</div>
              <div class="text-xl font-semibold">{{ money(m?.profit_total) }}</div>
            </div>
            <div class="rounded-xl border p-4">
              <div class="text-sm text-muted-foreground">Compras registradas</div>
              <div class="text-xl font-semibold">{{ int(m?.purchases_count ?? m?.purchases, 0) }}</div>
            </div>
            <div class="rounded-xl border p-4">
              <div class="text-sm text-muted-foreground">Vendas registradas</div>
              <div class="text-xl font-semibold">{{ int(m?.sales_count ?? m?.sales, 0) }}</div>
            </div>
          </template>
        </CardContent>
      </Card>

      <Card class="shadow-sm">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">Acesso rápido</CardTitle>
        </CardHeader>
        <Separator />
        <CardContent class="pt-4 grid gap-3">
          <Button class="w-full justify-start gap-2" variant="outline" @click="go('/produtos')">
            <Package class="w-4 h-4" /> Gerenciar produtos
          </Button>
          <Button class="w-full justify-start gap-2" variant="outline" @click="go('/compras')">
            <ShoppingCart class="w-4 h-4" /> Registrar compra
          </Button>
          <Button class="w-full justify-start gap-2" variant="outline" @click="go('/vendas')">
            <HandCoins class="w-4 h-4" /> Registrar venda
          </Button>
          <Button class="w-full justify-start gap-2" variant="outline" @click="go('/about')">
            <BarChart3 class="w-4 h-4" /> Sobre / ajuda
          </Button>
        </CardContent>
      </Card>
    </div>
  </div>
</template>