'use server'

import { revalidatePath } from 'next/cache'
import { redirect } from 'next/navigation'
import { adminRequest } from '@/lib/admin/api'
import { clearToken, setToken } from '@/lib/admin/session'
import type { ActionResult, CredentialResult, FieldErrors, PresignedUpload } from '@/lib/admin/types'

const failure = (result: { message: string; errors: FieldErrors }): ActionResult => ({
  ok: false,
  message: result.message,
  errors: result.errors,
})

export type LoginState = { error: string | null; email: string }

export async function loginAction(_previous: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get('email') ?? '')
  const result = await adminRequest<{ token: string; expires_in: number }>('auth/login', {
    method: 'POST',
    token: null,
    body: { email, password: String(formData.get('password') ?? '') },
  })

  // React vacía el formulario después de la acción: se devuelve el email para no obligar a reescribirlo.
  if (!result.ok) {
    if (result.status === 429) return { error: 'Demasiados intentos. Esperá un minuto y probá de nuevo.', email }
    return { error: result.errors.email?.[0] ?? result.message, email }
  }

  await setToken(result.data.token, result.data.expires_in)
  redirect('/admin')
}

export async function logoutAction(): Promise<void> {
  await adminRequest('auth/logout', { method: 'POST' })
  await clearToken()
  redirect('/admin/login')
}

/** Se ejecuta en el servidor: el navegador nunca ve el token, solo la URL firmada de R2. */
export async function presignUploadAction(
  purpose: 'product_image' | 'store_logo',
  contentType: string,
  size: number,
): Promise<{ ok: true; upload: PresignedUpload } | { ok: false; message: string }> {
  const result = await adminRequest<PresignedUpload>('admin/uploads/presign', {
    method: 'POST',
    body: { purpose, content_type: contentType, size },
  })

  if (!result.ok) {
    return { ok: false, message: result.errors.content_type?.[0] ?? result.errors.size?.[0] ?? result.message }
  }

  return { ok: true, upload: result.data }
}

export async function saveProductAction(id: string | null, payload: Record<string, unknown>): Promise<ActionResult> {
  const result = await adminRequest(id ? `admin/products/${id}` : 'admin/products', {
    method: id ? 'PUT' : 'POST',
    body: payload,
  })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  redirect('/admin/products')
}

export async function deleteProductAction(id: string): Promise<ActionResult> {
  const result = await adminRequest(`admin/products/${id}`, { method: 'DELETE' })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  return { ok: true }
}

export async function saveCategoryAction(id: string | null, payload: Record<string, unknown>): Promise<ActionResult> {
  const result = await adminRequest(id ? `admin/categories/${id}` : 'admin/categories', {
    method: id ? 'PUT' : 'POST',
    body: payload,
  })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  redirect('/admin/categories')
}

export async function deleteCategoryAction(id: string): Promise<ActionResult> {
  const result = await adminRequest(`admin/categories/${id}`, { method: 'DELETE' })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  redirect('/admin/categories')
}

export async function saveSettingsAction(payload: Record<string, unknown>): Promise<ActionResult> {
  const result = await adminRequest('admin/store', { method: 'PATCH', body: payload })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  return { ok: true }
}

export async function createUserAction(payload: { name: string; email: string; role: string }): Promise<CredentialResult> {
  const result = await adminRequest<{ email: string; temporary_password: string }>('admin/users', { method: 'POST', body: payload })

  if (!result.ok) return failure(result) as CredentialResult

  revalidatePath('/admin/users')
  return { ok: true, temporaryPassword: result.data.temporary_password, email: result.data.email }
}

export async function updateUserAction(id: number, payload: { name: string; role: string }): Promise<ActionResult> {
  const result = await adminRequest(`admin/users/${id}`, { method: 'PUT', body: payload })

  if (!result.ok) return failure(result)

  revalidatePath('/admin/users')
  return { ok: true }
}

export async function deleteUserAction(id: number): Promise<ActionResult> {
  const result = await adminRequest(`admin/users/${id}`, { method: 'DELETE' })

  if (!result.ok) return failure(result)

  revalidatePath('/admin/users')
  return { ok: true }
}

export async function resetUserPasswordAction(id: number, email: string): Promise<CredentialResult> {
  const result = await adminRequest<{ temporary_password: string }>(`admin/users/${id}/reset-password`, { method: 'POST' })

  if (!result.ok) return failure(result) as CredentialResult

  revalidatePath('/admin/users')
  return { ok: true, temporaryPassword: result.data.temporary_password, email }
}

export async function changePasswordAction(payload: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<ActionResult> {
  const result = await adminRequest('auth/password', { method: 'POST', body: payload })

  if (!result.ok) return failure(result)

  revalidatePath('/admin', 'layout')
  redirect('/admin')
}
