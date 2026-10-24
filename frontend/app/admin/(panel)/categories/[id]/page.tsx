import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { deleteCategoryAction } from '@/app/admin/actions'
import { CategoryForm } from '@/components/admin/CategoryForm'
import { DeleteButton } from '@/components/admin/DeleteButton'
import { PageHeader } from '@/components/admin/ui'
import { adminRequest } from '@/lib/admin/api'
import type { Category } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Editar categoría' }

export default async function EditCategoryPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  const [category, categories] = await Promise.all([
    adminRequest<Category>(`admin/categories/${encodeURIComponent(id)}`),
    adminRequest<Category[]>('admin/categories'),
  ])

  if (!category.ok) {
    if (category.status === 404) notFound()
    return <p role="alert" className="text-red-600">{category.message}</p>
  }

  return (
    <>
      <PageHeader title={category.data.name}>
        <DeleteButton
          confirmText="¿Eliminar esta categoría? Sus productos quedarán sin categoría y sus subcategorías pasarán a principales."
          action={deleteCategoryAction.bind(null, category.data.id)}
        />
      </PageHeader>
      <CategoryForm category={category.data} parents={categories.ok ? categories.data : []} />
    </>
  )
}
