<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import Pagination from '@/Components/Pagination.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import { Head, router } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPES } from '@/types'
import type { Community, SearchResultSet, KnowledgeType } from '@/types'
import { ref, watch } from 'vue'

const props = defineProps<{
  community: Community
  query: string
  results: SearchResultSet
  page: number
  selectedType?: string
  pageTitle?: string
}>()

const activeType = ref<KnowledgeType | null>(
  props.selectedType && KNOWLEDGE_TYPES.includes(props.selectedType as KnowledgeType)
    ? props.selectedType as KnowledgeType
    : null,
)

watch(activeType, (value) => {
  router.get(
    `/${props.community.slug}/search`,
    {
      q: props.query,
      page: 1,
      type: value ?? undefined,
    },
    { preserveState: true },
  )
})
</script>

<template>
  <Head :title="pageTitle ?? `${community.name} | Search`" />
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-primary to-primary-hover text-on-primary py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="query" mode="search" />
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8">
      <p class="text-sm text-ink-muted mb-4">{{ results.totalHits }} {{ results.totalHits === 1 ? 'result' : 'results' }} for "{{ query }}"</p>
      <TypeFilter :active="activeType" @select="activeType = $event" />

      <div class="grid gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-3">
        <KnowledgeCard
          v-for="item in results.items"
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

      <Pagination
        :current-page="page"
        :total-pages="results.totalPages"
        :base-url="`/${community.slug}/search`"
        :query="query"
        :extra-query="activeType ? { type: activeType } : {}"
      />
    </div>
  </DiscoveryLayout>
</template>
