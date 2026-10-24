import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { PageHeader } from '@/components/admin/ui'
import { ProductForm } from '@/components/admin/ProductForm'
import { adminRequest } from '@/lib/admin/api'
import { productToForm } from '@/lib/admin/forms'
import type { AdminProduct, Category } from '@/lib/admin/types'

export const metadata: Metadata = { title: 'Editar producto' }

export default async function EditProductPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  const [product, categories] = await Promise.all([
    adminRequest<AdminProduct>(`admin/products/${encodeURIComponent(id)}`),
    adminRequest<Category[]>('admin/categories'),
  ])

  if (!product.ok) {
    if (product.status === 404) notFound()
    return <p role="alert" className="text-red-600">{product.message}</p>
  }

  return (
    <>
      <PageHeader title={product.data.name} />
      <ProductForm productId={product.data.id} initial={productToForm(product.data)} categories={categories.ok ? categories.data : []} />
    </>
  )
}
