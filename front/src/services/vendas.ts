import { api } from '@/lib/api'

export type ItemVendaInput = {
    id: number
    quantidade: number
    preco_unitario: number
}

export type NovaVenda = {
    cliente: string
    produtos: ItemVendaInput[]
}

export type VendaItem = {
    produto_id: number
    quantidade: number
    preco_unitario: number
    subtotal?: number
    lucro_item?: number
}

export type Venda = {
    id: number
    cliente: string
    itens?: VendaItem[]
    produtos?: VendaItem[]
    total?: number | string
    lucro?: number | string
    status?: 'completed' | 'canceled' | string
    created_at?: string
    updated_at?: string
}

export type CreateVendaResponse = {
    message?: string
    venda?: Venda
    total?: number | string
    lucro?: number | string
}

export type CancelVendaResponse = {
    message?: string
    venda?: Venda
}

type Paginated<T> = { data: T[] } & Record<string, unknown>

export async function listarVendas(): Promise<Venda[]> {
    const { data } = await api.get<Pagamento<Venda[]> | Paginated<Venda> | Venda[]>('/vendas' as any)
    const body: any = data
    if (Array.isArray(body)) return body
    if (Array.isArray(body?.data)) return body.data
    return []
}

export async function obterVenda(id: number): Promise<Venda> {
    const { data } = await api.get<Venda | { venda: Venda }>(`/vendas/${id}`)
    const body: any = data
    return body?.venda ?? body
}

export async function criarVenda(payload: NovaVenda): Promise<CreateVendaResponse> {
    const { data } = await api.post<CreateVendaResponse>('/vendas', payload)
    return data
}

export async function cancelarVenda(id: number): Promise<CancelVendaResponse> {
    const { data } = await api.post<CancelVendaResponse>(`/vendas/${id}/cancelar`)
    return data
}
