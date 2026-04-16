<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import AnswerPanel from '@/Components/AnswerPanel.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import { Head } from '@inertiajs/vue3'
import type { Community, Citation, SearchResultSet } from '@/types'

defineProps<{
  community: Community
  question: string
  answer: string
  citations: Citation[]
  noRelevantItems: boolean
  relatedItems: SearchResultSet
  pageTitle?: string
}>()
</script>

<template>
  <Head :title="pageTitle ?? `${community.name} | Ask`" />
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-primary to-primary-hover text-on-primary py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="question" mode="ask" />
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-6 py-8">
      <AnswerPanel
        :answer="answer"
        :citations="citations"
        :community-slug="community.slug"
        :no-relevant-items="noRelevantItems"
      />

      <div v-if="relatedItems.items.length > 0" class="mt-8">
        <h2 class="text-lg font-semibold text-ink mb-4">Related knowledge</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <KnowledgeCard
            v-for="item in relatedItems.items"
            :key="item.id"
            :id="item.id"
            :title="item.title"
            :summary="item.summary"
            :knowledge-type="item.knowledgeType"
            :community-slug="community.slug"
            :access-tier="item.accessTier"
            :source-origin="item.sourceOrigin"
            :created-at="item.createdAt"
          />
        </div>
      </div>
    </div>
  </DiscoveryLayout>
</template>
