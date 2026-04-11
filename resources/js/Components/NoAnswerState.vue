<script setup lang="ts">
// Shown when Q&A returns no answer and no citations. Intentionally avoids
// any LLM-generated fallback copy — the page should not pretend to have
// information it does not have.
</script>

<template>
  <div class="bg-surface-raised rounded-lg border border-border p-6 text-center">
    <p class="text-ink font-medium mb-1">No information found</p>
    <p class="text-sm text-ink-muted italic">
      I could not find information about this in the community's knowledge base.
    </p>
  </div>
</template>
