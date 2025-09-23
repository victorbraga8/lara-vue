<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { listarProdutos, criarProduto, type Produto, type NovoProduto } from '@/services/produtos'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'

import { Loader2, RefreshCw, Plus } from 'lucide-vue-next'

const open = ref(false)
const qc = useQueryClient()

// ------------------------------
// LISTA (persistência via cache)
// ------------------------------
const {
  data,
  isLoading,
  isFetching,
  refetch,
  error,
} = useQuery<Produto[]>({
  queryKey: ['produtos', 'list'],
  queryFn: listarProdutos,
})

if (error.value) {
  toast.error(error.value?.message || 'Falha ao carregar produtos', { position: 'top-center' })
}

const produtos = computed<Produto[]>(() => data?.value ?? [])

// ------------------------------
// FORM & MUTATION (create)
// ------------------------------
const form = ref<NovoProduto>({
  nome: '',
  preco_venda: 0,
  custo_medio: 0,
  estoque: undefined,
})

const resetForm = () => {
  form.value = { nome: '', preco_venda: 0, custo_medio: 0, estoque: undefined }
}

const { mutateAsync: createProduct, isPending } = useMutation({
  mutationFn: (payload: NovoProduto) => criarProduto(payload),
  onSuccess: async (res: any) => {
    toast.success(res?.message || 'Produto cadastrado', { position: 'top-center' })
    open.value = false
    resetForm()
    // mantém a lista em cache e atualiza
    await qc.invalidateQueries({ queryKey: ['produtos', 'list'] })
  },
  onError: (e: any) => {
    const msg = e?.response?.data?.message || 'Erro ao cadastrar produto'
    const errs = e?.response?.data?.errors
    toast.error(Array.isArray(errs?.nome) ? `${msg}: ${errs.nome[0]}` : msg, { position: 'top-center' })
  },
})

async function submit() {
  // validações simples antes de enviar
  const nome = form.value.nome?.trim()
  if (!nome || nome.length < 3) {
    return toast.warning('Nome deve ter ao menos 3 caracteres', { position: 'top-center' })
  }
  if (Number(form.value.preco_venda) <= 0) {
    return toast.warning('Preço de venda deve ser > 0', { position: 'top-center' })
  }
  if (Number(form.value.custo_medio) < 0) {
    return toast.warning('Custo médio deve ser ≥ 0', { position: 'top-center' })
  }

  const payload: NovoProduto = {
    nome,
    preco_venda: Number(form.value.preco_venda),
    custo_medio: Number(form.value.custo_medio),
    ...(form.value.estoque !== undefined &&
      form.value.estoque !== null &&
      String(form.value.estoque) !== ''
      ? { estoque: Number(form.value.estoque) }
      : {}),
  }

  await createProduct(payload)
}

function money(n: number | string) {
  const v = typeof n === 'string' ? Number(n) : n
  return Number.isFinite(v) ? v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Produtos</h1>

      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="gap-2" @click="refetch()" :disabled="isFetching">
          <Loader2 v-if="isFetching" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          Atualizar
        </Button>

        <Dialog v-model:open="open">
          <DialogTrigger as-child>
            <Button class="gap-2"><Plus class="h-4 w-4" /> Novo produto</Button>
          </DialogTrigger>
          <DialogContent class="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Cadastrar produto</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="nome">Nome</Label>
                <Input id="nome" v-model="form.nome" placeholder="Ex.: Camiseta Básica" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                  <Label for="preco_venda">Preço de venda</Label>
                  <Input
                    id="preco_venda"
                    type="number"
                    step="0.01"
                    v-model="(form.preco_venda as any)"
                  />
                </div>
                <div class="grid gap-2">
                  <Label for="custo_medio">Custo médio</Label>
                  <Input
                    id="custo_medio"
                    type="number"
                    step="0.01"
                    v-model="(form.custo_medio as any)"
                  />
                </div>
              </div>
              <div class="grid gap-2">
                <Label for="estoque">Estoque (opcional)</Label>
                <Input
                  id="estoque"
                  type="number"
                  v-model="(form.estoque as any)"
                  placeholder="Se vazio, API usa 1"
                />
              </div>
            </div>

            <DialogFooter>
              <Button :disabled="isPending" @click="submit" class="gap-2">
                <Loader2 v-if="isPending" class="h-4 w-4 animate-spin" />
                <span v-else>Salvar</span>
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">Lista de produtos</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">ID</TableHead>
                <TableHead>Nome</TableHead>
                <TableHead class="whitespace-nowrap">Custo médio</TableHead>
                <TableHead class="whitespace-nowrap">Preço venda</TableHead>
                <TableHead>Estoque</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <!-- Skeletons no load inicial e no refetch -->
              <template v-if="isLoading || isFetching">
                <TableRow v-for="i in 6" :key="'skel-'+i">
                  <TableCell><Skeleton class="h-4 w-10" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-40" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton class="h-4 w-12" /></TableCell>
                </TableRow>
              </template>

              <TableRow v-else-if="!produtos.length">
                <TableCell colspan="5" class="text-center text-muted-foreground">
                  Nenhum produto.
                </TableCell>
              </TableRow>

              <TableRow v-else v-for="p in produtos" :key="p.id">
                <TableCell>{{ p.id }}</TableCell>
                <TableCell class="font-medium">{{ p.nome }}</TableCell>
                <TableCell>{{ money(p.custo_medio) }}</TableCell>
                <TableCell>{{ money(p.preco_venda) }}</TableCell>
                <TableCell>{{ p.estoque }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
