<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getOverview, type MetricsOverview } from '@/services/metrics'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'

import { Package, ShoppingCart, HandCoins, BarChart3, Box, DollarSign, TrendingUp, AlertTriangle } from 'lucide-vue-next'

const router = useRouter()
const m = ref<MetricsOverview | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    m.value = await getOverview()
  } catch (e) {
    toast.error('Falha ao carregar métricas')
  } finally {
    loading.value = false
  }
})

function go(path: string) {
  router.push(path)
}

function money(v?: string | number) {
  const n = typeof v === 'string' ? Number(v) : (v ?? 0)
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
  <div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p class="text-sm text-muted-foreground">
          Visão geral do período atual {{ m?.month_label ? `(${m?.month_label})` : '' }}
        </p>
      </div>

      <!-- Ações principais -->
      <div class="flex flex-wrap gap-2">
        <Button class="gap-2" @click="go('/produtos')"><Package class="w-4 h-4" /> Produtos</Button>
        <Button variant="secondary" class="gap-2" @click="go('/compras')"><ShoppingCart class="w-4 h-4" /> Compras</Button>
        <Button variant="outline" class="gap-2" @click="go('/vendas')"><HandCoins class="w-4 h-4" /> Vendas</Button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="grid gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
    <Card>
      <CardHeader class="flex items-center justify-between pb-2 bg-accent/40 rounded-t-2xl">
        <CardTitle class="text-sm font-medium">Receita (mês)</CardTitle>
        <DollarSign class="h-4 w-4 text-primary" />
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ money(m?.revenue_month) }}</div>
        <p class="text-xs text-muted-foreground">Vendas concluídas no mês</p>
      </CardContent>
    </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium">Lucro (mês)</CardTitle>
          <TrendingUp class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ loading ? '...' : money(m?.profit_month) }}</div>
          <p class="text-xs text-muted-foreground">Com base no custo médio</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium">Produtos</CardTitle>
          <Box class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ loading ? '...' : m?.products_count }}</div>
          <p class="text-xs text-muted-foreground">Itens cadastrados</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium">Estoque crítico</CardTitle>
          <AlertTriangle class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ loading ? '...' : m?.low_stock }}</div>
          <p class="text-xs text-muted-foreground">≤ 5 unidades</p>
        </CardContent>
      </Card>
    </div>

    <!-- Cards secundários -->
    <div class="grid gap-4 md:gap-6 grid-cols-1 lg:grid-cols-3">
      <!-- Resumo acumulado -->
      <Card class="lg:col-span-2">
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Acumulado geral</CardTitle>
            <Badge variant="secondary" class="font-normal">desde o início</Badge>
          </div>
        </CardHeader>
        <Separator />
        <CardContent class="pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="rounded-xl border p-4">
            <div class="text-sm text-muted-foreground">Receita total</div>
            <div class="text-xl font-semibold">{{ loading ? '...' : money(m?.revenue_total) }}</div>
          </div>
          <div class="rounded-xl border p-4">
            <div class="text-sm text-muted-foreground">Lucro total</div>
            <div class="text-xl font-semibold">{{ loading ? '...' : money(m?.profit_total) }}</div>
          </div>

          <div class="rounded-xl border p-4">
            <div class="text-sm text-muted-foreground">Compras registradas</div>
            <div class="text-xl font-semibold">{{ loading ? '...' : m?.purchases_count }}</div>
          </div>
          <div class="rounded-xl border p-4">
            <div class="text-sm text-muted-foreground">Vendas registradas</div>
            <div class="text-xl font-semibold">{{ loading ? '...' : m?.sales_count }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
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
