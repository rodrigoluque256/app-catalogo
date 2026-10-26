import { afterEach, describe, expect, it, vi } from 'vitest'
import { apiRequest } from '@/lib/server-api'

const respond = (status: number, body: string | null, contentType = 'application/json') =>
  vi.stubGlobal('fetch', vi.fn(async () => new Response(body, { status, headers: { 'Content-Type': contentType } })))

afterEach(() => vi.unstubAllGlobals())

describe('apiRequest', () => {
  it('sends the bearer token and JSON body, and unwraps data', async () => {
    respond(200, JSON.stringify({ data: { id: 1 } }))

    const result = await apiRequest<{ id: number }>('platform/auth/me', { method: 'POST', body: { a: 1 }, token: 'tok' })

    expect(result).toEqual({ ok: true, status: 200, data: { id: 1 } })
    const [url, init] = vi.mocked(fetch).mock.calls[0]!
    expect(url).toMatch(/\/api\/v1\/platform\/auth\/me$/)
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer tok')
    expect(init?.body).toBe('{"a":1}')
  })

  it('keeps the envelope when asked (paginated lists)', async () => {
    respond(200, JSON.stringify({ data: [], meta: { total: 0 } }))

    expect(await apiRequest('x', { token: null, envelope: true })).toMatchObject({ ok: true, data: { data: [], meta: { total: 0 } } })
  })

  it('passes validation errors and the API error code through', async () => {
    respond(403, JSON.stringify({ message: 'Cambiá tu contraseña.', code: 'password_change_required' }))

    expect(await apiRequest('x', { token: 't' })).toEqual({
      ok: false, status: 403, message: 'Cambiá tu contraseña.', errors: {}, code: 'password_change_required',
    })
  })

  it('never shows the message of a 5xx', async () => {
    respond(500, JSON.stringify({ message: 'SQLSTATE[42P01] relation does not exist' }))

    const result = await apiRequest('x', { token: null })

    expect(result.ok).toBe(false)
    expect(!result.ok && result.message).toBe('Ocurrió un error en el servidor. Probá de nuevo en unos minutos.')
  })

  it('treats a 2xx without valid JSON as an error', async () => {
    respond(200, '<b>Fatal error</b>', 'text/html')

    expect(await apiRequest('x', { token: null })).toMatchObject({ ok: false, status: 200 })
  })

  it('returns 204 as success without a body and a network failure as status 0', async () => {
    respond(204, null)
    expect(await apiRequest('x', { token: null })).toEqual({ ok: true, status: 204, data: undefined })

    vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('fetch failed') }))
    expect(await apiRequest('x', { token: null })).toMatchObject({ ok: false, status: 0 })
  })
})
