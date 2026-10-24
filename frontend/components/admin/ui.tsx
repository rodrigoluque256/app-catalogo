import type { FieldErrors } from '@/lib/admin/types'

export const inputClass =
  'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900 disabled:bg-zinc-100'

export const buttonClass =
  'inline-flex items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50'

export const secondaryButtonClass =
  'inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50'

export function Field({
  label, htmlFor, error, hint, children,
}: { label: string; htmlFor: string; error?: string | undefined; hint?: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={htmlFor} className="text-sm font-medium">
        {label}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-zinc-500">{hint}</p>}
      {error && (
        <p role="alert" className="text-xs text-red-600">
          {error}
        </p>
      )}
    </div>
  )
}

/** Mensaje general + errores de campos que el formulario no muestra al lado de su input. */
export function FormErrors({ message, errors, shown }: { message: string | null; errors: FieldErrors; shown: string[] }) {
  if (!message) return null

  const others = Object.entries(errors).filter(([key]) => !shown.includes(key) && !shown.some((prefix) => key.startsWith(`${prefix}.`)))

  return (
    <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
      <p className="font-medium">{message}</p>
      {others.length > 0 && (
        <ul className="mt-1 list-disc pl-5">
          {others.map(([key, messages]) => (
            <li key={key}>{messages[0]}</li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function PageHeader({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-semibold">{title}</h1>
      {children}
    </div>
  )
}

export function StatusBadge({ status }: { status: 'draft' | 'active' | 'archived' }) {
  const styles = {
    active: 'bg-green-100 text-green-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-zinc-200 text-zinc-700',
  }
  const labels = { active: 'Activo', draft: 'Borrador', archived: 'Archivado' }

  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles[status]}`}>{labels[status]}</span>
}
