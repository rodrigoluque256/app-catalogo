'use client'

import Link from 'next/link'
import { useActionState } from 'react'
import { acceptInvitationAction, forgotPasswordAction, resetPasswordAction, type AccessLinkState, type ForgotState } from '@/app/admin/actions'
import { firstError } from '@/lib/admin/forms'
import { buttonClass, Field, inputClass } from './ui'

const cardClass = 'flex w-full max-w-sm flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6'

export function ForgotPasswordForm() {
  const [state, action, pending] = useActionState<ForgotState, FormData>(forgotPasswordAction, { sent: false, error: null })

  return (
    <form action={action} className={cardClass}>
      <h1 className="text-xl font-semibold">Recuperar contraseña</h1>
      {state.sent ? (
        <p role="status" className="rounded-md bg-green-50 p-3 text-sm text-green-800">
          Si el email está registrado, te enviamos un link para elegir una contraseña nueva. Vence en 60 minutos: revisá también el correo no deseado.
        </p>
      ) : (
        <>
          <p className="text-sm text-zinc-600">Escribí el email con el que entrás al panel y te mandamos un link.</p>
          {state.error && <p role="alert" className="rounded-md bg-red-50 p-2 text-sm text-red-800">{state.error}</p>}
          <Field label="Email" htmlFor="email">
            <input id="email" name="email" type="email" autoComplete="username" required className={inputClass} />
          </Field>
          <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Enviando…' : 'Enviar link'}</button>
        </>
      )}
      <Link href="/admin/login" className="text-center text-sm underline">Volver a ingresar</Link>
    </form>
  )
}

function PasswordFields({ state }: { state: AccessLinkState }) {
  return (
    <>
      <Field label="Contraseña nueva" htmlFor="password" error={firstError(state.errors, 'password')} hint="Mínimo 10 caracteres, con letras y números.">
        <input id="password" name="password" type="password" autoComplete="new-password" required className={inputClass} />
      </Field>
      <Field label="Repetí la contraseña" htmlFor="password_confirmation">
        <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required className={inputClass} />
      </Field>
    </>
  )
}

/** Error del link (vencido o usado): se muestra arriba del formulario, con la salida que corresponde. */
function LinkError({ state, help }: { state: AccessLinkState; help: React.ReactNode }) {
  const tokenError = firstError(state.errors, 'token')
  const message = tokenError ?? (Object.keys(state.errors).length === 0 ? state.message : null)

  if (!message) return null

  return (
    <div role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-800">
      <p>{message}</p>
      {tokenError && <p className="mt-1">{help}</p>}
    </div>
  )
}

export function ResetPasswordForm({ email, token }: { email: string; token: string }) {
  const [state, action, pending] = useActionState<AccessLinkState, FormData>(resetPasswordAction, { errors: {}, message: null })

  return (
    <form action={action} className={cardClass}>
      <h1 className="text-xl font-semibold">Elegí una contraseña nueva</h1>
      <p className="text-sm text-zinc-600">Para <strong>{email}</strong>. Al guardarla se cierran tus sesiones abiertas.</p>
      <LinkError state={state} help={<Link href="/admin/recuperar" className="underline">Pedir un link nuevo</Link>} />
      <input type="hidden" name="email" value={email} />
      <input type="hidden" name="token" value={token} />
      <PasswordFields state={state} />
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Guardando…' : 'Guardar contraseña'}</button>
    </form>
  )
}

export function AcceptInvitationForm({ email, token, store }: { email: string; token: string; store: string }) {
  const [state, action, pending] = useActionState<AccessLinkState, FormData>(acceptInvitationAction, { errors: {}, message: null })

  return (
    <form action={action} className={cardClass}>
      <h1 className="text-xl font-semibold">Bienvenido a {store}</h1>
      <p className="text-sm text-zinc-600">Elegí la contraseña con la que vas a entrar al panel como <strong>{email}</strong>.</p>
      <LinkError state={state} help="Pedile a quien te invitó que te la reenvíe." />
      <input type="hidden" name="email" value={email} />
      <input type="hidden" name="token" value={token} />
      <PasswordFields state={state} />
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Entrando…' : 'Guardar y entrar'}</button>
    </form>
  )
}
