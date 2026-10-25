import { LoadingRegion } from '@/components/Skeleton'
import { FormSkeleton, PageHeaderSkeleton, TableSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando usuarios…">
      <PageHeaderSkeleton />
      <div className="flex flex-col gap-6">
        <FormSkeleton fields={2} className="" />
        <TableSkeleton rows={3} cols={3} />
      </div>
    </LoadingRegion>
  )
}
