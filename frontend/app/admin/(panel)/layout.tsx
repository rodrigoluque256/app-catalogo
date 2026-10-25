import type { Metadata } from 'next'
import { redirect } from 'next/navigation'
import { logoutAction } from '@/app/admin/actions'
import { AdminNav, type NavItem } from '@/components/admin/AdminNav'
import { SkipLink } from '@/components/SkipLink'
import { can, requireSession, storefrontUrl } from '@/lib/admin/api'

export const metadata: Metadata = { title: { default: 'Panel', template: '%s | Panel' }, robots: { index: false } }

export default async function PanelLayout({ children }: { children: React.ReactNode }) {
  const session = await requireSession()

  // Contraseña temporal: nada del panel funciona hasta que la cambie (la API también lo exige).
  if (session.user.must_change_password) redirect('/admin/account')

  const items: NavItem[] = [
    { href: '/admin', label: 'Inicio' },
    ...(can(session, 'products.manage')
      ? [{ href: '/admin/products', label: 'Productos' }, { href: '/admin/categories', label: 'Categorías' }]
      : []),
    ...(can(session, 'store.manage') ? [{ href: '/admin/settings', label: 'Mi tienda' }] : []),
    ...(can(session, 'users.manage') ? [{ href: '/admin/users', label: 'Usuarios' }] : []),
    { href: '/admin/account', label: 'Mi cuenta' },
  ]

  return (
    <div className="flex min-h-screen flex-col bg-zinc-50 md:flex-row">
      <SkipLink />
      <aside className="flex flex-col gap-4 border-b border-zinc-200 bg-white p-4 md:min-h-screen md:w-56 md:border-b-0 md:border-r">
        <div>
          <p className="truncate font-semibold">{session.store.name}</p>
          <a href={storefrontUrl(session.store.slug)} target="_blank" rel="noreferrer" className="text-xs text-zinc-500 underline">
            Ver tienda <span aria-hidden="true">↗</span>
            <span className="sr-only"> (se abre en otra pestaña)</span>
          </a>
        </div>
        <AdminNav items={items} />
        <div className="mt-auto hidden flex-col gap-1 text-xs text-zinc-500 md:flex">
          <span className="truncate">{session.user.email}</span>
          <form action={logoutAction}>
            <button type="submit" className="underline hover:text-zinc-900">Cerrar sesión</button>
          </form>
        </div>
      </aside>

      <main id="contenido" tabIndex={-1} className="flex-1 p-4 outline-none md:p-8">{children}</main>

      <form action={logoutAction} className="border-t border-zinc-200 p-4 text-center text-xs md:hidden">
        <button type="submit" className="underline">Cerrar sesión ({session.user.email})</button>
      </form>
    </div>
  )
}
