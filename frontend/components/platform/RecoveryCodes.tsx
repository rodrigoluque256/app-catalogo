'use client'

import { useState } from 'react'
import { acknowledgeRecoveryCodesAction } from '@/app/platform/actions'
import { buttonClass, secondaryButtonClass } from '@/components/admin/ui'

export function RecoveryCodes({ codes }: { codes: string[] }) {
  const [copied, setCopied] = useState(false)

  async function copy() {
    try {
      await navigator.clipboard.writeText(codes.join('\n'))
      setCopied(true)
    } catch {
      setCopied(false) // sin permiso de portapapeles: se pueden seleccionar a mano
    }
  }

  return (
    <div className="flex w-full max-w-md flex-col gap-4 rounded-lg border border-amber-300 bg-amber-50 p-6 text-sm">
      <h1 className="text-xl font-semibold text-zinc-900">Guardá tus códigos de recuperación</h1>
      <p className="text-amber-900">
        La verificación en dos pasos quedó activada. Si perdés el teléfono, cada código te deja entrar <strong>una sola vez</strong>.
        Guardalos en un lugar seguro: <strong>no se vuelven a mostrar</strong>.
      </p>
      <ul className="grid select-all grid-cols-2 gap-2 rounded bg-white p-3 font-mono text-base" aria-label="Códigos de recuperación">
        {codes.map((code) => <li key={code}>{code}</li>)}
      </ul>
      <div className="flex flex-wrap gap-3">
        <button type="button" onClick={copy} className={secondaryButtonClass}>{copied ? 'Copiados ✓' : 'Copiar'}</button>
        <form action={acknowledgeRecoveryCodesAction}>
          <button type="submit" className={buttonClass}>Ya los guardé, continuar</button>
        </form>
      </div>
    </div>
  )
}
