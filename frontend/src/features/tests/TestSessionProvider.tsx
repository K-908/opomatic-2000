import { type ReactNode, useMemo, useReducer } from 'react'
import { TestSessionContext } from './testSessionContextValue'
import { initialTestSessionState, testSessionReducer } from './testSessionState'

export function TestSessionProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(testSessionReducer, initialTestSessionState)
  const value = useMemo(() => ({
    ...state,
    startTest: (generatedTest: NonNullable<typeof state.generatedTest>) =>
      dispatch({ type: 'start', generatedTest, submissionId: crypto.randomUUID() }),
    selectAnswer: (questionId: number, optionId: number) =>
      dispatch({ type: 'selectAnswer', questionId, optionId }),
    clearAnswer: (questionId: number) => dispatch({ type: 'clearAnswer', questionId }),
    navigateTo: (index: number) => dispatch({ type: 'navigate', index }),
    clearTest: () => dispatch({ type: 'reset' }),
  }), [state])

  return <TestSessionContext.Provider value={value}>{children}</TestSessionContext.Provider>
}
