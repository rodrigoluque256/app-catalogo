import 'server-only'
import { redirect } from 'next/navigation'
import { cache } from 'react'
import { apiRequest, type ApiRequestOptions, type ApiResult } from '@/lib/server-api'
import { getChallengeToken, getPlatformToken } from './session'
import type { PlatformSession } from './types'

type RequestOptions = Omit<ApiRequestOptions, 'token'> & {
  /** 'session' (por defecto), 'challenge' (paso del 2FA) o ninguno (login). */
  auth?: 'session' | 'challenge' | 'none'
}

/** Llama a la API del super panel. Un 401 (token o desafío vencido) vuelve al login de la plataforma. */
export async function platformRequest<T>(path: string, options: RequestOptions = {}): Promise<ApiResult<T>> {
  const auth = options.auth ?? 'session'
  const token = auth === 'session' ? await getPlatformToken() : auth === 'challenge' ? await getChallengeToken() : null
  const result = await apiRequest<T>(`platform/${path}`, { ...options, token })

  if (!result.ok && result.status === 401 && auth !== 'none') redirect('/platform/login?expired=1')

  return result
}

/** Una sola llamada a /me por request, aunque la usen el layout y la página. */
export const getPlatformSession = cache(async (): Promise<PlatformSession | null> => {
  if (!(await getPlatformToken())) return null

  const result = await platformRequest<PlatformSession>('auth/me')

  return result.ok ? result.data : null
})

export async function requirePlatformSession(): Promise<PlatformSession> {
  const session = await getPlatformSession()

  if (!session) redirect('/platform/login')

  return session
}
