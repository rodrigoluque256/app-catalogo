import 'server-only'
import type { FieldErrors } from './admin/types'

const API_URL = process.env.API_URL ?? 'http://localhost:8000'

export type ApiResult<T> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; message: string; errors: FieldErrors; code?: string }

export type ApiRequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  token: string | null
  /** Devuelve el cuerpo completo ({ data, meta }) en vez de solo `data`: para listados paginados. */
  envelope?: boolean
}

/**
 * Llamada a la API desde el servidor (panel de tiendas y super panel). Normaliza errores y nunca deja
 * pasar mensajes internos; qué hacer ante un 401 o un 403 lo decide cada panel (ver adminRequest, platformRequest).
 */
export async function apiRequest<T>(path: string, options: ApiRequestOptions): Promise<ApiResult<T>> {
  let response: Response
  try {
    response = await fetch(`${API_URL}/api/v1/${path}`, {
      method: options.method ?? 'GET',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        ...(options.body !== undefined && { 'Content-Type': 'application/json' }),
        ...(options.token && { Authorization: `Bearer ${options.token}` }),
      },
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    })
  } catch {
    return { ok: false, status: 0, message: 'No se pudo conectar con el servidor. Probá de nuevo.', errors: {} }
  }

  if (response.status === 204) return { ok: true, status: 204, data: undefined as T }

  const body = (await response.json().catch(() => null)) as Record<string, unknown> | null

  // Un 2xx sin JSON válido (ej. un error de PHP impreso como HTML) no es una respuesta de la API.
  if (!response.ok || body === null) {
    return {
      ok: false,
      status: response.status,
      // Un 5xx puede traer detalles internos (excepciones, rutas): nunca se le muestran al usuario.
      message:
        response.status < 500 && typeof body?.message === 'string' ? body.message : 'Ocurrió un error en el servidor. Probá de nuevo en unos minutos.',
      errors: (body?.errors as FieldErrors | undefined) ?? {},
      ...(typeof body?.code === 'string' && { code: body.code }),
    }
  }

  return { ok: true, status: response.status, data: (options.envelope ? body : (body?.data ?? body)) as T }
}
