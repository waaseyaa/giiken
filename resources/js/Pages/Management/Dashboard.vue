<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-ink mb-2">Management Overview</h1>
    <p class="text-ink-muted mb-8">Use the active management tools below for this community.</p>

    <div class="grid gap-4 md:grid-cols-2">
      <a
        :href="`/${community.slug}/manage/ingestion`"
        class="block rounded-lg border border-border bg-surface-raised p-5 hover:border-primary/50 hover:bg-surface"
      >
        <h2 class="text-lg font-semibold text-ink mb-2">Ingestion Queue</h2>
        <p class="text-sm text-ink-muted">
          Upload documents, media, and structured files for processing and transcription.
        </p>
      </a>

      <a
        :href="`/${community.slug}/manage/export`"
        class="block rounded-lg border border-border bg-surface-raised p-5 hover:border-primary/50 hover:bg-surface"
      >
        <h2 class="text-lg font-semibold text-ink mb-2">Export</h2>
        <p class="text-sm text-ink-muted">
          Download the current community archive in open formats for backup or migration.
        </p>
      </a>
    </div>
  </ManagementLayout>
</template>
