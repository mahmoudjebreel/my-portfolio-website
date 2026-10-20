import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/client'
import { DataError } from '../components/ui/DataStates'
import { I18nProvider } from '../i18n/I18nProvider'

const renderError = (error: ApiError, locale: 'en' | 'ar' = 'en') => {
  const onRetry = vi.fn()
  render(
    <I18nProvider locale={locale}>
      <DataError error={error} onRetry={onRetry} />
    </I18nProvider>,
  )
  return onRetry
}

describe('DataError', () => {
  it.each([
    ['network', new ApiError('network', 'x')],
    ['timeout', new ApiError('timeout', 'x')],
    ['502 from the dev proxy', new ApiError('http', 'x', { status: 502 })],
    ['503', new ApiError('http', 'x', { status: 503 })],
  ])('explains that the server is unreachable (%s)', (_label, error) => {
    renderError(error)
    expect(screen.getByRole('alert')).toHaveTextContent('Unable to load this section.')
  })

  it('distinguishes rate limiting and other failures', () => {
    renderError(new ApiError('rate_limited', 'x', { status: 429 }))
    expect(screen.getByRole('alert')).toHaveTextContent('Too many requests.')
  })

  it('uses a generic message for server errors without leaking details', () => {
    renderError(new ApiError('http', 'SQLSTATE secret', { status: 500 }))
    expect(screen.getByRole('alert')).toHaveTextContent('Something went wrong while loading this section.')
    expect(screen.getByRole('alert')).not.toHaveTextContent('SQLSTATE')
  })

  it('calls retry when the button is pressed', async () => {
    const onRetry = renderError(new ApiError('network', 'x'))
    await userEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(onRetry).toHaveBeenCalledTimes(1)
  })
})
