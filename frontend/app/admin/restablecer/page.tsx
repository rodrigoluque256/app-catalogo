import type { Metadata } from 'next'
import Link from 'next/link'
import { ResetPasswordForm } from '@/components/admin/AccessForms'

export const metadata: Metadata = { title: 'Restablecer contraseña | Panel', robots: { index: false }, referrer: 'no-referrer' }

type Props = { searchParams: Promise<{ token?: string; email?: string }> }

export default async function ResetPasswordPage({ searchParams }: Props) {
  const { token = '', email = '' } = await searchParams

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4">
      {token && email ? (
        <ResetPasswordForm email={email} token={token} />
      ) : (
        <p role="alert" className="max-w-sm rounded-lg border border-zinc-200 bg-white p-6 text-sm">
          El link está incompleto. <Link href="/admin/recuperar" className="underline">Pedí uno nuevo</Link>.
        </p>
      )}
    </main>
  )
}
