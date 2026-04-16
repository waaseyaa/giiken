<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { Community, KnowledgeItem } from '@/types'

const props = defineProps<{
  community: Community
  item: KnowledgeItem
  pageTitle?: string
}>()

const typeConfig = props.item.knowledgeType ? KNOWLEDGE_TYPE_CONFIG[props.item.knowledgeType] : null
</script>

<template>
  <Head :title="pageTitle ?? `${community.name} | ${item.title}`" />
  <DiscoveryLayout :community="community">
    <div class="max-w-3xl mx-auto px-6 py-8">
      <Link :href="`/${community.slug}`" class="text-sm text-primary hover:underline mb-4 inline-block">
        ← Back to browse
      </Link>

      <article>
        <div class="flex items-center gap-3 mb-4">
          <span
            v-if="typeConfig"
            class="inline-block text-xs px-2 py-0.5 rounded-full"
            :class="typeConfig.chip"
          >
            {{ typeConfig.label }}
          </span>
          <span class="text-xs text-ink-muted">{{ item.createdAt }}</span>
        </div>

        <h1 class="text-2xl font-bold text-ink mb-6">{{ item.title }}</h1>

        <div class="prose prose-sm max-w-none text-ink" v-html="item.content" />
      </article>
    </div>
  </DiscoveryLayout>
</template>
