'use client'

import Link from 'next/link'
import { useState, useTransition } from 'react'
import { changePasswordAction } from '@/app/admin/actions'
import { firstError } from '@/lib/admin/forms'
import type { FieldErrors } from '@/lib/admin/types'
import { buttonClass, Field, FormErrors, inputClass, secondaryButtonClass } from './ui'

export function ChangePasswordForm({ forced }: { forced: boolean }) {
  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setMessage(null)

    startTransition(async () => {
      const result = await changePasswordAction({ current_password: current, password, password_confirmation: confirmation })

      if (result && !result.ok) {
        setErrors(result.errors)
        setMessage(result.message)
      }
    })
  }

  return (
    <form onSubmit={submit} className="flex w-full max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6" noValidate>
      <h1 className="text-xl font-semibold">{forced ? 'Elegí tu contraseña' : 'Cambiar contraseña'}</h1>
      {forced && (
        <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-900">
          Ingresaste con una contraseña temporal. Para seguir, elegí una propia.
        </p>
      )}
      <FormErrors message={message} errors={errors} shown={['current_password', 'password']} />
      <Field label={forced ? 'Contraseña temporal' : 'Contraseña actual'} htmlFor="current" error={firstError(errors, 'current_password')}>
        <input id="current" type="password" autoComplete="current-password" className={inputClass} value={current} onChange={(e) => setCurrent(e.target.value)} />
      </Field>
      <Field label="Nueva contraseña" htmlFor="new" error={firstError(errors, 'password')} hint="Mínimo 10 caracteres, con letras y números.">
        <input id="new" type="password" autoComplete="new-password" className={inputClass} value={password} onChange={(e) => setPassword(e.target.value)} />
      </Field>
      <Field label="Repetí la nueva contraseña" htmlFor="confirm">
        <input id="confirm" type="password" autoComplete="new-password" className={inputClass} value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
      </Field>
      <div className="flex gap-3">
        <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Guardando…' : 'Guardar contraseña'}</button>
        {!forced && <Link href="/admin" className={secondaryButtonClass}>Cancelar</Link>}
      </div>
      {!forced && <p className="text-xs text-zinc-500">Al cambiarla se cierran tus sesiones en otros dispositivos.</p>}
    </form>
  )
}
