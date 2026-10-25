/** Bloque gris animado. Decorativo: el anuncio para lectores de pantalla lo hace LoadingRegion. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div aria-hidden className={`animate-pulse rounded-md bg-zinc-200 motion-reduce:animate-none ${className}`} />
}

/** Contenedor de un estado de carga: anuncia `label` a lectores de pantalla y oculta los bloques grises. */
export function LoadingRegion({ label, className, children }: { label: string; className?: string; children: React.ReactNode }) {
  return (
    <div role="status" aria-busy="true" aria-live="polite" className={className}>
      <span className="sr-only">{label}</span>
      {children}
    </div>
  )
}

/** Misma grilla que ProductGrid y misma forma que ProductCard, para que no salte el layout al cargar. */
export function ProductGridSkeleton({ count = 8 }: { count?: number }) {
  return (
    <ul aria-hidden className="grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
      {Array.from({ length: count }, (_, index) => (
        <li key={index} className="flex flex-col gap-2">
          <Skeleton className="aspect-square rounded-lg" />
          <Skeleton className="h-4 w-3/4" />
          <Skeleton className="h-4 w-1/3" />
        </li>
      ))}
    </ul>
  )
}
