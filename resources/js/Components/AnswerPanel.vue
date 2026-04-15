<script setup lang="ts">
import { computed } from 'vue'
import CitationCard from '@/Components/CitationCard.vue'
import NoAnswerState from '@/Components/NoAnswerState.vue'
import type { Citation } from '@/types'

const props = withDefaults(
  defineProps<{
    answer: string
    citations: Citation[]
    communitySlug: string
    loading?: boolean
    noRelevantItems?: boolean
  }>(),
  {
    loading: false,
    noRelevantItems: false,
  },
)

// TODO(#17): replace with i18n key `discover.answer_label`.
const answerLabel = 'from your knowledge base'

interface TextPart {
  kind: 'text'
  text: string
}

interface SupPart {
  kind: 'sup'
  index: number
}

type AnswerPart = TextPart | SupPart

/**
 * Parse [N] markers in the answer text into a sequence of text + superscript
 * parts so the template can render the superscript as a real anchor element
 * linking to the matching citation card. We intentionally avoid v-html here
 * so the answer text cannot inject markup into the page even if a future LLM
 * provider returns something unsafe.
 */
const answerParts = computed<AnswerPart[]>(() => {
  const parts: AnswerPart[] = []
  const re = /\[(\d+)\]/g
  let lastIndex = 0
  let match: RegExpExecArray | null
  while ((match = re.exec(props.answer)) !== null) {
    if (match.index > lastIndex) {
      parts.push({ kind: 'text', text: props.answer.slice(lastIndex, match.index) })
    }
    parts.push({ kind: 'sup', index: Number(match[1]) })
    lastIndex = re.lastIndex
  }
  if (lastIndex < props.answer.length) {
    parts.push({ kind: 'text', text: props.answer.slice(lastIndex) })
  }
  return parts
})

const shouldRenderNoAnswer = computed(
  () =>
    !props.loading &&
    (props.noRelevantItems || (props.answer.trim() === '' && props.citations.length === 0)),
)
</script>

<template>
  <NoAnswerState v-if="shouldRenderNoAnswer" />

  <div v-else class="bg-surface-raised rounded-lg border border-border p-6">
    <div class="flex items-center gap-2 mb-4">
      <span class="w-2.5 h-2.5 bg-primary rounded-full" />
      <span class="font-semibold text-ink">Answer</span>
      <span class="text-xs text-ink-muted">{{ answerLabel }}</span>
    </div>

    <div v-if="loading" data-test="answer-loading" class="space-y-3">
      <div class="h-4 bg-surface rounded animate-pulse" />
      <div class="h-4 bg-surface rounded animate-pulse w-11/12" />
      <div class="h-4 bg-surface rounded animate-pulse w-3/4" />
    </div>

    <div v-else>
      <p class="text-ink whitespace-pre-line leading-relaxed">
        <template v-for="(part, i) in answerParts" :key="i">
          <template v-if="part.kind === 'text'">{{ part.text }}</template>
          <sup v-else class="mx-0.5">
            <a
              :href="`#citation-${part.index}`"
              class="text-primary font-semibold no-underline hover:underline"
              >[{{ part.index }}]</a
            >
          </sup>
        </template>
      </p>

      <div v-if="citations.length > 0" class="mt-6 pt-4 border-t border-border">
        <p class="text-sm font-medium text-ink-muted mb-2">Sources</p>
        <div class="space-y-2">
          <!--
            `index` must match the token the LLM emits in the answer body
            (`QaService` uses the DB item id there). Using `idx + 1` broke
            alignment whenever the LLM did not cite item #1 (giiken#93):
            a body `[2]` pointed at Sources card `#citation-1` after the
            list was trimmed to only-cited-items.
          -->
          <CitationCard
            v-for="citation in citations"
            :key="citation.itemId"
            :index="citation.itemId"
            :citation="citation"
            :community-slug="communitySlug"
          />
        </div>
      </div>
    </div>
  </div>
</template>
