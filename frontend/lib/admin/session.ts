import 'server-only'
import { cookies } from 'next/headers'

export const TOKEN_COOKIE = 'admin_token'

export async function getToken(): Promise<string | null> {
  return (await cookies()).get(TOKEN_COOKIE)?.value ?? null
}

/** httpOnly: el token nunca es visible para JavaScript del navegador (el panel es un BFF). */
export async function setToken(token: string, maxAgeSeconds: number): Promise<void> {
  ;(await cookies()).set(TOKEN_COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/admin',
    maxAge: maxAgeSeconds,
  })
}

export async function clearToken(): Promise<void> {
  ;(await cookies()).delete({ name: TOKEN_COOKIE, path: '/admin' })
}
