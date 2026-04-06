<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import type { Community, SearchResultSet, KnowledgeType } from '@/types'
import { ref, computed } from 'vue'

const props = defineProps<{
  community: Community
  recentItems: SearchResultSet
}>()

const activeType = ref<KnowledgeType | null>(null)

const filteredItems = computed(() => {
  if (activeType.value === null) return props.recentItems.items
  return props.recentItems.items.filter(i => i.knowledgeType === activeType.value)
})
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-16 px-6 text-center">
      <h1 class="text-3xl font-bold mb-2">{{ community.name }} Knowledge Base</h1>
      <p class="text-indigo-light mb-8">Search or ask anything</p>
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" />
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8">
      <TypeFilter :active="activeType" @select="activeType = $event" />

      <div class="grid gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-3">
        <KnowledgeCard
          v-for="item in filteredItems"
          :key="item.id"
          :id="item.id"
          :title="item.title"
          :summary="item.summary"
          :knowledge-type="item.knowledgeType"
          :community-slug="community.slug"
        />
      </div>

      <p v-if="filteredItems.length === 0" class="text-muted text-center mt-12">
        No knowledge items yet. Upload documents to get started.
      </p>
    </div>
  </DiscoveryLayout>
</template>
