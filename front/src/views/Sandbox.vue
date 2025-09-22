<!-- src/views/Playground.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Download } from 'lucide-vue-next'

const open = ref(false)
</script>

<template>
  <div class="p-6 space-y-4">
    <Button @click="open = true" class="gap-2">
      <Download class="w-4 h-4" /> Abrir diálogo
    </Button>

    <Dialog v-model:open="open">
      <DialogTrigger as-child>
        <Button variant="outline">Abrir</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>shadcn-vue funcionando ✅</DialogTitle>
        </DialogHeader>
        <p class="text-sm text-muted-foreground">Pronto para montar as telas.</p>
      </DialogContent>
    </Dialog>
  </div>
</template>
