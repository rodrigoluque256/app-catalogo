import type { Metadata } from 'next'
import Link from 'next/link'
import { AcceptInvitationForm } from '@/components/admin/AccessForms'
import { adminRequest } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Invitación | Panel', robots: { index: false }, referrer: 'no-referrer' }

type Props = { searchParams: Promise<{ token?: string; email?: string }> }

export default async function InvitationPage({ searchParams }: Props) {
  const { token = '', email = '' } = await searchParams

  // Se valida el link antes de pedir la contraseña (sin consumirlo): así un link vencido lo dice de entrada.
  const check = token && email
    ? await adminRequest<{ store: string }>(`auth/invitation?${new URLSearchParams({ token, email })}`, { token: null })
    : null

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4">
      {check?.ok ? (
        <AcceptInvitationForm email={email} token={token} store={check.data.store} />
      ) : (
        <div role="alert" className="flex max-w-sm flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-6 text-sm">
          <h1 className="text-xl font-semibold">La invitación no es válida</h1>
          <p>El link venció o ya se usó. Pedile a quien te invitó que te la reenvíe.</p>
          <p>Si ya elegiste tu contraseña, <Link href="/admin/login" className="underline">ingresá desde acá</Link>.</p>
        </div>
      )}
    </main>
  )
}
