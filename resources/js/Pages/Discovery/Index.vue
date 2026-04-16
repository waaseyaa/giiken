<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import { Head, router } from '@inertiajs/vue3'
import type { Community, SearchResult, SearchResultSet, KnowledgeType } from '@/types'
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

const props = defineProps<{
  community: Community
  recentItems: SearchResultSet
  page: number
  hasMore: boolean
  pageTitle?: string
}>()

const activeType = ref<KnowledgeType | null>(null)
const loadedItems = ref<SearchResult[]>([...props.recentItems.items])
const currentPage = ref(props.page)
const hasMorePages = ref(props.hasMore)
const loadingNextPage = ref(false)
const sentinelEl = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

function resetLoadedItems() {
  loadedItems.value = [...props.recentItems.items]
  currentPage.value = props.page
  hasMorePages.value = props.hasMore
}

const filteredItems = computed(() => {
  if (activeType.value === null) return loadedItems.value
  return loadedItems.value.filter(i => i.knowledgeType === activeType.value)
})

function appendIncomingItems(items: SearchResult[]) {
  const seen = new Set(loadedItems.value.map(item => item.id))
  const merged = [...loadedItems.value]
  for (const item of items) {
    if (!seen.has(item.id)) {
      merged.push(item)
    }
  }
  loadedItems.value = merged
}

function loadNextPage() {
  if (loadingNextPage.value || !hasMorePages.value) return
  loadingNextPage.value = true

  const nextPage = currentPage.value + 1
  router.get(
    `/${props.community.slug}`,
    { page: nextPage },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['recentItems', 'page', 'hasMore'],
      onSuccess: (page) => {
        const pageProps = page.props as Record<string, unknown>
        const incomingResultSet = (pageProps.recentItems ?? {}) as Partial<SearchResultSet>
        const incomingItems = Array.isArray(incomingResultSet.items) ? incomingResultSet.items as SearchResult[] : []
        appendIncomingItems(incomingItems)

        const resolvedPage = Number(pageProps.page ?? nextPage)
        const resolvedHasMore = Boolean(pageProps.hasMore ?? false)
        currentPage.value = resolvedPage
        hasMorePages.value = resolvedHasMore
      },
      onFinish: () => {
        loadingNextPage.value = false
      },
    },
  )
}

function wireObserver() {
  if (observer) observer.disconnect()
  if (!sentinelEl.value || !hasMorePages.value) return

  observer = new IntersectionObserver((entries) => {
    if (entries.some(entry => entry.isIntersecting)) {
      loadNextPage()
    }
  }, { rootMargin: '240px 0px' })

  observer.observe(sentinelEl.value)
}

watch(() => props.community.slug, () => {
  resetLoadedItems()
  nextTick(() => wireObserver())
})

watch(() => props.recentItems.items, () => {
  if (props.page === 1) {
    resetLoadedItems()
  }
})

watch([hasMorePages, sentinelEl], () => {
  nextTick(() => wireObserver())
})

onMounted(() => {
  wireObserver()
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})
</script>

<template>
  <Head :title="pageTitle ?? `${community.name} | Discover`" />
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-primary to-primary-hover text-on-primary py-16 px-6 text-center">
      <h1 class="text-3xl font-bold mb-2">{{ community.name }} Knowledge Base</h1>
      <p class="text-primary-subtle mb-8">Search or ask anything</p>
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" mode="search" />
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
          :access-tier="item.accessTier"
          :source-origin="item.sourceOrigin"
          :created-at="item.createdAt"
        />
      </div>

      <p v-if="filteredItems.length === 0" class="text-ink-muted text-center mt-12">
        No knowledge items yet. Upload documents to get started.
      </p>

      <div
        v-show="hasMorePages"
        ref="sentinelEl"
        class="h-8 mt-6"
        aria-hidden="true"
      />

      <p v-if="loadingNextPage" class="text-xs text-ink-muted text-center mt-2">
        Loading more…
      </p>
    </div>
  </DiscoveryLayout>
</template>
