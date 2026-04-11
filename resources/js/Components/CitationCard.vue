<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { Citation } from '@/types'

const props = defineProps<{
  index: number
  citation: Citation
  communitySlug: string
}>()

const expanded = ref(false)

const typeConfig = props.citation.knowledgeType
  ? KNOWLEDGE_TYPE_CONFIG[props.citation.knowledgeType]
  : null

function toggle() {
  expanded.value = !expanded.value
}
</script>

<template>
  <div
    :id="`citation-${index}`"
    class="border border-border rounded bg-bg overflow-hidden"
  >
    <button
      type="button"
      class="w-full flex items-start gap-2 text-left p-3 hover:bg-white transition-colors"
      :aria-expanded="expanded"
      :aria-controls="`citation-${index}-body`"
      @click="toggle"
    >
      <span class="text-indigo font-medium shrink-0">[{{ index }}]</span>
      <span class="font-medium text-indigo-dark flex-1 min-w-0 truncate">{{ citation.title }}</span>
      <span
        v-if="typeConfig"
        class="text-xs px-2 py-0.5 rounded-full shrink-0"
        :style="{ backgroundColor: typeConfig.bg, color: typeConfig.text }"
      >
        {{ typeConfig.label }}
      </span>
      <span class="text-xs text-muted shrink-0">{{ expanded ? '−' : '+' }}</span>
    </button>

    <div
      v-if="expanded"
      :id="`citation-${index}-body`"
      class="px-3 pb-3 pt-0 border-t border-border bg-white"
    >
      <p class="text-sm text-indigo-dark whitespace-pre-line">{{ citation.excerpt }}</p>
      <Link
        :href="`/${communitySlug}/item/${citation.itemId}`"
        class="inline-block text-xs text-indigo hover:underline mt-2"
      >
        Open full item →
      </Link>
    </div>
  </div>
</template>
