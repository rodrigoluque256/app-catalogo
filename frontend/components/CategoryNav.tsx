import Link from 'next/link'
import type { Category } from '@/lib/types'

export function CategoryNav({ categories, activeSlug }: { categories: Category[]; activeSlug?: string }) {
  const roots = categories.filter((category) => category.parent_id === null)

  if (roots.length === 0) return null

  const chip = 'shrink-0 rounded-full border px-3 py-1 text-sm transition-colors'

  return (
    <nav aria-label="Categorías" className="-mx-4 overflow-x-auto px-4">
      <ul className="flex gap-2 pb-1">
        <li>
          <Link
            href="/"
            className={`${chip} ${activeSlug === undefined ? 'border-primary bg-primary text-primary-fg' : 'border-zinc-300 hover:border-zinc-500'}`}
          >
            Todo
          </Link>
        </li>
        {roots.map((category) => (
          <li key={category.id}>
            <Link
              href={`/c/${category.slug}`}
              aria-current={activeSlug === category.slug ? 'page' : undefined}
              className={`${chip} ${activeSlug === category.slug ? 'border-primary bg-primary text-primary-fg' : 'border-zinc-300 hover:border-zinc-500'}`}
            >
              {category.name}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  )
}
