<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { listarProdutos, criarProduto, type Produto, type NovoProduto } from '@/services/produtos'
import { toast } from 'vue-sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const produtos = ref<Produto[]>([])
const loading = ref(false)
const open = ref(false)

// form
const form = ref<NovoProduto>({
  nome: '',
  preco_venda: 0,
  custo_medio: 0,
  estoque: undefined,
})

async function load() {
  loading.value = true
  try {
    produtos.value = await listarProdutos()
  } catch (e: any) {
    toast.error('Falha ao carregar produtos')
  } finally {
    loading.value = false
  }
}

async function submit() {
  try {
    if (!form.value.nome || form.value.nome.trim().length < 3) {
      return toast.warning('Nome deve ter ao menos 3 caracteres')
    }
    if (Number(form.value.preco_venda) <= 0) return toast.warning('Preço de venda deve ser > 0')
    if (Number(form.value.custo_medio) < 0) return toast.warning('Custo médio deve ser ≥ 0')

    const payload: NovoProduto = {
      nome: form.value.nome.trim(),
      preco_venda: Number(form.value.preco_venda),
      custo_medio: Number(form.value.custo_medio),
      ...(form.value.estoque !== undefined && form.value.estoque !== null && String(form.value.estoque) !== '' ? { estoque: Number(form.value.estoque) } : {})
    }

    const res = await criarProduto(payload)
    toast.success(res.message || 'Produto cadastrado')
    open.value = false
    await load()

    form.value = { nome: '', preco_venda: 0, custo_medio: 0, estoque: undefined }
  } catch (e: any) {

    const msg = e?.response?.data?.message || 'Erro ao cadastrar produto'
    const errs = e?.response?.data?.errors
    toast.error(
      Array.isArray(errs?.nome) ? `${msg}: ${errs.nome[0]}` : msg
    )
  }
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Produtos</h1>
      <Dialog v-model:open="open">
        <DialogTrigger as-child>
          <Button>Novo produto</Button>
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
                <Input id="preco_venda" type="number" step="0.01" v-model="(form.preco_venda as any)" />
              </div>
              <div class="grid gap-2">
                <Label for="custo_medio">Custo médio</Label>
                <Input id="custo_medio" type="number" step="0.01" v-model="(form.custo_medio as any)" />
              </div>
            </div>
            <div class="grid gap-2">
              <Label for="estoque">Estoque (opcional)</Label>
              <Input id="estoque" type="number" v-model="(form.estoque as any)" placeholder="Se vazio, API usa 1" />
            </div>
          </div>

          <DialogFooter>
            <Button :disabled="loading" @click="submit">Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
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
                <TableHead>ID</TableHead>
                <TableHead>Nome</TableHead>
                <TableHead>Custo médio</TableHead>
                <TableHead>Preço venda</TableHead>
                <TableHead>Estoque</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="loading">
                <TableCell colspan="5">Carregando...</TableCell>
              </TableRow>
              <TableRow v-else-if="!produtos.length">
                <TableCell colspan="5">Nenhum produto.</TableCell>
              </TableRow>
              <TableRow v-else v-for="p in produtos" :key="p.id">
                <TableCell>{{ p.id }}</TableCell>
                <TableCell class="font-medium">{{ p.nome }}</TableCell>
                <TableCell>R$ {{ p.custo_medio }}</TableCell>
                <TableCell>R$ {{ p.preco_venda }}</TableCell>
                <TableCell>{{ p.estoque }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
