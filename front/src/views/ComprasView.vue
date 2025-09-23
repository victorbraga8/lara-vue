<script setup lang="ts">
import { computed, reactive, ref, watch, watchEffect } from 'vue'
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
type CompraRow = { id: number; fornecedor: string; itens?: any[]; produtos?: any[]; itens_count?: number; total?: number | string; created_at?: string }
type ItemCompraInput = { id: number; quantidade: number; preco_unitario: number }
type NovaCompra = { fornecedor: string; produtos: ItemCompraInput[] }

const qc = useQueryClient()
const open = ref(false)
const openDetail = ref(false)
const selectedId = ref<number | null>(null)

const comprasQ = useQuery<CompraRow[]>({
  queryKey: ['compras', 'list'],
  queryFn: () => listarCompras(),
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
  try { await comprasQ.refetch() } finally { refreshing.value = false }
}

const showSkeleton = computed(() => comprasQ.isPending.value || refreshing.value || (comprasQ.isFetching.value && compras.value.length === 0))

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
watchEffect(() => { if (!openDetail.value) selectedId.value = null })

const form = reactive<NovaCompra>({ fornecedor: '', produtos: [] })

type ItemErr = { id?: string; quantidade?: string; preco_unitario?: string }
const formErrors = reactive<{ fornecedor?: string; itens: ItemErr[]; general?: string }>({ itens: [] })

function ensureErrIndex(i: number) {
  while (formErrors.itens.length <= i) formErrors.itens.push({})
}
function clearFieldError(field: 'fornecedor'): void
function clearFieldError(i: number, field: keyof ItemErr): void
function clearFieldError(a: any, b?: any) {
  if (typeof a === 'number') {
    ensureErrIndex(a)
    if (formErrors.itens[a][b as keyof ItemErr]) delete formErrors.itens[a][b as keyof ItemErr]
  } else if (a === 'fornecedor' && formErrors.fornecedor) {
    delete formErrors.fornecedor
  }
  if (!formErrors.fornecedor && formErrors.itens.every(x => !x.id && !x.quantidade && !x.preco_unitario)) {
    delete formErrors.general
  }
}

function addItem() {
  form.produtos.push({ id: produtos.value[0]?.id ?? 0, quantidade: 1, preco_unitario: 0 })
  formErrors.itens.push({})
}
function removeItem(i: number) {
  form.produtos.splice(i, 1)
  formErrors.itens.splice(i, 1)
}

const totalForm = computed(() => form.produtos.reduce((acc, it) => acc + Number(it.quantidade || 0) * Number(it.preco_unitario || 0), 0))

const { mutateAsync: createMutationRun, isPending: createPending, reset: resetCreateMutation } = useMutation({
  mutationFn: (payload: NovaCompra) => criarCompra(payload),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Compra registrada', { position: 'top-center' })
    open.value = false
    form.fornecedor = ''
    form.produtos = []
    formErrors.itens = []
    delete formErrors.fornecedor
    delete formErrors.general
    resetCreateMutation()
    await qc.invalidateQueries({ queryKey: ['compras', 'list'] })
    await qc.invalidateQueries({ queryKey: ['produtos'] })
  },
  onError: (e: any) => {
    formErrors.general = e?.response?.data?.message || 'Erro ao registrar compra'
    resetCreateMutation()
  },
})

watch(open, (isOpen) => {
  if (!isOpen) {
    resetCreateMutation()
  }
})

function validateLocal(): boolean {
  formErrors.itens = []
  delete formErrors.fornecedor
  delete formErrors.general
  if (!form.fornecedor?.trim()) formErrors.fornecedor = 'Informe o fornecedor'
  form.produtos.forEach((it, i) => {
    const row: ItemErr = {}
    if (!it.id) row.id = 'Selecione um produto'
    if (Number(it.quantidade) <= 0) row.quantidade = 'Quantidade deve ser maior que 0'
    if (Number(it.preco_unitario) <= 0) row.preco_unitario = 'Preço unitário deve ser maior que 0'
    formErrors.itens[i] = row
  })
  const hasRowErr = formErrors.itens.some(r => r.id || r.quantidade || r.preco_unitario)
  if (formErrors.fornecedor || hasRowErr) {
    formErrors.general = 'Corrija os campos destacados para continuar'
    return false
  }
  return true
}

async function submit() {
  if (!validateLocal()) return
  await createMutationRun({
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

const resumo = computed(() => {
  const map = new Map<string, { fornecedor: string; total_compras: number; total_gasto: number }>()
  for (const c of compras.value) {
    const key = c.fornecedor || '—'
    const cur = map.get(key) ?? { fornecedor: key, total_compras: 0, total_gasto: 0 }
    cur.total_compras += 1
    const t = typeof c.total === 'string' ? Number(c.total) : (c.total ?? 0)
    cur.total_gasto += Number.isFinite(t) ? t : 0
    map.set(key, cur)
  }
  return Array.from(map.values())
})
const resumoPending = computed(() => comprasQ.isPending.value || comprasQ.isFetching.value || refreshing.value)

watch(() => form.fornecedor, () => clearFieldError('fornecedor'))
watch(() => form.produtos.map(p => p.id), (v) => v.forEach((_, i) => clearFieldError(i, 'id')))
watch(() => form.produtos.map(p => p.quantidade), (v) => v.forEach((_, i) => clearFieldError(i, 'quantidade')))
watch(() => form.produtos.map(p => p.preco_unitario), (v) => v.forEach((_, i) => clearFieldError(i, 'preco_unitario')))
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-semibold">Compras</h1>

      <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
        <Button variant="outline" size="sm" class="gap-2 justify-center" @click="refreshList" :disabled="refreshing">
          <Loader2 v-if="refreshing" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          <span>Atualizar</span>
        </Button>

        <Dialog v-model:open="open">
          <DialogTrigger as-child>
            <Button class="gap-2 justify-center">
              <Plus class="h-4 w-4" /> Registrar compra
            </Button>
          </DialogTrigger>

          <DialogContent class="w-[94vw] max-w-[94vw] sm:w-[640px] sm:max-w-[640px] md:w-[720px] md:max-w-[720px]">
            <DialogHeader>
              <DialogTitle>Registrar compra</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="fornecedor">Fornecedor</Label>
                <Input id="fornecedor" v-model="form.fornecedor" placeholder="Ex.: Fornecedor X" :aria-invalid="!!formErrors.fornecedor" />
                <p v-if="formErrors.fornecedor" class="text-sm text-destructive">{{ formErrors.fornecedor }}</p>
              </div>

              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Itens</span>
                  <Button variant="outline" size="sm" @click="addItem">Adicionar item</Button>
                </div>

                <div class="space-y-3">
                  <div v-for="(it, i) in form.produtos" :key="i" class="grid grid-cols-1 sm:grid-cols-12 gap-3 border rounded-lg p-3">
                    <div class="sm:col-span-6">
                      <Label>Produto</Label>
                      <select v-model="(it.id as any)" class="mt-2 block w-full rounded-md border bg-background px-3 py-2 text-sm focus:outline-none" :aria-invalid="!!formErrors.itens[i]?.id">
                        <option v-if="!produtos.length" :value="0" disabled>Carregando produtos…</option>
                        <option v-for="p in produtos" :key="p.id" :value="p.id">{{ p.nome }}</option>
                      </select>
                      <p v-if="formErrors.itens[i]?.id" class="text-sm text-destructive mt-1">{{ formErrors.itens[i]?.id }}</p>
                    </div>

                    <div class="sm:col-span-3">
                      <Label>Qtd</Label>
                      <Input class="mt-2" type="number" min="1" v-model="(it.quantidade as any)" :aria-invalid="!!formErrors.itens[i]?.quantidade" />
                      <p v-if="formErrors.itens[i]?.quantidade" class="text-sm text-destructive mt-1">{{ formErrors.itens[i]?.quantidade }}</p>
                    </div>

                    <div class="sm:col-span-3">
                      <Label>Preço unitário</Label>
                      <Input class="mt-2" type="number" step="0.01" min="0" v-model="(it.preco_unitario as any)" :aria-invalid="!!formErrors.itens[i]?.preco_unitario" />
                      <p v-if="formErrors.itens[i]?.preco_unitario" class="text-sm text-destructive mt-1">{{ formErrors.itens[i]?.preco_unitario }}</p>
                    </div>

                    <div class="sm:col-span-12 flex justify-end">
                      <Button variant="destructive" size="icon" class="mt-1 sm:mt-6" @click="removeItem(i)">
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

            <div v-if="formErrors.general" class="rounded-md border border-destructive/50 bg-destructive/10 text-destructive px-3 py-2 text-sm">
              {{ formErrors.general }}
            </div>

            <DialogFooter class="mt-2">
              <Button :disabled="createPending" class="gap-2" @click="submit">
                <Loader2 v-if="createPending" class="h-4 w-4 animate-spin" />
                <span v-else>Salvar</span>
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>

    <Card>
      <CardHeader class="pb-2">
        <CardTitle class="text-base">Resumo — Compras por fornecedor</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="sm:hidden grid gap-3">
          <template v-if="resumoPending">
            <div v-for="i in 3" :key="'sk-r-'+i" class="rounded-lg border p-3">
              <Skeleton class="h-4 w-40 mb-2" />
              <Skeleton class="h-4 w-24" />
            </div>
          </template>
          <div v-else-if="!resumo.length" class="text-center text-muted-foreground py-4">Nenhum fornecedor encontrado.</div>
          <div v-else v-for="r in resumo" :key="'rm-'+r.fornecedor" class="rounded-lg border p-3">
            <div class="font-medium">{{ r.fornecedor }}</div>
            <div class="mt-1 text-sm grid grid-cols-2">
              <span class="text-muted-foreground">Qtde compras</span><span>{{ r.total_compras }}</span>
              <span class="text-muted-foreground">Total gasto</span><span>{{ money(r.total_gasto) }}</span>
            </div>
          </div>
        </div>

        <div class="hidden sm:block overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fornecedor</TableHead>
                <TableHead class="whitespace-nowrap">Qtde compras</TableHead>
                <TableHead class="whitespace-nowrap">Total gasto</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-if="resumoPending">
                <TableRow v-for="i in 3" :key="'sk-r-'+i">
                  <TableCell><Skeleton class="h-4 w-40" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                </TableRow>
              </template>
              <TableRow v-else-if="!resumo.length">
                <TableCell colspan="3" class="text-center text-muted-foreground">Nenhum fornecedor encontrado.</TableCell>
              </TableRow>
              <TableRow v-else v-for="r in resumo" :key="r.fornecedor">
                <TableCell class="font-medium">{{ r.fornecedor }}</TableCell>
                <TableCell>{{ r.total_compras }}</TableCell>
                <TableCell>{{ money(r.total_gasto) }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

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
                    <Button size="sm" variant="outline" @click="comprasQ.refetch()">Tentar novamente</Button>
                  </div>
                </TableCell>
              </TableRow>

              <TableRow v-else-if="!compras.length">
                <TableCell colspan="6" class="text-center text-muted-foreground">Nenhuma compra registrada.</TableCell>
              </TableRow>

              <template v-else>
                <TableRow v-for="c in compras" :key="c.id">
                  <TableCell>{{ c.id }}</TableCell>
                  <TableCell class="font-medium">{{ c.fornecedor }}</TableCell>
                  <TableCell>{{ money(c.total) }}</TableCell>
                  <TableCell>{{ (c.created_at || '').toString().replace('T', ' ').slice(0, 19) }}</TableCell>
                  <TableCell class="flex gap-2">
                    <Button size="sm" variant="outline" class="gap-1" @click="openDetalhe(c.id)">
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
      <DialogContent class="w-[94vw] max-w-[94vw] sm:w-[640px] sm:max-w-[640px] md:w-[720px] md:max-w-[720px]">
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
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
