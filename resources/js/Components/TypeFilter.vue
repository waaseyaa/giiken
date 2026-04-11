<script setup lang="ts">
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

defineProps<{ active: KnowledgeType | null }>()
const emit = defineEmits<{ select: [type: KnowledgeType | null] }>()

const types = Object.entries(KNOWLEDGE_TYPE_CONFIG) as [KnowledgeType, typeof KNOWLEDGE_TYPE_CONFIG[KnowledgeType]][]
</script>

<template>
  <div class="flex gap-2 flex-wrap">
    <button
      class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
      :class="active === null ? 'bg-primary text-on-primary' : 'bg-primary-subtle text-primary'"
      @click="emit('select', null)"
    >
      All
    </button>
    <button
      v-for="[type, config] in types"
      :key="type"
      class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
      :class="active === type ? config.activeChip : config.chip"
      @click="emit('select', type)"
    >
      {{ config.label }}
    </button>
  </div>
</template>
