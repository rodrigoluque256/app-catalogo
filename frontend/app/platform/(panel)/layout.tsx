import type { Metadata } from 'next'
import { platformLogoutAction } from '@/app/platform/actions'
import { AdminNav } from '@/components/admin/AdminNav'
import { SkipLink } from '@/components/SkipLink'
import { requirePlatformSession } from '@/lib/platform/api'

export const metadata: Metadata = { title: { default: 'Plataforma', template: '%s | Plataforma' }, robots: { index: false } }

export default async function PlatformLayout({ children }: { children: React.ReactNode }) {
  const session = await requirePlatformSession()

  return (
    <div className="flex min-h-screen flex-col bg-zinc-50 md:flex-row">
      <SkipLink />
      <aside className="flex flex-col gap-4 border-b border-zinc-200 bg-white p-4 md:min-h-screen md:w-56 md:border-b-0 md:border-r">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2">
            <span className="rounded bg-zinc-900 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">Plataforma</span>
            <span className="text-sm text-zinc-600">Super panel</span>
          </div>
          <div className="flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
            <span className="truncate">{session.admin.email}</span>
            <form action={platformLogoutAction}>
              <button type="submit" className="underline hover:text-zinc-900">Cerrar sesión</button>
            </form>
          </div>
        </div>
        <AdminNav items={[{ href: '/platform', label: 'Inicio' }]} />
      </aside>

      <main id="contenido" tabIndex={-1} className="flex-1 p-4 outline-none md:p-8">{children}</main>
    </div>
  )
}
