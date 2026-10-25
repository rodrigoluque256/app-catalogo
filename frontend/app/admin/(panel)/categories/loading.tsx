import { LoadingRegion } from '@/components/Skeleton'
import { PageHeaderSkeleton, TableSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando categorías…">
      <PageHeaderSkeleton action />
      <div className="max-w-2xl">
        <TableSkeleton rows={6} cols={2} />
      </div>
    </LoadingRegion>
  )
}
