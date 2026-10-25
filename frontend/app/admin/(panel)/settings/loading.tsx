import { LoadingRegion } from '@/components/Skeleton'
import { FormSkeleton, PageHeaderSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando configuración…">
      <PageHeaderSkeleton />
      <FormSkeleton fields={6} />
    </LoadingRegion>
  )
}
