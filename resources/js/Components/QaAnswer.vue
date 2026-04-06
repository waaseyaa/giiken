<script setup lang="ts">
import type { SearchResult } from '@/types'

defineProps<{
  answer: string
  citedItems: SearchResult[]
  noRelevantItems: boolean
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-border p-6">
    <div class="flex items-center gap-2 mb-4">
      <span class="w-2.5 h-2.5 bg-indigo rounded-full" />
      <span class="font-semibold text-indigo-dark">Answer</span>
      <span class="text-xs text-muted">from your knowledge base</span>
    </div>

    <div v-if="noRelevantItems" class="text-muted italic">
      I could not find information about this in the community's knowledge base.
    </div>

    <div v-else>
      <div class="prose prose-sm max-w-none text-indigo-dark" v-html="answer" />

      <div v-if="citedItems.length > 0" class="mt-4 pt-4 border-t border-border">
        <p class="text-sm font-medium text-muted mb-2">Sources</p>
        <div class="space-y-2">
          <div
            v-for="(item, idx) in citedItems"
            :key="item.id"
            class="text-sm p-3 rounded bg-bg"
          >
            <span class="text-indigo font-medium">[{{ idx + 1 }}]</span>
            <span class="font-medium ml-1">{{ item.title }}</span>
            <span class="text-muted ml-2">{{ item.summary?.slice(0, 100) }}...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
