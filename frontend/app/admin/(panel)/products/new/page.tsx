import type { Metadata } from 'next'
import { PageHeader } from '@/components/admin/ui'
import { ProductForm } from '@/components/admin/ProductForm'
import { adminRequest } from '@/lib/admin/api'
import { emptyProductForm } from '@/lib/admin/forms'
import type { Category } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Nuevo producto' }

export default async function NewProductPage() {
  const categories = await adminRequest<Category[]>('admin/categories')

  return (
    <>
      <PageHeader title="Nuevo producto" />
      <ProductForm productId={null} initial={emptyProductForm()} categories={categories.ok ? categories.data : []} />
    </>
  )
}
