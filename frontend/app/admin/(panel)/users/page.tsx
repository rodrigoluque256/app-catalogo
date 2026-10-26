import type { Metadata } from 'next'
import { PageHeader } from '@/components/admin/ui'
import { UsersManager } from '@/components/admin/UsersManager'
import { adminRequest, can, requireSession } from '@/lib/admin/api'
import type { AdminUser } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Usuarios' }

export default async function UsersPage() {
  const session = await requireSession()

  if (!can(session, 'users.manage')) {
    return <p className="rounded-lg border border-zinc-200 bg-white p-6">Solo el dueño de la tienda puede administrar usuarios.</p>
  }

  const result = await adminRequest<AdminUser[]>('admin/users')

  if (!result.ok) return <p role="alert" className="text-red-600">{result.message}</p>

  return (
    <>
      <PageHeader title="Usuarios" />
      <UsersManager users={result.data} maxUsers={session.plan.limits.users} planName={session.plan.name} />
    </>
  )
}
