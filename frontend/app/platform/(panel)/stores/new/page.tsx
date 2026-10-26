import type { Metadata } from 'next'
import { PageHeader } from '@/components/admin/ui'
import { CreateStoreForm } from '@/components/platform/CreateStoreForm'
import { storefrontUrl } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Nueva tienda' }

export default function NewStorePage() {
  return (
    <>
      <PageHeader title="Nueva tienda" />
      <CreateStoreForm storefrontTemplate={storefrontUrl('{slug}')} />
    </>
  )
}
