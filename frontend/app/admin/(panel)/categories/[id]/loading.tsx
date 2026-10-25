import { LoadingRegion } from '@/components/Skeleton'
import { FormSkeleton, PageHeaderSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando categoría…">
      <PageHeaderSkeleton />
      <FormSkeleton fields={4} />
    </LoadingRegion>
  )
}
