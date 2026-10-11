import type { GeneratedTest } from './api/testsApi'

export interface TestSessionState {
  generatedTest: GeneratedTest | null
  submissionId: string | null
  currentIndex: number
  answers: Readonly<Record<number, number>>
}

export type TestSessionAction =
  | { type: 'start'; generatedTest: GeneratedTest; submissionId: string }
  | { type: 'selectAnswer'; questionId: number; optionId: number }
  | { type: 'clearAnswer'; questionId: number }
  | { type: 'navigate'; index: number }
  | { type: 'reset' }

export const initialTestSessionState: TestSessionState = {
  generatedTest: null,
  submissionId: null,
  currentIndex: 0,
  answers: {},
}

export function testSessionReducer(
  state: TestSessionState,
  action: TestSessionAction,
): TestSessionState {
  if (action.type === 'start') {
    return {
      generatedTest: action.generatedTest,
      submissionId: action.submissionId,
      currentIndex: 0,
      answers: {},
    }
  }
  if (action.type === 'selectAnswer') {
    return { ...state, answers: { ...state.answers, [action.questionId]: action.optionId } }
  }
  if (action.type === 'clearAnswer') {
    const { [action.questionId]: _removed, ...answers } = state.answers
    return { ...state, answers }
  }
  if (action.type === 'navigate') {
    if (!state.generatedTest || action.index < 0 || action.index >= state.generatedTest.questions.length) {
      return state
    }
    return { ...state, currentIndex: action.index }
  }
  if (action.type === 'reset') return initialTestSessionState
  return state
}
