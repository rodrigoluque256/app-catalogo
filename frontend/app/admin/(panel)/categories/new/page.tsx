import type { Metadata } from 'next'
import { CategoryForm } from '@/components/admin/CategoryForm'
import { PageHeader } from '@/components/admin/ui'
import { adminRequest } from '@/lib/admin/api'
import type { Category } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Nueva categoría' }

export default async function NewCategoryPage() {
  const categories = await adminRequest<Category[]>('admin/categories')

  return (
    <>
      <PageHeader title="Nueva categoría" />
      <CategoryForm category={null} parents={categories.ok ? categories.data : []} />
    </>
  )
}
