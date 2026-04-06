<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()

function requestExport() {
  alert('Export will be wired in the next iteration.')
}
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Export</h1>

    <div class="bg-white rounded-lg border border-border p-6 max-w-lg">
      <h2 class="font-semibold text-indigo-dark mb-2">Export all community data</h2>
      <p class="text-sm text-muted mb-4">
        Downloads a ZIP archive containing all knowledge items (Markdown), original media files,
        embeddings, and community configuration. Open formats only, no vendor lock-in.
      </p>
      <p class="text-xs text-indigo bg-indigo-light rounded p-3 mb-4">
        <strong>Community Sovereignty Guarantee:</strong> Your data is always exportable in open formats.
        You can move to any infrastructure at any time.
      </p>
      <button
        @click="requestExport"
        class="px-6 py-2 bg-indigo text-white rounded-lg hover:bg-indigo-mid font-medium"
      >
        Export Community Data
      </button>
    </div>
  </ManagementLayout>
</template>
