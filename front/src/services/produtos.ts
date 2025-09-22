import { api } from '@/lib/api'

export type Produto = {
    id: number
    nome: string
    custo_medio: string
    preco_venda: string
    estoque: number
}

export type NovoProduto = {
    nome: string
    preco_venda: number
    custo_medio: number
    estoque?: number
}

export async function listarProdutos(): Promise<Produto[]> {
    const { data } = await api.get<Produto[]>('/produtos')
    return data
}

export async function criarProduto(payload: NovoProduto) {
    const { data } = await api.post('/produtos', payload)
    return data as { message: string; data: Produto }
}
