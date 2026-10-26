import type { Metadata } from 'next'
import { ForgotPasswordForm } from '@/components/admin/AccessForms'

export const metadata: Metadata = { title: 'Recuperar contraseña | Panel', robots: { index: false } }

export default function ForgotPasswordPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4">
      <ForgotPasswordForm />
    </main>
  )
}
