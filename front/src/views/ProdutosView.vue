<script setup lang="ts">
import { computed, ref, watch, h } from 'vue'
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

function showToast(type: 'success' | 'error', message: string) {
  toast.custom(
    () =>
      h(
        'div',
        { class: 'mx-auto w-[92vw] max-w-[520px] rounded-xl border bg-background/95 backdrop-blur px-4 py-3 shadow-2xl ring-1 ring-black/5' },
        [h('p', { class: `text-center text-sm font-medium ${type === 'success' ? 'text-emerald-600' : 'text-red-600'}` }, message)],
      ),
    { position: 'top-center', duration: 2800 },
  )
}

async function invalidateAllProducts() {
  await Promise.all([
    qc.invalidateQueries({ queryKey: ['produtos'] }),
    qc.invalidateQueries({ queryKey: ['produtos', 'list'] }),
    qc.invalidateQueries({ queryKey: ['produtos', 'options'] }),
  ])
}

const { data, isLoading, isFetching, refetch, error } = useQuery<Produto[]>({
  queryKey: ['produtos', 'list'],
  queryFn: listarProdutos,
})

if (error.value) {
  showToast('error', error.value?.message || 'Falha ao carregar produtos')
}

const produtos = computed<Produto[]>(() => data?.value ?? [])

const form = ref<NovoProduto>({
  nome: '',
  preco_venda: 0,
  custo_medio: 0,
  estoque: undefined,
})

const formErrors = ref<Record<string, string>>({})
const generalError = ref<string>('')

function resetForm() {
  form.value = { nome: '', preco_venda: 0, custo_medio: 0, estoque: undefined }
  formErrors.value = {}
  generalError.value = ''
}

function clearError(field: keyof NovoProduto) {
  if (formErrors.value[field]) {
    delete formErrors.value[field]
  }
  if (!Object.keys(formErrors.value).length) generalError.value = ''
}

watch(() => form.value.nome, () => clearError('nome'))
watch(() => form.value.preco_venda, () => clearError('preco_venda'))
watch(() => form.value.custo_medio, () => clearError('custo_medio'))
watch(() => form.value.estoque, () => clearError('estoque'))

const { mutateAsync: createProduct, isPending } = useMutation({
  mutationFn: (payload: NovoProduto) => criarProduto(payload),
  onSuccess: async (res: any) => {
    showToast('success', res?.message || 'Produto cadastrado')
    open.value = false
    resetForm()
    await invalidateAllProducts()
  },
  onError: (e: any) => {
    const msg = e?.response?.data?.message || 'Erro ao cadastrar produto'
    const errs = e?.response?.data?.errors || {}
    generalError.value = msg
    formErrors.value = {
      nome: Array.isArray(errs?.nome) ? errs.nome[0] : '',
      preco_venda: Array.isArray(errs?.preco_venda) ? errs.preco_venda[0] : '',
      custo_medio: Array.isArray(errs?.custo_medio) ? errs.custo_medio[0] : '',
      estoque: Array.isArray(errs?.estoque) ? errs.estoque[0] : '',
    }
    showToast('error', msg)
  },
})

function validateLocal(): boolean {
  formErrors.value = {}
  generalError.value = ''
  const local: Record<string, string> = {}

  const nome = form.value.nome?.trim()
  if (!nome || nome.length < 3) local.nome = 'Nome deve ter ao menos 3 caracteres'
  if (Number(form.value.preco_venda) <= 0) local.preco_venda = 'Preço de venda deve ser maior que 0'
  if (Number(form.value.custo_medio) < 0) local.custo_medio = 'Custo médio deve ser maior ou igual a 0'

  if (Object.keys(local).length) {
    formErrors.value = local
    generalError.value = 'Corrija os campos destacados para continuar'
    return false
  }
  return true
}

async function submit() {
  if (!validateLocal()) return
  const payload: NovoProduto = {
    nome: form.value.nome.trim(),
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
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-semibold">Produtos</h1>

      <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
        <Button variant="outline" size="sm" class="gap-2 justify-center" @click="refetch()" :disabled="isFetching">
          <Loader2 v-if="isFetching" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4" />
          <span class="sm:inline">Atualizar</span>
        </Button>

        <Dialog v-model:open="open">
          <DialogTrigger as-child>
            <Button class="gap-2 justify-center">
              <Plus class="h-4 w-4" />
              <span>Novo produto</span>
            </Button>
          </DialogTrigger>

          <DialogContent class="w-[94vw] max-w-[94vw] sm:w-[600px] sm:max-w-[600px] md:w-[720px] md:max-w-[720px]">
            <DialogHeader>
              <DialogTitle>Cadastrar produto</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
              <div class="grid gap-2">
                <Label for="nome">Nome</Label>
                <Input
                  id="nome"
                  v-model="form.nome"
                  placeholder="Ex.: Camiseta Básica"
                  :aria-invalid="!!formErrors.nome"
                />
                <p v-if="formErrors.nome" class="text-sm text-destructive" aria-live="polite">
                  {{ formErrors.nome }}
                </p>
              </div>

              <div class="grid grid-cols-1 gap-4">
                <div class="grid gap-2">
                  <Label for="preco_venda">Preço de venda</Label>
                  <Input
                    id="preco_venda"
                    type="number"
                    step="0.01"
                    v-model="(form.preco_venda as any)"
                    :aria-invalid="!!formErrors.preco_venda"
                  />
                  <p v-if="formErrors.preco_venda" class="text-sm text-destructive" aria-live="polite">
                    {{ formErrors.preco_venda }}
                  </p>
                </div>

                <div class="grid gap-2">
                  <Label for="custo_medio">Custo médio</Label>
                  <Input
                    id="custo_medio"
                    type="number"
                    step="0.01"
                    v-model="(form.custo_medio as any)"
                    :aria-invalid="!!formErrors.custo_medio"
                  />
                  <p v-if="formErrors.custo_medio" class="text-sm text-destructive" aria-live="polite">
                    {{ formErrors.custo_medio }}
                  </p>
                </div>
              </div>

              <div class="grid gap-2">
                <Label for="estoque">Estoque (opcional)</Label>
                <Input
                  id="estoque"
                  type="number"
                  v-model="(form.estoque as any)"
                  placeholder="Se vazio, API usa 1"
                  :aria-invalid="!!formErrors.estoque"
                />
                <p v-if="formErrors.estoque" class="text-sm text-destructive" aria-live="polite">
                  {{ formErrors.estoque }}
                </p>
              </div>
            </div>

            <div v-if="generalError && Object.keys(formErrors).length"
                 class="rounded-md border border-destructive/50 bg-destructive/10 text-destructive px-3 py-2 text-sm">
              {{ generalError }}
            </div>

            <DialogFooter class="mt-2">
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
      <CardHeader class="py-3 sm:py-4">
        <CardTitle class="text-base">Lista de produtos</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid gap-3 sm:hidden">
          <template v-if="isLoading || isFetching">
            <div v-for="i in 6" :key="'skel-m-'+i" class="rounded-lg border p-3">
              <Skeleton class="h-4 w-40 mb-2" />
              <div class="grid grid-cols-2 gap-2 text-sm">
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-4 w-20" />
                <Skeleton class="h-4 w-12" />
              </div>
            </div>
          </template>

          <div v-else-if="!produtos.length" class="text-center text-muted-foreground py-4">
            Nenhum produto.
          </div>

          <div v-else v-for="p in produtos" :key="'m-'+p.id" class="rounded-lg border p-3">
            <div class="flex items-center justify-between">
              <span class="text-xs text-muted-foreground">#{{ p.id }}</span>
              <span class="text-xs">{{ p.estoque }} un.</span>
            </div>
            <h3 class="mt-1 font-medium text-base line-clamp-2">{{ p.nome }}</h3>
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
              <div class="flex flex-col">
                <span class="text-muted-foreground">Custo médio</span>
                <span>{{ money(p.custo_medio) }}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-muted-foreground">Preço venda</span>
                <span>{{ money(p.preco_venda) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hidden sm:block overflow-x-auto">
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
                <TableCell class="w-16">#{{ p.id }}</TableCell>
                <TableCell class="font-medium max-w-[420px] truncate">{{ p.nome }}</TableCell>
                <TableCell class="whitespace-nowrap">{{ money(p.custo_medio) }}</TableCell>
                <TableCell class="whitespace-nowrap">{{ money(p.preco_venda) }}</TableCell>
                <TableCell class="w-20">{{ p.estoque }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
