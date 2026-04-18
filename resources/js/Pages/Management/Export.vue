<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-ink mb-6">Export</h1>

    <div class="bg-surface-raised rounded-lg border border-border p-6 max-w-lg">
      <h2 class="font-semibold text-ink mb-2">Export all community data</h2>
      <p class="text-sm text-ink-muted mb-4">
        Downloads a ZIP archive containing all knowledge items (Markdown), original media files,
        embeddings, and community configuration. Open formats only, no vendor lock-in.
      </p>
      <p class="text-xs text-primary bg-primary-subtle rounded p-3 mb-4">
        <strong>Community Sovereignty Guarantee:</strong> Your data is always exportable in open formats.
        You can move to any infrastructure at any time.
      </p>
      <a
        :href="`/${community.slug}/manage/export/download`"
        class="inline-flex px-6 py-2 bg-primary text-on-primary rounded-lg hover:bg-primary-hover font-medium"
      >
        Export Community Data
      </a>
    </div>
  </ManagementLayout>
</template>
