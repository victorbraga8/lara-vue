<script setup lang="ts">
import { computed, reactive, ref, watchEffect } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { listarCompras, criarCompra, obterCompra } from '@/services/compras'
import { listarProdutos } from '@/services/produtos'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'
import { Loader2, RefreshCw, Plus, Trash2, Eye } from 'lucide-vue-next'

type ProdutoOption = { id: number; nome: string; preco_venda?: number | string }
type CompraRow = {
  id: number
  fornecedor: string
  itens?: any[]
  produtos?: any[]
  total?: number | string
  created_at?: string
}
type ItemCompraInput = { id: number; quantidade: number; preco_unitario: number }
type NovaCompra = { fornecedor: string; produtos: ItemCompraInput[] }

const qc = useQueryClient()
const open = ref(false)
const openDetail = ref(false)
const selectedId = ref<number | null>(null)

const comprasQ = useQuery<CompraRow[]>({
  queryKey: ['compras', 'list'],
  queryFn: listarCompras,
  retry: 0,
  refetchOnWindowFocus: false,
  refetchOnReconnect: false,
  staleTime: 60_000,
  select: (res: any) => {
    const body = res ?? {}
    if (Array.isArray(body)) return body
    if (Array.isArray(body.data)) return body.data
    return []
  },
})
const compras = computed(() => comprasQ.data?.value ?? [])

const refreshing = ref(false)
async function refreshList() {
  refreshing.value = true
  try {
    await comprasQ.refetch()
  } finally {
    refreshing.value = false
  }
}

const showSkeleton = computed(
  () =>
    comprasQ.isPending.value ||
    refreshing.value ||
    (comprasQ.isFetching.value && compras.value.length === 0)
)

const produtosQ = useQuery<ProdutoOption[]>({
  queryKey: ['produtos', 'options'],
  queryFn: listarProdutos,
  retry: 0,
  refetchOnWindowFocus: false,
  refetchOnReconnect: false,
  staleTime: 60_000,
  select: (res: any) => {
    const body = res ?? {}
    if (Array.isArray(body)) return body
    if (Array.isArray(body.data)) return body.data
    return []
  },
})
const produtos = computed(() => produtosQ.data?.value ?? [])

const compraDetalheQ = useQuery({
  queryKey: computed(() => ['compras', 'byId', selectedId.value]),
  queryFn: () => obterCompra(selectedId.value as number),
  enabled: computed(() => openDetail.value && selectedId.value !== null),
  retry: 0,
  refetchOnWindowFocus: false,
  refetchOnReconnect: false,
  staleTime: 60_000,
})
const compraDetalhe = computed<any>(() => compraDetalheQ.data?.value ?? null)
const detalhePending = computed(() => compraDetalheQ.status.value === 'pending')

watchEffect(() => {
  if (comprasQ.isError.value && (!comprasQ.data?.value || comprasQ.data?.value.length === 0)) {
    toast.error((comprasQ.error.value as any)?.message || 'Falha ao carregar compras', { position: 'top-center' })
  }
})
watchEffect(() => {
  if (compraDetalheQ.isError.value) {
    toast.error((compraDetalheQ.error.value as any)?.message || 'Falha ao carregar compra', { position: 'top-center' })
  }
})
watchEffect(() => {
  if (!openDetail.value) selectedId.value = null
})

const form = reactive<NovaCompra>({ fornecedor: '', produtos: [] })

function addItem() {
  form.produtos.push({ id: produtos.value[0]?.id ?? 0, quantidade: 1, preco_unitario: 0 })
}
function removeItem(i: number) {
  form.produtos.splice(i, 1)
}

const totalForm = computed(() =>
  form.produtos.reduce((acc, it) => acc + Number(it.quantidade || 0) * Number(it.preco_unitario || 0), 0)
)

const createMutation = useMutation({
  mutationFn: (payload: NovaCompra) => criarCompra(payload),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Compra registrada', { position: 'top-center' })
    open.value = false
    form.fornecedor = ''
    form.produtos = []
    await qc.invalidateQueries({ queryKey: ['compras', 'list'] })
    await qc.invalidateQueries({ queryKey: ['produtos'] })
  },
  onError: (e: any) => {
    toast.error(e?.response?.data?.message || 'Erro ao registrar compra', { position: 'top-center' })
  },
})

async function submit() {
  if (!form.fornecedor?.trim()) return toast.warning('Informe o fornecedor', { position: 'top-center' })
  if (!form.produtos.length) return toast.warning('Adicione ao menos 1 item', { position: 'top-center' })
  for (const [idx, it] of form.produtos.entries()) {
    if (!it.id) return toast.warning(`Selecione o produto da linha ${idx + 1}`, { position: 'top-center' })
    if (Number(it.quantidade) <= 0) return toast.warning(`Quantidade inválida na linha ${idx + 1}`, { position: 'top-center' })
    if (Number(it.preco_unitario) <= 0) return toast.warning(`Preço unitário inválido na linha ${idx + 1}`, { position: 'top-center' })
  }
  await createMutation.mutateAsync({
    fornecedor: form.fornecedor.trim(),
    produtos: form.produtos.map(p => ({
      id: Number(p.id),
      quantidade: Number(p.quantidade),
      preco_unitario: Number(p.preco_unitario),
    })),
  })
}

function openDetalhe(id: number) {
  selectedId.value = id
  openDetail.value = true
}

function money(n: number | string | undefined) {
  const v = typeof n === 'string' ? Number(n) : (n ?? 0)
  return Number.isFinite(v) ? v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Compras</h1>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="gap-2 transition-colors hover:bg-accent hover:text-accent-foreground"
          @click="refreshList"
          :disabled="refreshing"
        >
          <Loader2 v-if="refreshing" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          Atualizar
        </Button>

        <Dialog v-model:open="open">
          <DialogTrigger as-child>
            <Button class="gap-2 transition-colors hover:opacity-90"><Plus class="h-4 w-4" /> Registrar compra</Button>
          </DialogTrigger>

          <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
              <DialogTitle>Registrar compra</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="fornecedor">Fornecedor</Label>
                <Input id="fornecedor" v-model="form.fornecedor" placeholder="Ex.: Fornecedor X" />
              </div>

              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Itens</span>
                  <Button variant="outline" size="sm" class="transition-colors hover:bg-accent hover:text-accent-foreground" @click="addItem">Adicionar item</Button>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="(it, i) in form.produtos"
                    :key="i"
                    class="grid grid-cols-12 gap-3 items-end border rounded-lg p-3"
                  >
                    <div class="col-span-6 sm:col-span-5">
                      <Label>Produto</Label>
                      <select
                        v-model="(it.id as any)"
                        class="mt-2 block w-full rounded-md border bg-background px-3 py-2 text-sm focus:outline-none"
                      >
                        <option v-if="!produtos.length" :value="0" disabled>Carregando produtos…</option>
                        <option v-for="p in produtos" :key="p.id" :value="p.id">
                          {{ p.nome }}
                        </option>
                      </select>
                    </div>

                    <div class="col-span-3 sm:col-span-3">
                      <Label>Qtd</Label>
                      <Input class="mt-2" type="number" min="1" v-model="(it.quantidade as any)" />
                    </div>

                    <div class="col-span-3 sm:col-span-3">
                      <Label>Preço unitário</Label>
                      <Input class="mt-2" type="number" step="0.01" min="0" v-model="(it.preco_unitario as any)" />
                    </div>

                    <div class="col-span-12 sm:col-span-1 flex justify-end">
                      <Button variant="destructive" size="icon" class="mt-6 transition-opacity hover:opacity-90" @click="removeItem(i)">
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                  <span class="text-sm text-muted-foreground">Total</span>
                  <span class="text-lg font-semibold">{{ money(totalForm) }}</span>
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button :disabled="createMutation.isPending" class="gap-2 transition-opacity hover:opacity-90" @click="submit">
                <Loader2 v-if="createMutation.isPending" class="h-4 w-4 animate-spin" />
                <span v-else>Salvar</span>
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">Lista de compras</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">ID</TableHead>
                <TableHead>Fornecedor</TableHead>
                <TableHead class="whitespace-nowrap">Itens</TableHead>
                <TableHead class="whitespace-nowrap">Total</TableHead>
                <TableHead class="whitespace-nowrap">Data</TableHead>
                <TableHead class="whitespace-nowrap">Ações</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <template v-if="showSkeleton">
                <TableRow v-for="i in 6" :key="'skel-'+i">
                  <TableCell><Skeleton class="h-4 w-10" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-40" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-10" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                  <TableCell class="flex gap-2"><Skeleton class="h-8 w-24" /></TableCell>
                </TableRow>
              </template>

              <TableRow v-else-if="comprasQ.isError && !compras.length">
                <TableCell colspan="6" class="text-center">
                  <div class="flex flex-col items-center gap-2 py-4">
                    <span class="text-sm text-muted-foreground">Não foi possível carregar as compras.</span>
                    <Button size="sm" variant="outline" class="transition-colors hover:bg-accent hover:text-accent-foreground" @click="comprasQ.refetch()">Tentar novamente</Button>
                  </div>
                </TableCell>
              </TableRow>

              <TableRow v-else-if="!compras.length">
                <TableCell colspan="6" class="text-center text-muted-foreground">
                  Nenhuma compra registrada.
                </TableCell>
              </TableRow>

              <template v-else>
                <TableRow v-for="c in compras" :key="c.id">
                  <TableCell>{{ c.id }}</TableCell>
                  <TableCell class="font-medium">{{ c.fornecedor }}</TableCell>
                  <TableCell>{{ c.itens?.length ?? c.produtos?.length ?? 0 }}</TableCell>
                  <TableCell>{{ money(c.total) }}</TableCell>
                  <TableCell>{{ (c.created_at || '').toString().replace('T', ' ').slice(0, 19) }}</TableCell>
                  <TableCell class="flex gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      class="gap-1 transition-colors hover:bg-accent hover:text-accent-foreground"
                      @click="openDetalhe(c.id)"
                    >
                      <Eye class="h-4 w-4" /> Detalhes
                    </Button>
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <Dialog v-model:open="openDetail">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Detalhes da compra {{ selectedId ?? '' }}</DialogTitle>
        </DialogHeader>

        <div v-if="detalhePending" class="space-y-3">
          <Skeleton class="h-4 w-56" />
          <Skeleton class="h-4 w-40" />
          <div class="grid gap-2">
            <Skeleton class="h-8 w-full" />
            <Skeleton class="h-8 w-full" />
            <Skeleton class="h-8 w-full" />
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-muted-foreground">Fornecedor:</span> <span class="font-medium">{{ compraDetalhe?.fornecedor }}</span></div>
            <div><span class="text-muted-foreground">Data:</span> <span class="font-medium">{{ (compraDetalhe?.created_at || '').toString().replace('T', ' ').slice(0, 19) }}</span></div>
            <div><span class="text-muted-foreground">Total:</span> <span class="font-medium">{{ money(compraDetalhe?.total) }}</span></div>
          </div>

          <div class="border rounded-lg overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="whitespace-nowrap">Produto</TableHead>
                  <TableHead>Qtd</TableHead>
                  <TableHead class="whitespace-nowrap">Preço unit.</TableHead>
                  <TableHead class="whitespace-nowrap">Subtotal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(it, i) in (compraDetalhe?.itens ?? compraDetalhe?.produtos ?? [])" :key="i">
                  <TableCell>{{ it.produto_id }}</TableCell>
                  <TableCell>{{ it.quantidade }}</TableCell>
                  <TableCell>{{ money(it.preco_unitario) }}</TableCell>
                  <TableCell>{{ money(it.subtotal) }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="openDetail = false">Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
