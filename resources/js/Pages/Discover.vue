<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface CommunitySummary {
  id: number | string
  name: string
  slug: string
  locale: string
}

defineProps<{
  communities: CommunitySummary[]
}>()
</script>

<template>
  <div class="min-h-screen bg-bg">
    <nav class="bg-indigo-dark text-white px-6 py-3">
      <span class="font-bold text-lg">Giiken</span>
    </nav>

    <header class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-20 px-6 text-center">
      <h1 class="text-4xl font-bold mb-3">Sovereign Indigenous Knowledge</h1>
      <p class="text-indigo-light text-lg max-w-2xl mx-auto">
        Browse community knowledge bases. Each community governs its own content under its own protocols.
      </p>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-12">
      <h2 class="text-xl font-semibold text-indigo-dark mb-6">Communities</h2>

      <div v-if="communities.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="community in communities"
          :key="community.id"
          :href="`/${community.slug}`"
          class="block p-5 bg-white rounded-lg border border-border hover:shadow-md hover:border-indigo transition"
        >
          <h3 class="font-semibold text-indigo-dark text-lg">{{ community.name }}</h3>
          <p class="text-sm text-muted mt-1">/{{ community.slug }}</p>
          <p class="text-xs text-muted mt-3 uppercase tracking-wide">{{ community.locale }}</p>
        </Link>
      </div>

      <div v-else class="bg-white border border-border rounded-lg p-8 text-center">
        <p class="text-muted">
          No communities yet. Run
          <code class="text-indigo bg-indigo-light px-2 py-0.5 rounded">./bin/waaseyaa giiken:seed:test-community</code>
          to seed a demo community.
        </p>
      </div>
    </main>
  </div>
</template>
