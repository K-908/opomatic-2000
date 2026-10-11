import { createContext } from 'react'
import type { GeneratedTest } from './api/testsApi'
import { initialTestSessionState } from './testSessionState'

export interface TestSessionContextValue {
  generatedTest: GeneratedTest | null
  submissionId: string | null
  currentIndex: number
  answers: Readonly<Record<number, number>>
  startTest: (test: GeneratedTest) => void
  selectAnswer: (questionId: number, optionId: number) => void
  clearAnswer: (questionId: number) => void
  navigateTo: (index: number) => void
  clearTest: () => void
}

export const TestSessionContext = createContext<TestSessionContextValue>({
  ...initialTestSessionState,
  startTest: () => undefined,
  selectAnswer: () => undefined,
  clearAnswer: () => undefined,
  navigateTo: () => undefined,
  clearTest: () => undefined,
})
