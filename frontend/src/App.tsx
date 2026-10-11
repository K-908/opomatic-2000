import { useEffect, useState, type MouseEvent } from 'react'
import { TopicsPage } from './features/topics/TopicsPage'
import { QuestionsPage } from './features/questions/QuestionsPage'
import { QuestionFormPage } from './features/questions/QuestionFormPage'
import { QuestionImportPage } from './features/questions/QuestionImportPage'
import { TestsNewPage } from './features/tests/TestsNewPage'
import { TestRunPage } from './features/tests/TestRunPage'
import { AttemptsPage } from './features/attempts/AttemptsPage'
import { AttemptDetailPage } from './features/attempts/AttemptDetailPage'
import { useTestSession } from './features/tests/useTestSession'
import type { FinishedTest, GeneratedTest } from './features/tests/api/testsApi'
import { HomeDashboard } from './features/statistics/HomeDashboard'
import { StatisticsPage } from './features/statistics/StatisticsPage'
import { LoginPage } from './features/auth/LoginPage'
import { AccessHistoryPage } from './features/auth/AccessHistoryPage'
import { getSession, logout, type AuthSession } from './features/auth/api/authApi'
import { ApiError } from './shared/api/apiClient'
import './App.css'

function App() {
  const [path, setPath] = useState(window.location.pathname)
  const [auth, setAuth] = useState<{ status: 'loading' } | { status: 'anonymous' } | { status: 'authenticated'; session: AuthSession }>({ status: 'loading' })
  const [loggingOut, setLoggingOut] = useState(false)
  const [logoutError, setLogoutError] = useState(false)
  const { generatedTest, startTest, clearTest } = useTestSession()

  useEffect(() => {
    const controller = new AbortController()
    getSession(controller.signal)
      .then((session) => setAuth({ status: 'authenticated', session }))
      .catch(() => {
        if (!controller.signal.aborted) {
          setAuth({ status: 'anonymous' })
        }
      })
    return () => controller.abort()
  }, [])

  useEffect(() => {
    const handlePopState = () => {
      if (path === '/tests/run' && generatedTest) {
        if (!window.confirm('¿Abandonar el test? Perderás todo el progreso.')) {
          window.history.pushState(null, '', '/tests/run')
          return
        }
        clearTest()
      }
      setPath(window.location.pathname)
    }
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [clearTest, generatedTest, path])

  function beginTest(test: GeneratedTest) {
    window.history.pushState(null, '', '/tests/run')
    startTest(test)
    setPath('/tests/run')
  }

  function abandonTest() {
    clearTest()
    window.history.pushState(null, '', '/tests/new')
    setPath('/tests/new')
  }

  function completeTest(result: FinishedTest) {
    clearTest()
    const resultPath = `/attempts/${result.attemptId}`
    window.history.pushState(null, '', resultPath)
    setPath(resultPath)
  }

  function goHome(event: MouseEvent<HTMLAnchorElement>) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

    event.preventDefault()
    if (path === '/tests/run' && generatedTest) {
      if (!window.confirm('¿Abandonar el test? Perderás todo el progreso.')) return
      clearTest()
    }

    window.history.pushState(null, '', '/')
    setPath('/')
  }

  async function signOut() {
    if (path === '/tests/run' && generatedTest &&
        !window.confirm('¿Cerrar sesión? Perderás todo el progreso del test.')) return

    setLoggingOut(true)
    setLogoutError(false)
    try {
      await logout()
      finishLogout()
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) finishLogout()
      else setLogoutError(true)
    } finally {
      setLoggingOut(false)
    }
  }

  function finishLogout() {
    clearTest()
    window.history.replaceState(null, '', '/')
    setPath('/')
    setAuth({ status: 'anonymous' })
  }

  if (auth.status === 'loading') return <main className="auth-loading" aria-label="Comprobando sesión" />
  if (auth.status === 'anonymous') {
    return <LoginPage onAuthenticated={(session) => setAuth({ status: 'authenticated', session })} />
  }

  let page

  if (path === '/tests/new') {
    page = <TestsNewPage onStarted={beginTest} />
  } else if (path === '/tests/run') {
    page = generatedTest
      ? <TestRunPage onFinished={completeTest} onAbandon={abandonTest} />
      : <MissingTestSession onStarted={beginTest} />
  } else {
    const attemptMatch = path.match(/^\/attempts\/(\d+)$/)
    const editMatch = path.match(/^\/questions\/(\d+)\/edit$/)

    if (attemptMatch) page = <AttemptDetailPage attemptId={Number(attemptMatch[1])} />
    else if (path === '/attempts') page = <AttemptsPage />
    else if (path === '/statistics') page = <StatisticsPage />
    else if (path === '/questions/import') page = <QuestionImportPage />
    else if (path === '/questions') page = <QuestionsPage />
    else if (path === '/questions/new') page = <QuestionFormPage />
    else if (editMatch) page = <QuestionFormPage questionId={Number(editMatch[1])} />
    else if (path === '/topics') page = <TopicsPage />
    else if (path === '/accesses') page = <AccessHistoryPage />
    else page = <HomeDashboard />
  }

  return (
    <>
      <header className="site-header">
        <div className="site-header__inner">
          <a className="site-brand" href="/" onClick={goHome} aria-label="OpoMatic-2000, ir al inicio">
            <span className="site-brand__mark" aria-hidden="true">O</span>
            <span>OpoMatic-2000</span>
          </a>
          <nav className="site-account" aria-label="Cuenta y navegación">
            <a href="/accesses">Accesos</a>
            <span>{auth.session.username}</span>
            <button type="button" onClick={signOut} disabled={loggingOut}>{loggingOut ? 'Saliendo…' : 'Cerrar sesión'}</button>
            {logoutError && <span role="alert">No se pudo cerrar la sesión. Inténtalo de nuevo.</span>}
            <a className="site-home-link" href="/" onClick={goHome} aria-current={path === '/' ? 'page' : undefined}>Inicio</a>
          </nav>
        </div>
      </header>
      <div className="site-content">{page}</div>
    </>
  )
}

function MissingTestSession({ onStarted }: { onStarted: (test: GeneratedTest) => void }) {
  useEffect(() => {
    window.history.replaceState(null, '', '/tests/new?notice=lost')
  }, [])
  return <TestsNewPage onStarted={onStarted} lostProgress />
}

export default App
