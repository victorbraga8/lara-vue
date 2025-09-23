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
    preco_unitario: number
    subtotal?: number
}

export type Compra = {
    id: number
    fornecedor: string
    itens?: CompraItem[]
    produtos?: CompraItem[]
    total?: number | string
    created_at?: string
    updated_at?: string
}

export async function listarCompras(): Promise<Compra[]> {
    const { data } = await api.get<Compra[]>('/compras')
    return Array.isArray(data) ? data : []
}

export async function obterCompra(id: number): Promise<Compra> {
    const { data } = await api.get<Compra>(`/compras/${id}`)
    return data
}


export async function criarCompra(payload: NovaCompra) {
    const { data } = await api.post('/compras', payload)
    return data
}

export async function listarComprasPorFornecedor(fornecedor: string): Promise<Compra[]> {
    const { data } = await api.get<Compra[]>('/compras', { params: { fornecedor } })
    return Array.isArray(data) ? data : []
}
