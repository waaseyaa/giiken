<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import Pagination from '@/Components/Pagination.vue'
import type { Community, SearchResultSet } from '@/types'

const props = defineProps<{
  community: Community
  query: string
  results: SearchResultSet
  page: number
}>()
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="query" />
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8">
      <p class="text-sm text-muted mb-4">{{ results.totalHits }} {{ results.totalHits === 1 ? 'result' : 'results' }} for "{{ query }}"</p>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <KnowledgeCard
          v-for="item in results.items"
          :key="item.id"
          :id="item.id"
          :title="item.title"
          :summary="item.summary"
          :knowledge-type="item.knowledgeType"
          :community-slug="community.slug"
        />
      </div>

      <Pagination
        :current-page="page"
        :total-pages="results.totalPages"
        :base-url="`/${community.slug}/search`"
        :query="query"
      />
    </div>
  </DiscoveryLayout>
</template>
