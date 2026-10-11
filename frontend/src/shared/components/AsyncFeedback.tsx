interface AsyncFeedbackProps {
  kind: 'loading' | 'error'
  message: string
}

export function AsyncFeedback({ kind, message }: AsyncFeedbackProps) {
  return (
    <p
      className={kind === 'error' ? 'async-feedback inline-alert' : 'async-feedback'}
      role={kind === 'error' ? 'alert' : 'status'}
    >
      {message}
    </p>
  )
}
