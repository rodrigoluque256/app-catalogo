import { LoadingRegion, Skeleton } from '@/components/Skeleton'
import { PageHeaderSkeleton } from '@/components/admin/skeletons'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando panel…">
      <PageHeaderSkeleton />
      <div className="mb-8 grid gap-4 sm:grid-cols-3">
        {Array.from({ length: 3 }, (_, index) => (
          <div key={index} className="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-4">
            <Skeleton className="h-9 w-12" />
            <Skeleton className="h-4 w-28" />
          </div>
        ))}
      </div>
      <div className="flex max-w-xl flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-5">
        <Skeleton className="h-5 w-32" />
        {Array.from({ length: 4 }, (_, index) => (
          <Skeleton key={index} className="h-4 w-3/4" />
        ))}
      </div>
    </LoadingRegion>
  )
}
