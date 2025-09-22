import { VueQueryPlugin, type VueQueryPluginOptions, QueryClient } from '@tanstack/vue-query'

export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 60_000,
            refetchOnWindowFocus: false,
            retry: 1,
        },
    },
})

export const vueQueryOptions: VueQueryPluginOptions = {
    queryClient,
}
export const vueQueryPlugin = VueQueryPlugin
