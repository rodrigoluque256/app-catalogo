import type { Metadata } from 'next'
import Link from 'next/link'
import { redirect } from 'next/navigation'
import { TwoFactorSetupForm, TwoFactorVerifyForm } from '@/components/platform/TwoFactorForms'
import { platformRequest } from '@/lib/platform/api'
import { getChallengeToken } from '@/lib/platform/session'
import type { TwoFactorSetup } from '@/lib/platform/types'

export const metadata: Metadata = { title: 'Verificación | Plataforma', robots: { index: false } }

export default async function TwoFactorPage({ searchParams }: { searchParams: Promise<{ setup?: string }> }) {
  // Sin desafío vigente (no pasó por la contraseña, o venció) no hay nada que verificar.
  if (!(await getChallengeToken())) redirect('/platform/login?expired=1')

  const { setup } = await searchParams
  let content: React.ReactNode = <TwoFactorVerifyForm />

  if (setup === '1') {
    // Cada visita genera un secreto nuevo sin confirmar: un QR abandonado a mitad de camino no sirve.
    const result = await platformRequest<TwoFactorSetup>('auth/two-factor/setup', { method: 'POST', auth: 'challenge' })

    if (!result.ok) redirect('/platform/two-factor')

    content = <TwoFactorSetupForm qrSvg={result.data.qr_svg} secret={result.data.secret} />
  }

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-zinc-100 px-4 py-8">
      {content}
      <Link href="/platform/login" className="text-sm underline">Volver al inicio de sesión</Link>
    </main>
  )
}
