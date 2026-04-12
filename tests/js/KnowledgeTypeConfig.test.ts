import { describe, it, expect } from 'vitest'
import { KNOWLEDGE_TYPES, KNOWLEDGE_TYPE_CONFIG } from '@/types'

describe('KNOWLEDGE_TYPE_CONFIG', () => {
  it('has an entry for every member of KNOWLEDGE_TYPES', () => {
    const configKeys = Object.keys(KNOWLEDGE_TYPE_CONFIG).sort()
    const typesList = [...KNOWLEDGE_TYPES].sort()
    expect(configKeys).toEqual(typesList)
  })

  it('every config entry has required style properties', () => {
    for (const type of KNOWLEDGE_TYPES) {
      const entry = KNOWLEDGE_TYPE_CONFIG[type]
      expect(entry).toHaveProperty('label')
      expect(entry).toHaveProperty('chip')
      expect(entry).toHaveProperty('activeChip')
      expect(entry).toHaveProperty('dot')
    }
  })

  const EXPECTED_FUTURE = new Set(['synthesis'])

  it('every PHP KnowledgeType case is in KNOWLEDGE_TYPES or EXPECTED_FUTURE', async () => {
    const fs = await import('node:fs/promises')
    const path = await import('node:path')
    const phpPath = path.resolve(__dirname, '../../src/Entity/KnowledgeItem/KnowledgeType.php')
    const phpSource = await fs.readFile(phpPath, 'utf-8')
    const caseRegex = /case\s+\w+\s*=\s*'(\w+)'/g
    const phpCases: string[] = []
    let match: RegExpExecArray | null
    while ((match = caseRegex.exec(phpSource)) !== null) {
      phpCases.push(match[1])
    }

    expect(phpCases.length).toBeGreaterThan(0)

    const tsSet = new Set<string>(KNOWLEDGE_TYPES)
    for (const phpCase of phpCases) {
      const inTs = tsSet.has(phpCase)
      const inFuture = EXPECTED_FUTURE.has(phpCase)
      expect(
        inTs || inFuture,
        `PHP case '${phpCase}' is not in KNOWLEDGE_TYPES or EXPECTED_FUTURE`,
      ).toBe(true)
    }
  })

  it('EXPECTED_FUTURE has no members already in KNOWLEDGE_TYPES', () => {
    const tsSet = new Set<string>(KNOWLEDGE_TYPES)
    for (const futureType of EXPECTED_FUTURE) {
      expect(
        tsSet.has(futureType),
        `'${futureType}' is in both EXPECTED_FUTURE and KNOWLEDGE_TYPES — remove it from EXPECTED_FUTURE`,
      ).toBe(false)
    }
  })
})
