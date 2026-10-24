import type { Metadata } from 'next'
import { redirect } from 'next/navigation'
import { logoutAction } from '@/app/admin/actions'
import { ChangePasswordForm } from '@/components/admin/ChangePasswordForm'
import { getSession } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Mi cuenta | Panel', robots: { index: false } }

// Fuera del grupo (panel): es la única pantalla accesible mientras la contraseña es temporal.
export default async function AccountPage() {
  const session = await getSession()

  if (!session) redirect('/admin/login')

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-zinc-100 px-4">
      <p className="text-sm text-zinc-600">{session.user.email}</p>
      <ChangePasswordForm forced={session.user.must_change_password} />
      <form action={logoutAction}>
        <button type="submit" className="text-sm underline">Cerrar sesión</button>
      </form>
    </main>
  )
}
