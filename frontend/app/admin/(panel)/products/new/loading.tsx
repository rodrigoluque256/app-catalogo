import { LoadingRegion } from '@/components/Skeleton'
import { FormSkeleton, PageHeaderSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando producto…">
      <PageHeaderSkeleton />
      <div className="flex max-w-4xl flex-col gap-8">
        <FormSkeleton fields={5} className="" />
        <FormSkeleton fields={1} className="" />
        <FormSkeleton fields={2} className="" />
      </div>
    </LoadingRegion>
  )
}
