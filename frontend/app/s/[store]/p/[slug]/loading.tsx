import { LoadingRegion, Skeleton } from '@/components/Skeleton'

// Misma estructura que la página de producto: galería a la izquierda, datos y compra a la derecha.
export default function Loading() {
  return (
    <LoadingRegion label="Cargando producto…" className="grid gap-8 md:grid-cols-2">
      <div className="flex flex-col gap-3">
        <Skeleton className="aspect-square rounded-lg" />
        <div className="flex gap-2">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-16 w-16" />
          ))}
        </div>
      </div>

      <div className="flex flex-col gap-5">
        <div className="flex flex-col gap-2">
          <Skeleton className="h-4 w-20" />
          <Skeleton className="h-8 w-3/4" />
        </div>
        <Skeleton className="h-8 w-32" />
        <div className="flex flex-col gap-2">
          <Skeleton className="h-4 w-16" />
          <div className="flex gap-2">
            {Array.from({ length: 4 }, (_, index) => (
              <Skeleton key={index} className="h-9 w-11" />
            ))}
          </div>
        </div>
        <Skeleton className="h-12 rounded-lg" />
        <div className="flex flex-col gap-2">
          <Skeleton className="h-5 w-28" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-2/3" />
        </div>
      </div>
    </LoadingRegion>
  )
}
