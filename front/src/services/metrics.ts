import { api } from '@/lib/api'

export type MetricsOverview = {
    products_count: number
    purchases_count: number
    sales_count: number
    revenue_month: string
    profit_month: string
    revenue_total: string
    profit_total: string
    low_stock: number
    month_label: string
}

export async function getOverview(): Promise<MetricsOverview> {
    const { data } = await api.get<MetricsOverview>('/metrics/overview')
    return data
}
