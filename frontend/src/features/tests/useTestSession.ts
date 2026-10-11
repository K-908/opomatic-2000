import { useContext } from 'react'
import { TestSessionContext } from './testSessionContextValue'

export function useTestSession() {
  return useContext(TestSessionContext)
}
