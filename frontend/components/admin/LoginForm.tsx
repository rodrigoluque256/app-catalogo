'use client'

import Link from 'next/link'
import { useActionState } from 'react'
import { loginAction, type LoginState } from '@/app/admin/actions'
import { buttonClass, Field, inputClass } from './ui'

export function LoginForm({ expired, reset = false }: { expired: boolean; reset?: boolean }) {
  const [state, action, pending] = useActionState<LoginState, FormData>(loginAction, { error: null, email: '' })

  return (
    <form action={action} className="flex w-full max-w-sm flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6">
      <h1 className="text-xl font-semibold">Ingresar al panel</h1>
      {expired && !state.error && (
        <p role="status" className="rounded-md bg-amber-50 p-2 text-sm text-amber-800">Tu sesión venció. Ingresá de nuevo.</p>
      )}
      {reset && !state.error && (
        <p role="status" className="rounded-md bg-green-50 p-2 text-sm text-green-800">Listo, actualizamos tu contraseña. Ingresá con la nueva.</p>
      )}
      {state.error && <p role="alert" className="rounded-md bg-red-50 p-2 text-sm text-red-800">{state.error}</p>}
      <Field label="Email" htmlFor="email">
        <input id="email" name="email" type="email" autoComplete="username" required defaultValue={state.email} key={state.email} className={inputClass} />
      </Field>
      <Field label="Contraseña" htmlFor="password">
        <input id="password" name="password" type="password" autoComplete="current-password" required className={inputClass} />
      </Field>
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Ingresando…' : 'Ingresar'}</button>
      <Link href="/admin/recuperar" className="text-center text-sm underline">¿Olvidaste tu contraseña?</Link>
    </form>
  )
}
