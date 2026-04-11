<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-ink mb-6">Dashboard</h1>
    <p class="text-ink-muted">Management dashboard coming soon. Use the sidebar to navigate.</p>
  </ManagementLayout>
</template>
