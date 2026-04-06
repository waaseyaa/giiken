<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import QaAnswer from '@/Components/QaAnswer.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import type { Community, SearchResultSet, SearchResult } from '@/types'

const props = defineProps<{
  community: Community
  question: string
  answer: string
  citedItemIds: string[]
  noRelevantItems: boolean
  relatedItems: SearchResultSet
}>()

const citedItems: SearchResult[] = props.relatedItems.items.filter(i =>
  props.citedItemIds.includes(i.id)
)
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="question" />
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-6 py-8">
      <QaAnswer
        :answer="answer"
        :cited-items="citedItems"
        :no-relevant-items="noRelevantItems"
      />

      <div v-if="relatedItems.items.length > 0" class="mt-8">
        <h2 class="text-lg font-semibold text-indigo-dark mb-4">Related knowledge</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <KnowledgeCard
            v-for="item in relatedItems.items"
            :key="item.id"
            :id="item.id"
            :title="item.title"
            :summary="item.summary"
            :knowledge-type="item.knowledgeType"
            :community-slug="community.slug"
          />
        </div>
      </div>
    </div>
  </DiscoveryLayout>
</template>
