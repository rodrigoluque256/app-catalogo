import type { Metadata } from 'next'
import { redirect } from 'next/navigation'
import { RecoveryCodes } from '@/components/platform/RecoveryCodes'
import { requirePlatformSession } from '@/lib/platform/api'
import { getRecoveryCodes } from '@/lib/platform/session'

export const metadata: Metadata = { title: 'Códigos de recuperación | Plataforma', robots: { index: false } }

export default async function RecoveryCodesPage() {
  await requirePlatformSession()
  const codes = await getRecoveryCodes()

  if (!codes) redirect('/platform')

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4 py-8">
      <RecoveryCodes codes={codes} />
    </main>
  )
}
