<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import SearchHero from '@/Components/SearchHero.vue'
import BrowseStrip from '@/Components/BrowseStrip.vue'
import type { CommunitySummary } from '@/types'

defineProps<{
  communities: CommunitySummary[]
}>()
</script>

<template>
  <div class="min-h-screen bg-surface">
    <nav class="bg-surface-inverse text-on-inverse px-6 py-3">
      <span class="font-bold text-lg">Giiken</span>
    </nav>

    <SearchHero :communities="communities" />
    <BrowseStrip :communities="communities" />

    <main class="max-w-5xl mx-auto px-6 py-12">
      <h2 class="text-xl font-semibold text-ink mb-6">Communities</h2>

      <div v-if="communities.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="community in communities"
          :key="community.id"
          :href="`/${community.slug}`"
          class="block p-5 bg-surface-raised rounded-lg border border-border hover:shadow-md hover:border-primary transition"
        >
          <h3 class="font-semibold text-ink text-lg">{{ community.name }}</h3>
          <p class="text-sm text-ink-muted mt-1">/{{ community.slug }}</p>
          <p class="text-xs text-ink-muted mt-3 uppercase tracking-wide">{{ community.locale }}</p>
        </Link>
      </div>

      <div v-else class="bg-surface-raised border border-border rounded-lg p-8 text-center">
        <p class="text-ink-muted">
          No communities yet. Run
          <code class="text-primary bg-primary-subtle px-2 py-0.5 rounded">./bin/waaseyaa giiken:seed:test-community</code>
          to seed a demo community.
        </p>
      </div>
    </main>
  </div>
</template>
