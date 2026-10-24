import type { Metadata } from 'next'
import { PageHeader } from '@/components/admin/ui'
import { SettingsForm } from '@/components/admin/SettingsForm'
import { can, requireSession } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Mi tienda' }

export default async function SettingsPage() {
  const session = await requireSession()

  if (!can(session, 'store.manage')) {
    return <p className="rounded-lg border border-zinc-200 bg-white p-6">Solo el dueño de la tienda puede cambiar su configuración.</p>
  }

  return (
    <>
      <PageHeader title="Mi tienda" />
      <SettingsForm store={session.store} />
    </>
  )
}
