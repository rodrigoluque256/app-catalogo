import { LoadingRegion, Skeleton } from '@/components/Skeleton'
import { PageHeaderSkeleton, TableSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando productos…">
      <PageHeaderSkeleton action />
      <div className="mb-4 flex flex-wrap gap-2">
        <Skeleton className="h-9 w-full max-w-xs" />
        <Skeleton className="h-9 w-40" />
        <Skeleton className="h-9 w-20" />
      </div>
      <TableSkeleton rows={8} />
    </LoadingRegion>
  )
}
