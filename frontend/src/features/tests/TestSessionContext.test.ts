import { describe, expect, it } from 'vitest'
import type { GeneratedTest } from './api/testsApi'
import { testSessionReducer } from './testSessionState'

const test = {
  testToken: 'opaque',
  expiresAtUtc: '2026-08-21T00:00:00Z',
  topics: [],
  questions: [],
} satisfies GeneratedTest

describe('testSessionReducer', () => {
  it('selects, changes and clears an answer without mutating previous state', () => {
    const started = testSessionReducer(
      { generatedTest: null, submissionId: null, currentIndex: 0, answers: {} },
      { type: 'start', generatedTest: test, submissionId: 'submission' },
    )
    expect(started.generatedTest).toBe(test)
    const selected = testSessionReducer(started, { type: 'selectAnswer', questionId: 1, optionId: 10 })
    const changed = testSessionReducer(selected, { type: 'selectAnswer', questionId: 1, optionId: 11 })
    const cleared = testSessionReducer(changed, { type: 'clearAnswer', questionId: 1 })
    expect(started.answers).toEqual({})
    expect(selected.answers).toEqual({ 1: 10 })
    expect(changed.answers).toEqual({ 1: 11 })
    expect(cleared.answers).toEqual({})
  })

  it('navigates within bounds and resets the whole session', () => {
    const generatedTest = { ...test, questions: [
      { id: 1, displayOrder: 1, topicId: 1, topicName: 'Tema', statement: 'Uno', options: [] },
      { id: 2, displayOrder: 2, topicId: 1, topicName: 'Tema', statement: 'Dos', options: [] },
    ] }
    const started = testSessionReducer(
      { generatedTest: null, submissionId: null, currentIndex: 0, answers: {} },
      { type: 'start', generatedTest, submissionId: 'submission' },
    )
    const navigated = testSessionReducer(started, { type: 'navigate', index: 1 })
    expect(navigated.currentIndex).toBe(1)
    expect(testSessionReducer(navigated, { type: 'navigate', index: 2 })).toBe(navigated)
    expect(testSessionReducer(navigated, { type: 'reset' })).toEqual({
      generatedTest: null, submissionId: null, currentIndex: 0, answers: {},
    })
  })
})
