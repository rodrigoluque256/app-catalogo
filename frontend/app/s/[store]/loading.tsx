import { LoadingRegion, ProductGridSkeleton, Skeleton } from '@/components/Skeleton'

export default function Loading() {
  return (
    <LoadingRegion label="Cargando productos…">
      <Skeleton className="mb-3 h-7 w-32" />
      <ProductGridSkeleton />
    </LoadingRegion>
  )
}
