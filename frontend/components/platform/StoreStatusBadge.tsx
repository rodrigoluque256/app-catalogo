import type { StoreListStatus } from '@/lib/platform/types'

const styles: Record<StoreListStatus, string> = {
  active: 'bg-green-100 text-green-800',
  suspended: 'bg-amber-100 text-amber-800',
  deleted: 'bg-red-100 text-red-800',
}

export const storeStatusLabels: Record<StoreListStatus, string> = { active: 'Activa', suspended: 'Suspendida', deleted: 'Borrada' }

export function StoreStatusBadge({ status }: { status: StoreListStatus }) {
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles[status]}`}>{storeStatusLabels[status]}</span>
}
