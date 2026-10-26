import type { Metadata } from 'next'
import { redirect } from 'next/navigation'
import { PlatformLoginForm } from '@/components/platform/PlatformLoginForm'
import { getPlatformSession } from '@/lib/platform/api'

export const metadata: Metadata = { title: 'Ingresar | Plataforma', robots: { index: false } }

export default async function PlatformLoginPage({ searchParams }: { searchParams: Promise<{ expired?: string }> }) {
  const { expired } = await searchParams

  if (!expired && (await getPlatformSession())) redirect('/platform')

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4">
      <PlatformLoginForm expired={expired === '1'} />
    </main>
  )
}
