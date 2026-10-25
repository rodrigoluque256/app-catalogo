import { Skeleton } from '@/components/Skeleton'

// Esqueletos del panel: replican márgenes y bordes de PageHeader, las tablas y los formularios para que no salte el layout.

export function PageHeaderSkeleton({ action = false }: { action?: boolean }) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <Skeleton className="h-8 w-48" />
      {action && <Skeleton className="h-9 w-40" />}
    </div>
  )
}

export function TableSkeleton({ rows = 6, cols = 4 }: { rows?: number; cols?: number }) {
  return (
    <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white">
      <div className="flex gap-6 border-b border-zinc-200 p-3">
        {Array.from({ length: cols }, (_, col) => (
          <Skeleton key={col} className="h-3 flex-1" />
        ))}
      </div>
      {Array.from({ length: rows }, (_, row) => (
        <div key={row} className="flex items-center gap-6 border-b border-zinc-100 p-3 last:border-0">
          <Skeleton className="h-10 w-10 shrink-0 rounded" />
          {Array.from({ length: cols }, (_, col) => (
            <Skeleton key={col} className="h-4 flex-1" />
          ))}
        </div>
      ))}
    </div>
  )
}

export function FormSkeleton({ fields = 4, className = 'max-w-xl' }: { fields?: number; className?: string }) {
  return (
    <div className={`flex flex-col gap-5 rounded-lg border border-zinc-200 bg-white p-5 ${className}`}>
      {Array.from({ length: fields }, (_, index) => (
        <div key={index} className="flex flex-col gap-1">
          <Skeleton className="h-4 w-24" />
          <Skeleton className="h-9" />
        </div>
      ))}
      <Skeleton className="h-9 w-28" />
    </div>
  )
}
