import { api } from '@/lib/api'

export type ItemCompraInput = {
    id: number
    quantidade: number
    preco_unitario: number
}

export type NovaCompra = {
    fornecedor: string
    produtos: ItemCompraInput[]
}

export type CompraItem = {
    produto_id: number
    quantidade: number
    preco_unitario: number | string
    subtotal?: number | string
}

export type Compra = {
    id: number
    fornecedor: string
    itens?: CompraItem[]
    produtos?: CompraItem[]
    itens_count?: number
    total?: number | string
    created_at?: string
    updated_at?: string
}

type Paginated<T> = { data: T[] } & Record<string, unknown>

export async function listarCompras(): Promise<Compra[]> {
    const { data } = await api.get<Compra[] | Paginated<Compra>>('/compras')
    const body: any = data
    if (Array.isArray(body)) return body
    if (Array.isArray(body?.data)) return body.data
    return []
}

export async function obterCompra(id: number): Promise<Compra> {
    const { data } = await api.get<Compra | { compra: Compra }>(`/compras/${id}`)
    const body: any = data
    return body?.compra ?? body
}

export async function criarCompra(payload: NovaCompra) {
    const { data } = await api.post('/compras', payload)
    return data
}

export type ComprasPorFornecedor = {
    fornecedor: string
    total_compras: number
    total_gasto?: number | string
}

export async function comprasPorFornecedor(): Promise<ComprasPorFornecedor[]> {
    const { data } = await api.get<ComprasPorFornecedor[]>('/compras/por-fornecedor')
    return Array.isArray(data) ? data : []
}
