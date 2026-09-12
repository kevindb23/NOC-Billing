// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { useState } from 'react'
import { beforeEach, describe, expect, it } from 'vitest'
import { ConfirmProvider, useConfirm } from './ConfirmProvider'

function Harness() {
  const confirm = useConfirm()
  const [result, setResult] = useState('')
  return <><button type="button" onClick={() => void confirm({ title: 'Delete role?', description: 'This cannot be undone.', confirmLabel: 'Delete', destructive: true }).then(value => setResult(value ? 'Confirmed' : 'Cancelled'))}>Ask</button><output aria-label="confirmation result">{result}</output></>
}

describe('ConfirmProvider', () => {
  beforeEach(() => cleanup())

  it('resolves false when the confirmation is cancelled', async () => {
    render(<ConfirmProvider><Harness /></ConfirmProvider>)

    fireEvent.click(screen.getByRole('button', { name: 'Ask' }))
    expect(screen.getByRole('dialog')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(await screen.findByText('Cancelled')).toBeTruthy()
  })

  it('resolves true when the destructive action is confirmed', async () => {
    render(<ConfirmProvider><Harness /></ConfirmProvider>)

    fireEvent.click(screen.getByRole('button', { name: 'Ask' }))
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    expect(await screen.findByText('Confirmed')).toBeTruthy()
  })
})
