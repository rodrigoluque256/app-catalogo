import 'server-only'
import { redirect } from 'next/navigation'
import { cache } from 'react'
import { apiRequest, type ApiRequestOptions, type ApiResult } from '@/lib/server-api'
import { getToken } from './session'
import type { Session } from './types'

export type { ApiResult }

type RequestOptions = Omit<ApiRequestOptions, 'token'> & { token?: string | null }

/** Llama a la API como el usuario del cookie. Un 401 manda a /admin/login (token vencido o revocado). */
export async function adminRequest<T>(path: string, options: RequestOptions = {}): Promise<ApiResult<T>> {
  const token = options.token === undefined ? await getToken() : options.token
  const result = await apiRequest<T>(path, { ...options, token })

  if (!result.ok && result.status === 401 && path !== 'auth/login') redirect('/admin/login?expired=1')

  // La API admin bloquea a quien entró con una contraseña temporal hasta que la cambie.
  if (!result.ok && result.status === 403 && result.code === 'password_change_required') redirect('/admin/account')

  return result
}

/** Una sola llamada a /auth/me por request, aunque la usen el layout y la página. */
export const getSession = cache(async (): Promise<Session | null> => {
  if (!(await getToken())) return null

  const result = await adminRequest<Session>('auth/me')

  return result.ok ? result.data : null
})

export async function requireSession(): Promise<Session> {
  const session = await getSession()

  if (!session) redirect('/admin/login')

  return session
}

export const can = (session: Session, permission: string) => session.permissions.includes(permission)

export function storefrontUrl(slug: string): string {
  return (process.env.STOREFRONT_URL_TEMPLATE ?? 'http://{slug}.localhost:3000').replace('{slug}', slug)
}
