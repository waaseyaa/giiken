<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType, CommunitySummary } from '@/types'

const props = defineProps<{
  communities: CommunitySummary[]
}>()

const types = Object.entries(KNOWLEDGE_TYPE_CONFIG) as [KnowledgeType, typeof KNOWLEDGE_TYPE_CONFIG[KnowledgeType]][]
const targetSlug = computed(() => props.communities[0]?.slug)
</script>

<template>
  <section v-if="communities.length > 0" class="max-w-5xl mx-auto px-6 pt-8">
    <div class="flex gap-2 overflow-x-auto flex-nowrap justify-center">
      <Link
        v-for="[type, config] in types"
        :key="type"
        :href="`/${targetSlug}?type=${type}`"
        class="px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors"
        :class="config.chip"
      >
        {{ config.label }}
      </Link>
    </div>
  </section>
</template>
