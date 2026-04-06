<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

const props = defineProps<{
  id: string
  title: string
  summary: string
  knowledgeType: KnowledgeType | null
  communitySlug: string
}>()

const typeConfig = props.knowledgeType ? KNOWLEDGE_TYPE_CONFIG[props.knowledgeType] : null
</script>

<template>
  <Link :href="`/${communitySlug}/item/${id}`" class="block p-4 bg-white rounded-lg border border-border hover:shadow-md transition-shadow">
    <div class="flex items-start gap-3">
      <div
        v-if="typeConfig"
        class="w-3 h-3 rounded-full mt-1.5 shrink-0"
        :style="{ backgroundColor: typeConfig.text }"
      />
      <div class="min-w-0">
        <h3 class="font-semibold text-indigo-dark truncate">{{ title }}</h3>
        <p class="text-sm text-muted mt-1 line-clamp-2">{{ summary }}</p>
        <span
          v-if="typeConfig"
          class="inline-block text-xs px-2 py-0.5 rounded-full mt-2"
          :style="{ backgroundColor: typeConfig.bg, color: typeConfig.text }"
        >
          {{ typeConfig.label }}
        </span>
      </div>
    </div>
  </Link>
</template>
