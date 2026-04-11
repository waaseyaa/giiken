<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <div class="min-h-screen bg-surface flex">
    <aside class="w-56 bg-surface-inverse text-on-inverse flex flex-col shrink-0">
      <div class="px-4 py-5 border-b border-white/10">
        <span class="font-bold text-sm">{{ community.name }}</span>
      </div>

      <nav class="flex-1 px-2 py-4 space-y-1">
        <p class="px-3 text-xs text-white/50 uppercase tracking-wider mb-2">Management</p>
        <Link :href="`/${community.slug}/manage`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📊 Dashboard</Link>
        <Link :href="`/${community.slug}/manage/reports`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📋 Reports</Link>
        <Link :href="`/${community.slug}/manage/users`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">👥 Users</Link>
        <Link :href="`/${community.slug}/manage/ingestion`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📥 Ingestion Queue</Link>

        <p class="px-3 text-xs text-white/50 uppercase tracking-wider mt-6 mb-2">System</p>
        <Link :href="`/${community.slug}/manage/export`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📦 Export</Link>
      </nav>

      <div class="px-2 py-4 border-t border-white/10">
        <Link :href="`/${community.slug}`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">
          ← Back to Discover
        </Link>
      </div>
    </aside>

    <main class="flex-1 p-8">
      <slot />
    </main>
  </div>
</template>
