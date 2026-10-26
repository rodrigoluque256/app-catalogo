import 'server-only'
import { cookies } from 'next/headers'

// Cookies propias del super panel, limitadas a /platform: nunca viajan al panel de tiendas ni al revés.
const TOKEN_COOKIE = 'platform_token'
const CHALLENGE_COOKIE = 'platform_challenge'
const RECOVERY_COOKIE = 'platform_recovery_codes'
const RECOVERY_PATH = '/platform/recovery-codes'

const options = (maxAge: number, path = '/platform') => ({
  httpOnly: true,
  sameSite: 'strict' as const,
  secure: process.env.NODE_ENV === 'production',
  path,
  maxAge,
})

export async function getPlatformToken(): Promise<string | null> {
  return (await cookies()).get(TOKEN_COOKIE)?.value ?? null
}

export async function getChallengeToken(): Promise<string | null> {
  return (await cookies()).get(CHALLENGE_COOKIE)?.value ?? null
}

/** Contraseña correcta, 2FA pendiente: el token de desafío solo sirve para /two-factor (vence en 5 min). */
export async function setChallengeToken(token: string, maxAgeSeconds: number): Promise<void> {
  ;(await cookies()).set(CHALLENGE_COOKIE, token, options(maxAgeSeconds))
}

/** 2FA superado: se descarta el desafío y queda el token de sesión. */
export async function setPlatformToken(token: string, maxAgeSeconds: number): Promise<void> {
  const store = await cookies()
  store.delete({ name: CHALLENGE_COOKIE, path: '/platform' })
  store.set(TOKEN_COOKIE, token, options(maxAgeSeconds))
}

export async function clearPlatformCookies(): Promise<void> {
  const store = await cookies()
  store.delete({ name: TOKEN_COOKIE, path: '/platform' })
  store.delete({ name: CHALLENGE_COOKIE, path: '/platform' })
}

/**
 * Los códigos de recuperación se muestran en su propia página: una Server Action que escribe cookies hace que
 * Next vuelva a renderizar la página actual, y la de 2FA ya no tiene desafío. Viven 10 minutos, solo en esa ruta,
 * y se borran cuando la persona confirma que los guardó.
 */
export async function setRecoveryCodes(codes: string[]): Promise<void> {
  ;(await cookies()).set(RECOVERY_COOKIE, JSON.stringify(codes), options(600, RECOVERY_PATH))
}

export async function getRecoveryCodes(): Promise<string[] | null> {
  const raw = (await cookies()).get(RECOVERY_COOKIE)?.value

  try {
    const codes: unknown = raw ? JSON.parse(raw) : null
    return Array.isArray(codes) && codes.every((code) => typeof code === 'string') ? codes : null
  } catch {
    return null
  }
}

export async function clearRecoveryCodes(): Promise<void> {
  ;(await cookies()).delete({ name: RECOVERY_COOKIE, path: RECOVERY_PATH })
}
