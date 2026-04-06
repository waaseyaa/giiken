<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import ReportCard from '@/Components/ReportCard.vue'
import type { Community } from '@/types'

defineProps<{
  community: Community
  reportTypes: string[]
}>()

const reports = [
  { type: 'governance_summary', title: 'Governance Summary', description: 'Overview of governance knowledge items with status and gaps.' },
  { type: 'language_report', title: 'Language Report', description: 'Language preservation items, coverage by dialect and domain.' },
  { type: 'land_brief', title: 'Land Brief', description: 'Land-related knowledge items with geographic and treaty context.' },
]

function generateReport(type: string) {
  alert(`Report generation for ${type} will be wired in the next iteration.`)
}
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Reports</h1>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-8">
      <ReportCard
        v-for="report in reports"
        :key="report.type"
        :title="report.title"
        :description="report.description"
        :type="report.type"
        @generate="generateReport"
      />
    </div>
  </ManagementLayout>
</template>
