'use client'

import { useActionState } from 'react'
import { platformLoginAction, type PlatformLoginState } from '@/app/platform/actions'
import { buttonClass, Field, inputClass } from '@/components/admin/ui'

export function PlatformLoginForm({ expired }: { expired: boolean }) {
  const [state, action, pending] = useActionState<PlatformLoginState, FormData>(platformLoginAction, { error: null, email: '' })

  return (
    <form action={action} className="flex w-full max-w-sm flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6">
      <div>
        <h1 className="text-xl font-semibold">Administración de la plataforma</h1>
        <p className="mt-1 text-sm text-zinc-600">Acceso restringido. Se pide un código de verificación después de la contraseña.</p>
      </div>
      {expired && !state.error && (
        <p role="status" className="rounded-md bg-amber-50 p-2 text-sm text-amber-800">Tu sesión venció. Ingresá de nuevo.</p>
      )}
      {state.error && <p role="alert" className="rounded-md bg-red-50 p-2 text-sm text-red-800">{state.error}</p>}
      <Field label="Email" htmlFor="email">
        <input id="email" name="email" type="email" autoComplete="username" required defaultValue={state.email} key={state.email} className={inputClass} />
      </Field>
      <Field label="Contraseña" htmlFor="password">
        <input id="password" name="password" type="password" autoComplete="current-password" required className={inputClass} />
      </Field>
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Verificando…' : 'Continuar'}</button>
    </form>
  )
}
