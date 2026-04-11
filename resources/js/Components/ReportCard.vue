<script setup lang="ts">
defineProps<{
  title: string
  description: string
  type: string
}>()

const emit = defineEmits<{ generate: [type: string] }>()
</script>

<template>
  <button
    @click="emit('generate', type)"
    class="p-5 bg-surface-raised rounded-lg border border-border hover:shadow-md transition-shadow text-left w-full"
  >
    <h3 class="font-semibold text-ink">{{ title }}</h3>
    <p class="text-sm text-ink-muted mt-1">{{ description }}</p>
  </button>
</template>
