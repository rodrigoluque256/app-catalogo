'use server'

import { redirect } from 'next/navigation'
import { platformRequest } from '@/lib/platform/api'
import { clearPlatformCookies, clearRecoveryCodes, setChallengeToken, setPlatformToken, setRecoveryCodes } from '@/lib/platform/session'
import type { LoginChallenge, PlatformToken } from '@/lib/platform/types'

export type PlatformLoginState = { error: string | null; email: string }

export async function platformLoginAction(_previous: PlatformLoginState, formData: FormData): Promise<PlatformLoginState> {
  const email = String(formData.get('email') ?? '')
  const result = await platformRequest<LoginChallenge>('auth/login', {
    method: 'POST',
    auth: 'none',
    body: { email, password: String(formData.get('password') ?? '') },
  })

  if (!result.ok) {
    if (result.status === 429) return { error: 'Demasiados intentos. Esperá un minuto y probá de nuevo.', email }
    return { error: result.errors.email?.[0] ?? result.message, email }
  }

  await setChallengeToken(result.data.challenge_token, result.data.expires_in)
  redirect(result.data.status === 'two_factor_setup_required' ? '/platform/two-factor?setup=1' : '/platform/two-factor')
}

export type TwoFactorState = { error: string | null; field: 'code' | 'recovery_code' }

function twoFactorError(result: { status: number; message: string; errors: Record<string, string[]> }, field: TwoFactorState['field']): TwoFactorState {
  const error = result.status === 429 ? 'Demasiados intentos. Esperá un minuto y probá de nuevo.' : (result.errors[field]?.[0] ?? result.message)

  return { error, field }
}

/** Primer código válido: confirma el 2FA y lleva a la página que muestra los códigos de recuperación una sola vez. */
export async function confirmTwoFactorAction(_previous: TwoFactorState, formData: FormData): Promise<TwoFactorState> {
  const result = await platformRequest<PlatformToken>('auth/two-factor/confirm', {
    method: 'POST',
    auth: 'challenge',
    body: { code: String(formData.get('code') ?? '') },
  })

  if (!result.ok) return twoFactorError(result, 'code')

  await setPlatformToken(result.data.token, result.data.expires_in)
  await setRecoveryCodes(result.data.recovery_codes ?? [])
  redirect('/platform/recovery-codes')
}

export async function acknowledgeRecoveryCodesAction(): Promise<void> {
  await clearRecoveryCodes()
  redirect('/platform')
}

export async function verifyTwoFactorAction(_previous: TwoFactorState, formData: FormData): Promise<TwoFactorState> {
  const recovery = String(formData.get('recovery_code') ?? '').trim()
  const field = recovery !== '' ? 'recovery_code' : 'code'
  const result = await platformRequest<PlatformToken>('auth/two-factor/verify', {
    method: 'POST',
    auth: 'challenge',
    body: field === 'recovery_code' ? { recovery_code: recovery } : { code: String(formData.get('code') ?? '') },
  })

  if (!result.ok) return twoFactorError(result, field)

  await setPlatformToken(result.data.token, result.data.expires_in)
  redirect('/platform')
}

export async function platformLogoutAction(): Promise<void> {
  await platformRequest('auth/logout', { method: 'POST' })
  await clearPlatformCookies()
  redirect('/platform/login')
}
