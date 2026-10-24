import 'server-only'
import { redirect } from 'next/navigation'
import { cache } from 'react'
import { getToken } from './session'
import type { FieldErrors, Session } from './types'

const API_URL = process.env.API_URL ?? 'http://localhost:8000'

export type ApiResult<T> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; message: string; errors: FieldErrors }

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  token?: string | null
  /** Devuelve el cuerpo completo ({ data, meta }) en vez de solo `data`: para listados paginados. */
  envelope?: boolean
}

/** Llama a la API como el usuario del cookie. Un 401 manda a /admin/login (token vencido o revocado). */
export async function adminRequest<T>(path: string, options: RequestOptions = {}): Promise<ApiResult<T>> {
  const token = options.token === undefined ? await getToken() : options.token

  let response: Response
  try {
    response = await fetch(`${API_URL}/api/v1/${path}`, {
      method: options.method ?? 'GET',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        ...(options.body !== undefined && { 'Content-Type': 'application/json' }),
        ...(token && { Authorization: `Bearer ${token}` }),
      },
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    })
  } catch {
    return { ok: false, status: 0, message: 'No se pudo conectar con el servidor. Probá de nuevo.', errors: {} }
  }

  if (response.status === 401 && path !== 'auth/login') redirect('/admin/login?expired=1')

  if (response.status === 204) return { ok: true, status: 204, data: undefined as T }

  const body = (await response.json().catch(() => null)) as Record<string, unknown> | null

  // La API admin bloquea a quien entró con una contraseña temporal hasta que la cambie.
  if (response.status === 403 && body?.code === 'password_change_required') redirect('/admin/account')

  // Un 2xx sin JSON válido (ej. un error de PHP impreso como HTML) no es una respuesta de la API.
  if (!response.ok || body === null) {
    return {
      ok: false,
      status: response.status,
      // Un 5xx puede traer detalles internos (excepciones, rutas): nunca se le muestran al usuario.
      message:
        response.status < 500 && typeof body?.message === 'string' ? body.message : 'Ocurrió un error en el servidor. Probá de nuevo en unos minutos.',
      errors: (body?.errors as FieldErrors | undefined) ?? {},
    }
  }

  return { ok: true, status: response.status, data: (options.envelope ? body : (body?.data ?? body)) as T }
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
