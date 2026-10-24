import type { Metadata } from 'next'
import Link from 'next/link'
import { buttonClass, PageHeader } from '@/components/admin/ui'
import { adminRequest } from '@/lib/admin/api'
import type { Category } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Categorías' }

/** Ordena la lista plana como árbol (padre seguido de sus hijas) y calcula la profundidad. */
function asTree(categories: Category[]): { category: Category; depth: number }[] {
  const byParent = new Map<string | null, Category[]>()
  const ids = new Set(categories.map((category) => category.id))

  for (const category of categories) {
    const parent = category.parent_id !== null && ids.has(category.parent_id) ? category.parent_id : null
    byParent.set(parent, [...(byParent.get(parent) ?? []), category])
  }

  const rows: { category: Category; depth: number }[] = []
  const visit = (parent: string | null, depth: number) => {
    for (const category of byParent.get(parent) ?? []) {
      rows.push({ category, depth })
      visit(category.id, depth + 1)
    }
  }
  visit(null, 0)

  return rows
}

export default async function CategoriesPage() {
  const result = await adminRequest<Category[]>('admin/categories')

  if (!result.ok) return <p role="alert" className="text-red-600">{result.message}</p>

  const rows = asTree(result.data)

  return (
    <>
      <PageHeader title="Categorías">
        <Link href="/admin/categories/new" className={buttonClass}>＋ Nueva categoría</Link>
      </PageHeader>

      {rows.length === 0 ? (
        <p className="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-zinc-500">Todavía no creaste categorías.</p>
      ) : (
        <ul className="max-w-2xl divide-y divide-zinc-100 rounded-lg border border-zinc-200 bg-white">
          {rows.map(({ category, depth }) => (
            <li key={category.id} className="flex items-center justify-between gap-3 p-3" style={{ paddingLeft: `${0.75 + depth * 1.5}rem` }}>
              <div>
                <Link href={`/admin/categories/${category.id}`} className="font-medium hover:underline">{category.name}</Link>
                <span className="ml-2 text-xs text-zinc-500">/{category.slug}</span>
                {!category.is_active && <span className="ml-2 rounded bg-zinc-200 px-1.5 py-0.5 text-xs">Oculta</span>}
              </div>
              <Link href={`/admin/categories/${category.id}`} className="text-sm underline">Editar</Link>
            </li>
          ))}
        </ul>
      )}
    </>
  )
}
