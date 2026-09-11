// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it } from 'vitest'
import App from './App'

describe('billing application entry point', () => {
  beforeEach(() => localStorage.clear())

  it('presents the billing operations sign-in surface', () => {
    render(<App />)

    expect(screen.getByText('1wan')).toBeTruthy()
    expect(screen.getByText('Billing operations')).toBeTruthy()
    expect((screen.getByLabelText('Password') as HTMLInputElement).value).toBe('')
    expect(screen.getByText('Enter your email and password to continue.')).toBeTruthy()
    const signInButton = screen.getByRole('button', { name: /sign in with email/i })
    expect(signInButton).toBeTruthy()
    expect(signInButton.getAttribute('type')).toBe('submit')
  })
})
