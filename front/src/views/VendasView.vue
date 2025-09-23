<script setup lang="ts">
import { computed, reactive, ref, watch, watchEffect } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { listarVendas, criarVenda, obterVenda, cancelarVenda } from '@/services/vendas'
import { listarProdutos } from '@/services/produtos'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'
import { Loader2, RefreshCw, Plus, Trash2, Eye, XCircle } from 'lucide-vue-next'

type ProdutoOption = { id: number; nome: string; preco_venda: number | string }
type VendaRow = { id: number; cliente: string; produtos?: any[]; itens?: any[]; total?: number | string; lucro?: number | string; created_at?: string; data?: string; status?: string }
type ItemVendaInput = { id: number; quantidade: number; preco_unitario: number }
type NovaVenda = { cliente: string; produtos: ItemVendaInput[] }

const qc = useQueryClient()
const open = ref(false)
const openDetail = ref(false)
const selectedId = ref<number | null>(null)
const pendingCancelId = ref<number | null>(null)

const vendasQ = useQuery<VendaRow[]>({
  queryKey: ['vendas', 'list'],
  queryFn: listarVendas,
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
const vendas = computed(() => vendasQ.data?.value ?? [])

const refreshing = ref(false)
async function refreshList() {
  refreshing.value = true
  try { await vendasQ.refetch() } finally { refreshing.value = false }
}

const showSkeleton = computed(() => vendasQ.isPending.value || refreshing.value || (vendasQ.isFetching.value && vendas.value.length === 0))

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

const vendaDetalheQ = useQuery({
  queryKey: computed(() => ['vendas', 'byId', selectedId.value]),
  queryFn: () => obterVenda(selectedId.value as number),
  enabled: computed(() => openDetail.value && selectedId.value !== null),
  retry: 0,
  refetchOnWindowFocus: false,
  refetchOnReconnect: false,
  staleTime: 60_000,
})
const vendaDetalhe = computed<any>(() => vendaDetalheQ.data?.value ?? null)
const detalhePending = computed(() => vendaDetalheQ.status.value === 'pending')

watchEffect(() => {
  if (vendasQ.isError.value && (!vendasQ.data?.value || vendasQ.data?.value.length === 0)) {
    toast.error((vendasQ.error.value as any)?.message || 'Falha ao carregar vendas', { position: 'top-center' })
  }
})
watchEffect(() => {
  if (vendaDetalheQ.isError.value) {
    toast.error((vendaDetalheQ.error.value as any)?.message || 'Falha ao carregar venda', { position: 'top-center' })
  }
})
watchEffect(() => { if (!openDetail.value) selectedId.value = null })

const form = reactive<NovaVenda>({ cliente: '', produtos: [] })

type ItemErr = { id?: string; quantidade?: string; preco_unitario?: string }
const formErrors = reactive<{ cliente?: string; itens: ItemErr[]; general?: string }>({ itens: [] })

function ensureErrIndex(i: number) {
  while (formErrors.itens.length <= i) formErrors.itens.push({})
}
function clearFieldError(field: 'cliente'): void
function clearFieldError(i: number, field: keyof ItemErr): void
function clearFieldError(a: any, b?: any) {
  if (typeof a === 'number') {
    ensureErrIndex(a)
    if (formErrors.itens[a][b as keyof ItemErr]) delete formErrors.itens[a][b as keyof ItemErr]
  } else if (a === 'cliente' && formErrors.cliente) {
    delete formErrors.cliente
  }
  if (!formErrors.cliente && formErrors.itens.every(x => !x.id && !x.quantidade && !x.preco_unitario)) {
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

const total = computed(() => form.produtos.reduce((acc, it) => acc + Number(it.quantidade || 0) * Number(it.preco_unitario || 0), 0))

function produtoNome(id: number) {
  return produtos.value.find(p => Number(p.id) === Number(id))?.nome || `ID ${id}`
}

const { mutateAsync: createRun, isPending: createPending, reset: resetCreate } = useMutation({
  mutationFn: (payload: NovaVenda) => criarVenda(payload),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Venda registrada', { position: 'top-center' })
    open.value = false
    form.cliente = ''
    form.produtos = []
    formErrors.itens = []
    delete formErrors.cliente
    delete formErrors.general
    resetCreate()
    await qc.invalidateQueries({ queryKey: ['vendas', 'list'] })
    await qc.invalidateQueries({ queryKey: ['produtos'] })
  },
  onError: (e: any) => {
    const data = e?.response?.data
    const msgs: string[] = []
    if (data?.errors?.produtos && Array.isArray(data.errors.produtos)) {
      const regex = /produto\s+ID\s+(\d+).*?Dispon[ií]vel:\s*(\d+)/i
      for (const msg of data.errors.produtos as string[]) {
        const m = msg.match(regex)
        if (!m) continue
        const pid = Number(m[1])
        const disponivel = Number(m[2])
        form.produtos.forEach((row, i) => {
          if (Number(row.id) === pid) {
            ensureErrIndex(i)
            form.produtos[i].quantidade = disponivel
            formErrors.itens[i].quantidade = `Estoque insuficiente. Disponível: ${disponivel}. A quantidade foi ajustada para ${disponivel}.`
          }
        })
        msgs.push(`${produtoNome(pid)} → disponível ${disponivel}`)
      }
      formErrors.general = msgs.length
        ? `Alguns itens foram ajustados para a quantidade disponível: ${msgs.join('; ')}.`
        : (data?.message || 'Erro ao registrar venda')
    } else {
      formErrors.general = data?.message || 'Erro ao registrar venda'
    }
    resetCreate()
  },
})

watch(open, (isOpen) => {
  if (!isOpen) resetCreate()
})

const cancelMutation = useMutation({
  mutationFn: (id: number) => cancelarVenda(id),
  onMutate: (id) => (pendingCancelId.value = id),
  onSettled: () => (pendingCancelId.value = null),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Venda cancelada', { position: 'top-center' })
    await qc.invalidateQueries({ queryKey: ['vendas', 'list'] })
    await qc.invalidateQueries({ queryKey: ['produtos'] })
    if (selectedId.value) await qc.invalidateQueries({ queryKey: ['vendas', 'byId', selectedId.value] })
    confirmOpen.value = false
    vendaToCancel.value = null
  },
  onError: (e: any) => {
    toast.error(e?.response?.data?.message || 'Erro ao cancelar venda', { position: 'top-center' })
  },
})

function validateLocal(): boolean {
  formErrors.itens = []
  delete formErrors.cliente
  delete formErrors.general
  if (!form.cliente?.trim()) formErrors.cliente = 'Informe o cliente'
  form.produtos.forEach((it, i) => {
    const row: ItemErr = {}
    if (!it.id) row.id = 'Selecione um produto'
    if (Number(it.quantidade) <= 0) row.quantidade = 'Quantidade deve ser maior que 0'
    if (Number(it.preco_unitario) <= 0) row.preco_unitario = 'Preço unitário deve ser maior que 0'
    formErrors.itens[i] = row
  })
  const hasRowErr = formErrors.itens.some(r => r.id || r.quantidade || r.preco_unitario)
  if (formErrors.cliente || hasRowErr) {
    formErrors.general = 'Corrija os campos destacados para continuar'
    return false
  }
  return true
}

async function submit() {
  if (!validateLocal()) return
  await createRun({
    cliente: form.cliente.trim(),
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

const confirmOpen = ref(false)
const vendaToCancel = ref<{ id: number; cliente?: string } | null>(null)
function solicitarCancelamento(v: VendaRow) {
  vendaToCancel.value = { id: v.id, cliente: v.cliente }
  confirmOpen.value = true
}
function confirmarCancelar() {
  if (vendaToCancel.value) cancelMutation.mutate(vendaToCancel.value.id)
}

watch(() => form.cliente, () => clearFieldError('cliente'))
watch(() => form.produtos.map(p => p.id), (v) => v.forEach((_, i) => clearFieldError(i, 'id')))
watch(() => form.produtos.map(p => p.quantidade), (v) => v.forEach((_, i) => clearFieldError(i, 'quantidade')))
watch(() => form.produtos.map(p => p.preco_unitario), (v) => v.forEach((_, i) => clearFieldError(i, 'preco_unitario')))
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-semibold">Vendas</h1>

      <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
        <Button variant="outline" size="sm" class="gap-2 justify-center" @click="refreshList" :disabled="refreshing">
          <Loader2 v-if="refreshing" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          Atualizar
        </Button>

<Dialog v-model:open="open">
  <DialogTrigger as-child>
    <Button class="gap-2 justify-center"><Plus class="h-4 w-4" /> Registrar venda</Button>
  </DialogTrigger>

      <DialogContent
        class="w-[94vw] max-w-[94vw] sm:w-[640px] sm:max-w-[640px] md:w-[720px] md:max-w-[720px] p-0"
      >
        <div class="flex flex-col h-[90svh] max-h-[90svh] sm:max-h-[85vh]">
          <DialogHeader class="px-6 pt-6">
            <DialogTitle>Registrar venda</DialogTitle>
          </DialogHeader>

          <div class="px-6 pb-4 overflow-y-auto">
            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="cliente">Cliente</Label>
                <Input
                  id="cliente"
                  v-model="form.cliente"
                  placeholder="Ex.: Fulano da Silva"
                  :aria-invalid="!!formErrors.cliente"
                />
                <p v-if="formErrors.cliente" class="text-sm text-destructive">{{ formErrors.cliente }}</p>
              </div>

              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Itens</span>
                  <Button variant="outline" size="sm" @click="addItem">Adicionar item</Button>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="(it, i) in form.produtos"
                    :key="i"
                    class="grid grid-cols-1 sm:grid-cols-12 gap-3 border rounded-lg p-3"
                  >
                    <div class="sm:col-span-6">
                      <Label>Produto</Label>
                      <select
                        v-model="(it.id as any)"
                        class="mt-2 block w-full rounded-md border bg-background px-3 py-2 text-sm focus:outline-none"
                        :aria-invalid="!!formErrors.itens[i]?.id"
                      >
                        <option v-if="!produtos.length" :value="0" disabled>Carregando produtos…</option>
                        <option v-for="p in produtos" :key="p.id" :value="p.id">
                          {{ p.nome }} ({{ money(p.preco_venda) }})
                        </option>
                      </select>
                      <p v-if="formErrors.itens[i]?.id" class="text-sm text-destructive mt-1">
                        {{ formErrors.itens[i]?.id }}
                      </p>
                    </div>

                    <div class="sm:col-span-3">
                      <Label>Qtd</Label>
                      <Input
                        class="mt-2"
                        type="number"
                        min="0"
                        v-model="(it.quantidade as any)"
                        :aria-invalid="!!formErrors.itens[i]?.quantidade"
                      />
                      <p v-if="formErrors.itens[i]?.quantidade" class="text-sm text-destructive mt-1">
                        {{ formErrors.itens[i]?.quantidade }}
                      </p>
                    </div>

                    <div class="sm:col-span-3">
                      <Label>Preço unitário</Label>
                      <Input
                        class="mt-2"
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="(it.preco_unitario as any)"
                        :aria-invalid="!!formErrors.itens[i]?.preco_unitario"
                      />
                      <p v-if="formErrors.itens[i]?.preco_unitario" class="text-sm text-destructive mt-1">
                        {{ formErrors.itens[i]?.preco_unitario }}
                      </p>
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
                  <span class="text-lg font-semibold">{{ money(total) }}</span>
                </div>
              </div>
            </div>

            <div
              v-if="formErrors.general"
              class="rounded-md border border-destructive/50 bg-destructive/10 text-destructive px-3 py-2 text-sm"
            >
              {{ formErrors.general }}
            </div>
          </div>

          <DialogFooter class="mt-0 px-6 py-4 border-t bg-background">
            <Button :disabled="createPending" class="gap-2" @click="submit">
              <Loader2 v-if="createPending" class="h-4 w-4 animate-spin" />
              <span v-else>Salvar</span>
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>

      </div>
    </div>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">Lista de vendas</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">ID</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead class="whitespace-nowrap">Total</TableHead>
                <TableHead class="whitespace-nowrap">Lucro</TableHead>
                <TableHead class="whitespace-nowrap">Data</TableHead>
                <TableHead class="whitespace-nowrap">Ações</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <template v-if="showSkeleton">
                <TableRow v-for="i in 6" :key="'skel-'+i">
                  <TableCell><Skeleton class="h-4 w-10" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-10" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                  <TableCell class="flex gap-2"><Skeleton class="h-8 w-20" /><Skeleton class="h-8 w-24" /></TableCell>
                </TableRow>
              </template>

              <TableRow v-else-if="vendasQ.isError && !vendas.length">
                <TableCell colspan="7" class="text-center">
                  <div class="flex flex-col items-center gap-2 py-4">
                    <span class="text-sm text-muted-foreground">Não foi possível carregar as vendas.</span>
                    <Button size="sm" variant="outline" @click="vendasQ.refetch()">Tentar novamente</Button>
                  </div>
                </TableCell>
              </TableRow>

              <TableRow v-else-if="!vendas.length">
                <TableCell colspan="7" class="text-center text-muted-foreground">
                  Nenhuma venda registrado.
                </TableCell>
              </TableRow>

              <template v-else>
                <TableRow v-for="v in vendas" :key="v.id">
                  <TableCell>{{ v.id }}</TableCell>
                  <TableCell class="font-medium">{{ v.cliente }}</TableCell>
                  <TableCell>{{ money(v.total) }}</TableCell>
                  <TableCell>{{ money((v as any).lucro ?? (v as any).profit) }}</TableCell>
                  <TableCell>{{ (v.created_at || v.data || '').toString().replace('T', ' ').slice(0, 19) }}</TableCell>
                  <TableCell class="flex gap-2">
                    <Button size="sm" variant="outline" class="gap-1" @click="openDetalhe(v.id)">
                      <Eye class="h-4 w-4" /> Detalhes
                    </Button>

                    <Button v-if="v.status !== 'canceled'" size="sm" variant="destructive" class="gap-1" :disabled="pendingCancelId === v.id" @click="solicitarCancelamento(v)">
                      <Loader2 v-if="pendingCancelId === v.id" class="h-4 w-4 animate-spin" />
                      <XCircle v-else class="h-4 w-4" />
                      Cancelar
                    </Button>

                    <Button v-else size="sm" variant="outline" class="gap-1 text-destructive border-destructive/50 bg-destructive/10 hover:bg-destructive/10 hover:text-destructive cursor-not-allowed pointer-events-none">
                      <XCircle class="h-3.5 w-3.5" />
                      Cancelada
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
          <DialogTitle>Detalhes da venda {{ selectedId ?? '' }}</DialogTitle>
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
            <div><span class="text-muted-foreground">Cliente:</span> <span class="font-medium">{{ vendaDetalhe?.cliente }}</span></div>
            <div><span class="text-muted-foreground">Data:</span> <span class="font-medium">{{ (vendaDetalhe?.created_at || '').toString().replace('T', ' ').slice(0, 19) }}</span></div>
            <div><span class="text-muted-foreground">Total:</span> <span class="font-medium">{{ money(vendaDetalhe?.total) }}</span></div>
            <div><span class="text-muted-foreground">Lucro:</span> <span class="font-medium">{{ money(vendaDetalhe?.lucro) }}</span></div>
          </div>

          <div class="border rounded-lg overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="whitespace-nowrap">Produto</TableHead>
                  <TableHead>Qtd</TableHead>
                  <TableHead class="whitespace-nowrap">Preço unit.</TableHead>
                  <TableHead class="whitespace-nowrap">Subtotal</TableHead>
                  <TableHead class="whitespace-nowrap">Lucro item</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(it, i) in (vendaDetalhe?.itens ?? vendaDetalhe?.produtos ?? [])" :key="i">
                  <TableCell>{{ it.produto_id }}</TableCell>
                  <TableCell>{{ it.quantidade }}</TableCell>
                  <TableCell>{{ money(it.preco_unitario) }}</TableCell>
                  <TableCell>{{ money(it.subtotal) }}</TableCell>
                  <TableCell>{{ money(it.lucro_item) }}</TableCell>
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

    <Dialog v-model:open="confirmOpen">
      <DialogContent class="w-[92vw] max-w-[92vw] sm:w-[520px] sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>Cancelar venda {{ vendaToCancel?.id }}</DialogTitle>
        </DialogHeader>
        <div class="space-y-3 text-sm">
          <p>Tem certeza que deseja cancelar esta venda de <span class="font-medium">{{ vendaToCancel?.cliente }}</span>?</p>
          <p class="text-destructive">Esta ação é permanente e não pode ser revertida.</p>
        </div>
        <DialogFooter class="gap-2">
          <Button variant="outline" @click="confirmOpen = false">Voltar</Button>
          <Button variant="destructive" class="gap-2" :disabled="pendingCancelId === vendaToCancel?.id" @click="confirmarCancelar">
            <Loader2 v-if="pendingCancelId === vendaToCancel?.id" class="h-4 w-4 animate-spin" />
            <XCircle v-else class="h-4 w-4" />
            Cancelar venda
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
