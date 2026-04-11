<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

interface UploadResult {
  originalFilename: string
  mimeType: string
  mediaId: string
  metadata: Record<string, unknown>
}

const props = defineProps<{
  community: Community
  bootError?: string | null
  uploadError?: string | null
  uploadResult?: UploadResult | null
}>()

const form = useForm<{ file: File | null }>({ file: null })

function submit() {
  if (!form.file) {
    return
  }
  form.post(`/${props.community.slug}/manage/ingestion`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.reset('file'),
  })
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  form.file = input.files && input.files.length > 0 ? input.files[0] : null
}
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-ink mb-6">Ingestion Queue</h1>

    <div v-if="bootError" class="mb-4 p-3 border border-amber-400 bg-amber-50 text-amber-900 rounded">
      {{ bootError }}
    </div>

    <section class="mb-8 p-4 border border-border rounded bg-surface-raised">
      <h2 class="text-lg font-semibold text-ink mb-3">Upload a file</h2>
      <p class="text-sm text-ink-muted mb-4">
        Markdown, CSV, HTML, Word/PDF documents, and audio/video files are accepted.
        Audio and video files are queued for transcription.
      </p>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <input
          type="file"
          class="block text-sm"
          :disabled="form.processing"
          @change="onFileChange"
        />
        <div>
          <button
            type="submit"
            class="px-4 py-2 bg-primary text-on-primary rounded disabled:opacity-50"
            :disabled="form.processing || !form.file"
          >
            {{ form.processing ? 'Uploading…' : 'Upload' }}
          </button>
        </div>
      </form>

      <div v-if="uploadError" class="mt-4 p-3 border border-danger-border bg-danger-subtle text-danger rounded text-sm">
        {{ uploadError }}
      </div>

      <div
        v-if="uploadResult"
        class="mt-4 p-3 border border-emerald-400 bg-emerald-50 text-emerald-900 rounded text-sm"
      >
        <div class="font-semibold">Uploaded: {{ uploadResult.originalFilename }}</div>
        <div class="text-xs text-emerald-800 mt-1">
          MIME: {{ uploadResult.mimeType }} · Media ID: {{ uploadResult.mediaId }}
        </div>
      </div>
    </section>

    <p class="text-ink-muted text-sm">
      Pipeline status display will be wired when the queue service is integrated.
    </p>
  </ManagementLayout>
</template>
