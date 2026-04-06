<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { Community } from '@/types'

const props = defineProps<{
  community: Community
  showManagement?: boolean
}>()
</script>

<template>
  <div class="min-h-screen bg-bg">
    <nav class="bg-indigo-dark text-white px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <span class="font-bold text-lg">{{ community.name }}</span>
        <div class="flex gap-4 text-sm">
          <Link :href="`/${community.slug}`" class="hover:text-indigo-light">Discover</Link>
          <Link :href="`/${community.slug}/manage`" class="hover:text-indigo-light" v-if="showManagement">⚙ Management</Link>
        </div>
      </div>
    </nav>
    <main>
      <slot />
    </main>
  </div>
</template>
