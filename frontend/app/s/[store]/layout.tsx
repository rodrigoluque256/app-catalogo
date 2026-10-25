import type { Metadata } from 'next'
import Image from 'next/image'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import type { CSSProperties } from 'react'
import { CategoryNav } from '@/components/CategoryNav'
import { SearchForm } from '@/components/SearchForm'
import { SkipLink } from '@/components/SkipLink'
import { getCategories, getStore } from '@/lib/api'
import { readableAccent, readableForeground } from '@/lib/color'
import { storeOpenGraph, storeOrigin } from '@/lib/seo'

type Props = { children: React.ReactNode; params: Promise<{ store: string }> }

// Vacío a propósito: no se prerenderiza nada en el build; cada página se genera en la primera visita y se
// cachea (ISR) hasta que el backend pida la revalidación por tag (ver app/api/revalidate).
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Pick<Props, 'params'>): Promise<Metadata> {
  const { store: slug } = await params
  const store = await getStore(slug)

  if (!store) return {}

  return {
    // URLs relativas (canonical, imágenes de OG) se resuelven contra el subdominio, también si se entra por /s/...
    metadataBase: new URL(storeOrigin(slug)),
    title: { default: store.name, template: `%s | ${store.name}` },
    description: `Catálogo de ${store.name}`,
    icons: store.logo_url ? { icon: store.logo_url } : undefined,
    // Sin canonical acá: lo heredarían búsqueda y 404. Cada página indexable declara el suyo.
    openGraph: { ...storeOpenGraph(store), title: store.name, description: `Catálogo de ${store.name}` },
    twitter: { card: 'summary' },
  }
}

export default async function StoreLayout({ children, params }: Props) {
  const { store: slug } = await params
  const [store, categories] = await Promise.all([getStore(slug), getCategories(slug)])

  if (!store) notFound()

  const theme = {
    '--primary': store.theme.primary_color,
    '--primary-fg': readableForeground(store.theme.primary_color),
    '--accent': store.theme.accent_color,
    '--accent-fg': readableForeground(store.theme.accent_color),
    '--accent-text': readableAccent(store.theme.accent_color),
  } as CSSProperties

  return (
    <div style={theme} className="flex min-h-screen flex-col">
      <SkipLink />
      <header className="border-b border-zinc-200 bg-white">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
          <Link href="/" className="flex items-center gap-2 font-semibold">
            {store.logo_url && (
              <Image src={store.logo_url} alt="" width={36} height={36} className="h-9 w-9 rounded object-contain" />
            )}
            <span>{store.name}</span>
          </Link>
          <SearchForm />
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl flex-1 px-4 py-5">
        <CategoryNav categories={categories} />
        <main id="contenido" tabIndex={-1} className="mt-5 outline-none">
          {children}
        </main>
      </div>

      <footer className="border-t border-zinc-200 bg-white py-6 text-center text-sm text-zinc-500">
        © {new Date().getFullYear()} {store.name}
      </footer>
    </div>
  )
}
