'use client'

import { useState, useTransition } from 'react'
import type { ActionResult } from '@/lib/admin/types'

type Props = { label?: string; confirmText: string; action: () => Promise<ActionResult | void>; className?: string }

export function DeleteButton({ label = 'Eliminar', confirmText, action, className = 'text-sm text-red-600 hover:underline' }: Props) {
  const [pending, startTransition] = useTransition()
  const [error, setError] = useState<string | null>(null)

  function run() {
    if (!window.confirm(confirmText)) return

    setError(null)
    startTransition(async () => {
      const result = await action()

      if (result && !result.ok) setError(result.message)
    })
  }

  return (
    <>
      <button type="button" onClick={run} disabled={pending} className={className}>
        {pending ? 'Eliminando…' : label}
      </button>
      {error && <span role="alert" className="ml-2 text-xs text-red-600">{error}</span>}
    </>
  )
}
