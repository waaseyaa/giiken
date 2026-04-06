<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Users</h1>
    <p class="text-muted">User management will be wired when the Waaseyaa user service is integrated.</p>
  </ManagementLayout>
</template>
