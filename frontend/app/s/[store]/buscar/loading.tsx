import { LoadingRegion, ProductGridSkeleton, Skeleton } from '@/components/Skeleton'

export default function Loading() {
  return (
    <LoadingRegion label="Buscando productos…" className="flex flex-col gap-5">
      <Skeleton className="h-8 w-64" />
      <Skeleton className="h-4 w-24" />
      <ProductGridSkeleton />
    </LoadingRegion>
  )
}
