'use client'

import { useState } from 'react'
import { secondaryButtonClass } from './ui'

/** Muestra la contraseña temporal una sola vez (el servidor no la guarda en claro ni puede volver a mostrarla). */
export function TemporaryPassword({ email, password, onClose }: { email: string; password: string; onClose: () => void }) {
  const [copied, setCopied] = useState(false)

  async function copy() {
    try {
      await navigator.clipboard.writeText(password)
      setCopied(true)
    } catch {
      setCopied(false) // sin permiso de portapapeles: se puede seleccionar el texto a mano
    }
  }

  return (
    <div role="status" className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <p className="font-medium">Contraseña temporal para {email}</p>
      <p className="mt-2 flex flex-wrap items-center gap-3">
        <code className="select-all rounded bg-white px-3 py-1.5 font-mono text-base tracking-wider">{password}</code>
        <button type="button" onClick={copy} className={secondaryButtonClass}>{copied ? 'Copiada ✓' : 'Copiar'}</button>
      </p>
      <p className="mt-2 text-amber-900">
        Guardala o envíasela ahora: <strong>no se vuelve a mostrar</strong>. Deberá cambiarla al ingresar por primera vez.
      </p>
      <button type="button" onClick={onClose} className="mt-3 text-xs underline">Ya la copié, cerrar</button>
    </div>
  )
}
