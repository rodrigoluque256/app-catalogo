import { LoadingRegion, ProductGridSkeleton, Skeleton } from '@/components/Skeleton'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando categoría…" className="flex flex-col gap-5">
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-5 w-full max-w-md" />
      </div>
      <ProductGridSkeleton />
    </LoadingRegion>
  )
}
