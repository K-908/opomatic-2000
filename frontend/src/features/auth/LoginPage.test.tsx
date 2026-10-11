import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { login } from './api/authApi'
import { LoginPage } from './LoginPage'

vi.mock('./api/authApi', () => ({ login: vi.fn() }))

const loginMock = vi.mocked(login)

describe('LoginPage', () => {
  beforeEach(() => loginMock.mockReset())

  it('contains only the requested login controls', () => {
    render(<LoginPage onAuthenticated={vi.fn()} />)

    expect(screen.getByLabelText('Nombre de usuario')).toBeVisible()
    expect(screen.getByLabelText('Contraseña')).toBeVisible()
    expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeVisible()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('authenticates and reports invalid credentials without exposing details', async () => {
    const onAuthenticated = vi.fn()
    loginMock.mockRejectedValueOnce(new ApiError({ title: 'Unauthorized', status: 401 }))
    render(<LoginPage onAuthenticated={onAuthenticated} />)

    fireEvent.change(screen.getByLabelText('Nombre de usuario'), { target: { value: 'Gogloglo' } })
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'incorrecta' } })
    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Usuario o contraseña incorrectos')
    expect(onAuthenticated).not.toHaveBeenCalled()
  })
})
