<script setup lang="ts">
import { computed, reactive, ref, watchEffect } from 'vue'
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
type VendaRow = {
  id: number
  cliente: string
  produtos?: any[]
  itens?: any[]
  total?: number | string
  lucro?: number | string
  created_at?: string
  data?: string
  status?: string
}
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
  retry: 1,
  select: (res: any) => {
    const body = res ?? {}
    if (Array.isArray(body)) return body
    if (Array.isArray(body.data)) return body.data
    return []
  },
})

watchEffect(() => {
  if (vendasQ.isError.value && (!vendasQ.data?.value || vendasQ.data?.value.length === 0)) {
    toast.error((vendasQ.error.value as any)?.message || 'Falha ao carregar vendas', { position: 'top-center' })
  }
})
const vendas = computed(() => vendasQ.data?.value ?? [])

const showSkeleton = computed(
  () => vendasQ.isPending.value || (vendasQ.isFetching.value && vendas.value.length === 0)
)

const produtosQ = useQuery<ProdutoOption[]>({
  queryKey: ['produtos', 'options'],
  queryFn: listarProdutos,
  retry: 1,
  select: (res: any) => {
    const body = res ?? {}
    if (Array.isArray(body)) return body
    if (Array.isArray(body.data)) return body.data
    return []
  },
})
const produtos = computed(() => produtosQ.data?.value ?? [])

const vendaDetalheQ = useQuery<any>({
  queryKey: computed(() => ['vendas', 'byId', selectedId.value]),
  queryFn: () => obterVenda(selectedId.value as number),
  enabled: computed(() => selectedId.value !== null),
  retry: 1,
})

watchEffect(() => {
  if (vendaDetalheQ.isError.value) {
    toast.error((vendaDetalheQ.error.value as any)?.message || 'Falha ao carregar venda', { position: 'top-center' })
  }
})

watchEffect(() => {
  if (!openDetail.value) selectedId.value = null
})

const form = reactive<NovaVenda>({ cliente: '', produtos: [] })

function addItem() {
  form.produtos.push({ id: produtos.value[0]?.id ?? 0, quantidade: 1, preco_unitario: 0 })
}
function removeItem(i: number) {
  form.produtos.splice(i, 1)
}

const total = computed(() =>
  form.produtos.reduce((acc, it) => acc + Number(it.quantidade || 0) * Number(it.preco_unitario || 0), 0)
)

const createMutation = useMutation({
  mutationFn: (payload: NovaVenda) => criarVenda(payload),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Venda registrada', { position: 'top-center' })
    open.value = false
    form.cliente = ''
    form.produtos = []
    await qc.invalidateQueries({ queryKey: ['vendas', 'list'] })
    await qc.invalidateQueries({ queryKey: ['produtos'] })
  },
  onError: (e: any) => {
    toast.error(e?.response?.data?.message || 'Erro ao registrar venda', { position: 'top-center' })
  },
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
  },
  onError: (e: any) => {
    toast.error(e?.response?.data?.message || 'Erro ao cancelar venda', { position: 'top-center' })
  },
})

async function submit() {
  if (!form.cliente?.trim()) return toast.warning('Informe o cliente', { position: 'top-center' })
  if (!form.produtos.length) return toast.warning('Adicione ao menos 1 item', { position: 'top-center' })
  for (const [idx, it] of form.produtos.entries()) {
    if (!it.id) return toast.warning(`Selecione o produto da linha ${idx + 1}`, { position: 'top-center' })
    if (Number(it.quantidade) <= 0) return toast.warning(`Quantidade inválida na linha ${idx + 1}`, { position: 'top-center' })
    if (Number(it.preco_unitario) <= 0) return toast.warning(`Preço unitário inválido na linha ${idx + 1}`, { position: 'top-center' })
  }
  await createMutation.mutateAsync({
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
function confirmarCancelar(id: number) {
  if (confirm('Confirma cancelar esta venda?')) cancelMutation.mutate(id)
}

function money(n: number | string | undefined) {
  const v = typeof n === 'string' ? Number(n) : (n ?? 0)
  return Number.isFinite(v) ? v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Vendas</h1>

      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="gap-2" @click="vendasQ.refetch()" :disabled="vendasQ.isFetching">
          <Loader2 v-if="vendasQ.isFetching" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          Atualizar
        </Button>

        <Dialog v-model:open="open">
          <DialogTrigger as-child>
            <Button class="gap-2"><Plus class="h-4 w-4" /> Registrar venda</Button>
          </DialogTrigger>

          <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
              <DialogTitle>Registrar venda</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="cliente">Cliente</Label>
                <Input id="cliente" v-model="form.cliente" placeholder="Ex.: Fulano da Silva" />
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
                          {{ p.nome }} ({{ money(p.preco_venda) }})
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
                      <Button variant="destructive" size="icon" class="mt-6" @click="removeItem(i)">
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

            <DialogFooter>
              <Button :disabled="createMutation.isPending" class="gap-2" @click="submit">
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
        <CardTitle class="text-base">Lista de vendas</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">ID</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead class="whitespace-nowrap">Itens</TableHead>
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
                  <TableCell><Skeleton class="h-4 w-40" /></TableCell>
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
                  Nenhuma venda registrada.
                </TableCell>
              </TableRow>

              <template v-else>
                <TableRow v-for="v in vendas" :key="v.id">
                  <TableCell>{{ v.id }}</TableCell>
                  <TableCell class="font-medium">{{ v.cliente }}</TableCell>
                  <TableCell>{{ v.produtos?.length ?? v.itens?.length ?? 0 }}</TableCell>
                  <TableCell>{{ money(v.total) }}</TableCell>
                  <TableCell>{{ money((v as any).lucro ?? (v as any).profit) }}</TableCell>
                  <TableCell>{{ (v.created_at || v.data || '').toString().replace('T', ' ').slice(0, 19) }}</TableCell>
                  <TableCell class="flex gap-2">
                    <Button size="sm" variant="outline" class="gap-1" @click="openDetalhe(v.id)">
                      <Eye class="h-4 w-4" /> Detalhes
                    </Button>
                    <Button
                      size="sm"
                      variant="destructive"
                      class="gap-1"
                      :disabled="pendingCancelId === v.id || v.status === 'canceled'"
                      @click="confirmarCancelar(v.id)"
                    >
                      <Loader2 v-if="pendingCancelId === v.id" class="h-4 w-4 animate-spin" />
                      <XCircle v-else class="h-4 w-4" />
                      Cancelar
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
          <DialogTitle>Detalhes da venda {{ selectedId ?? '' }}</DialogTitle>
        </DialogHeader>

        <div v-if="vendaDetalheQ.isLoading || vendaDetalheQ.isFetching" class="space-y-3">
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
            <div><span class="text-muted-foreground">Cliente:</span> <span class="font-medium">{{ vendaDetalheQ.data?.value?.cliente }}</span></div>
            <div><span class="text-muted-foreground">Data:</span> <span class="font-medium">{{ (vendaDetalheQ.data?.value?.created_at || vendaDetalheQ.data?.value?.data || '').toString().replace('T', ' ').slice(0, 19) }}</span></div>
            <div><span class="text-muted-foreground">Total:</span> <span class="font-medium">{{ money(vendaDetalheQ.data?.value?.total) }}</span></div>
            <div><span class="text-muted-foreground">Lucro:</span> <span class="font-medium">{{ money(vendaDetalheQ.data?.value?.lucro ?? vendaDetalheQ.data?.value?.profit) }}</span></div>
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
                <TableRow v-for="(it, i) in (vendaDetalheQ.data?.value?.itens ?? vendaDetalheQ.data?.value?.produtos ?? [])" :key="i">
                  <TableCell>{{ it.produto_id ?? it.id }}</TableCell>
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
  </div>
</template>
