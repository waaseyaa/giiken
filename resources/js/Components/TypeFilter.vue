<script setup lang="ts">
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

defineProps<{ active: KnowledgeType | null }>()
const emit = defineEmits<{ select: [type: KnowledgeType | null] }>()

const types = Object.entries(KNOWLEDGE_TYPE_CONFIG) as [KnowledgeType, typeof KNOWLEDGE_TYPE_CONFIG[KnowledgeType]][]

// Active-state class for each type button. Uses the saturated type colour
// as the background with on-primary text, so the active pill inverts from
// the default chip styling.
const activeClass: Record<KnowledgeType, string> = {
  cultural: 'bg-cultural text-on-primary',
  governance: 'bg-governance text-on-primary',
  land: 'bg-land text-on-primary',
  relationship: 'bg-relationship text-on-primary',
  event: 'bg-event text-on-primary',
}
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
      :class="active === type ? activeClass[type] : config.chip"
      @click="emit('select', type)"
    >
      {{ config.label }}
    </button>
  </div>
</template>
