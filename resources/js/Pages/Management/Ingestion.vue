<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Ingestion Queue</h1>
    <p class="text-muted">Pipeline status display will be wired when the queue service is integrated.</p>
  </ManagementLayout>
</template>
