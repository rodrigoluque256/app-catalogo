'use client'

import { useRouter } from 'next/navigation'
import { useState, useTransition } from 'react'
import { deleteStoreAction, restoreStoreAction, setStoreStatusAction } from '@/app/platform/actions'
import { DeleteButton } from '@/components/admin/DeleteButton'
import { Field, inputClass, secondaryButtonClass } from '@/components/admin/ui'
import type { PlatformStore } from '@/lib/platform/types'

const dangerButtonClass =
  'inline-flex items-center justify-center rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50'

export function StoreActions({ store }: { store: PlatformStore }) {
  if (store.status === 'deleted') {
    return (
      <section aria-labelledby="acciones" className="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-5">
        <h2 id="acciones" className="font-semibold">Acciones</h2>
        <p className="text-sm text-zinc-600">La tienda está borrada. Al restaurarla vuelve <strong>suspendida</strong>: revisala y reactivala cuando corresponda.</p>
        <div>
          <DeleteButton
            label="Restaurar tienda" pendingLabel="Restaurando…" className={secondaryButtonClass}
            confirmText={`¿Restaurar “${store.name}”? Va a quedar suspendida.`}
            action={restoreStoreAction.bind(null, store.id)}
          />
        </div>
      </section>
    )
  }

  const suspend = store.status === 'active'

  return (
    <>
      <section aria-labelledby="estado" className="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-5">
        <h2 id="estado" className="font-semibold">{suspend ? 'Suspender' : 'Reactivar'}</h2>
        <p className="text-sm text-zinc-600">
          {suspend
            ? 'La tienda deja de verse al instante y sus usuarios no pueden entrar al panel (se cierran sus sesiones). No se borra nada.'
            : 'La tienda vuelve a verse y sus usuarios pueden entrar de nuevo.'}
        </p>
        <div>
          <DeleteButton
            label={suspend ? 'Suspender tienda' : 'Reactivar tienda'}
            pendingLabel={suspend ? 'Suspendiendo…' : 'Reactivando…'}
            className={suspend ? dangerButtonClass : secondaryButtonClass}
            confirmText={suspend ? `¿Suspender “${store.name}”? Deja de verse ya mismo.` : `¿Reactivar “${store.name}”?`}
            action={setStoreStatusAction.bind(null, store.id, suspend ? 'suspended' : 'active')}
          />
        </div>
      </section>
      <DeleteStore store={store} />
    </>
  )
}

/** Borrar exige escribir el subdominio: es la acción más destructiva del panel. */
function DeleteStore({ store }: { store: PlatformStore }) {
  const router = useRouter()
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()
  const matches = confirm.trim().toLowerCase() === store.slug

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)

    startTransition(async () => {
      const result = await deleteStoreAction(store.id, confirm)

      if (result.ok) router.push('/platform/stores?status=deleted')
      else setError(result.errors.confirm_slug?.[0] ?? result.message)
    })
  }

  return (
    <form onSubmit={submit} aria-labelledby="borrar" className="flex flex-col gap-3 rounded-lg border border-red-200 bg-white p-5">
      <h2 id="borrar" className="font-semibold text-red-800">Borrar tienda</h2>
      <p className="text-sm text-zinc-600">
        Deja de verse y nadie puede entrar. Los datos se conservan y se puede restaurar; el subdominio queda ocupado.
      </p>
      <Field label={`Escribí “${store.slug}” para confirmar`} htmlFor="confirm_slug" error={error ?? undefined}>
        <input id="confirm_slug" autoComplete="off" className={`${inputClass} max-w-xs font-mono`} value={confirm} onChange={(e) => setConfirm(e.target.value)} />
      </Field>
      <div>
        <button type="submit" disabled={!matches || pending} className={dangerButtonClass}>{pending ? 'Borrando…' : 'Borrar tienda'}</button>
      </div>
    </form>
  )
}
