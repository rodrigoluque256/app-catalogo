import type { Metadata } from 'next'
import { redirect } from 'next/navigation'
import { LoginForm } from '@/components/admin/LoginForm'
import { getSession } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Ingresar | Panel', robots: { index: false } }

export default async function LoginPage({ searchParams }: { searchParams: Promise<{ expired?: string; reset?: string }> }) {
  const { expired, reset } = await searchParams

  // Con sesión válida no tiene sentido ver el login; con una vencida (expired=1) sí.
  if (!expired && (await getSession())) redirect('/admin')

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4">
      <LoginForm expired={expired === '1'} reset={reset === '1'} />
    </main>
  )
}
