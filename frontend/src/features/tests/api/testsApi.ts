import { apiRequest } from '../../../shared/api/apiClient'

export interface GeneratedTestTopic {
  id: number
  name: string
}

export interface GeneratedTestOption {
  id: number
  displayOrder: number
  text: string
}

export interface GeneratedTestQuestion {
  id: number
  displayOrder: number
  topicId: number
  topicName: string
  statement: string
  options: GeneratedTestOption[]
}

export interface GeneratedTest {
  testToken: string
  expiresAtUtc: string
  topics: GeneratedTestTopic[]
  questions: GeneratedTestQuestion[]
}

export interface FinishTestAnswer {
  questionId: number
  selectedOptionId: number | null
}

export interface FinishedTest {
  attemptId: number
  completedAtUtc: string
  totalQuestions: number
  correctCount: number
  incorrectCount: number
  unansweredCount: number
  score: number
}

export function generateTest(topicIds: number[], numberOfQuestions: number) {
  return apiRequest<GeneratedTest>('/api/tests/generate', {
    method: 'POST',
    body: { topicIds, numberOfQuestions },
  })
}

export function finishTest(
  submissionId: string,
  testToken: string,
  answers: FinishTestAnswer[],
) {
  return apiRequest<FinishedTest>('/api/tests/finish', {
    method: 'POST',
    body: { submissionId, testToken, answers },
  })
}
