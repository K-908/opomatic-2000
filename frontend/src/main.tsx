import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { TestSessionProvider } from './features/tests/TestSessionProvider.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <TestSessionProvider>
      <App />
    </TestSessionProvider>
  </StrictMode>,
)
