'use client'

import { useActionState, useState } from 'react'
import { confirmTwoFactorAction, verifyTwoFactorAction, type TwoFactorState } from '@/app/platform/actions'
import { buttonClass, Field, inputClass } from '@/components/admin/ui'

const initial: TwoFactorState = { error: null, field: 'code' }

function CodeInput({ error }: { error?: string | undefined }) {
  return (
    <Field label="Código de 6 dígitos" htmlFor="code" error={error} hint="El que muestra tu app de autenticación en este momento.">
      <input
        id="code" name="code" required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9 ]{6,7}" maxLength={7}
        className={`${inputClass} font-mono tracking-widest`}
      />
    </Field>
  )
}

/** Primer ingreso: escanear el QR, confirmar con un código y guardar los códigos de recuperación. */
export function TwoFactorSetupForm({ qrSvg, secret }: { qrSvg: string; secret: string }) {
  const [state, action, pending] = useActionState(confirmTwoFactorAction, initial)

  return (
    <form action={action} className="flex w-full max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6">
      <h1 className="text-xl font-semibold">Configurá la verificación en dos pasos</h1>
      <ol className="list-decimal space-y-1 pl-5 text-sm text-zinc-700">
        <li>Abrí una app de autenticación (Google Authenticator, Authy, 1Password…).</li>
        <li>Escaneá este código QR o cargá la clave a mano.</li>
        <li>Escribí el código de 6 dígitos que te muestra.</li>
      </ol>
      {/* SVG generado por la API; como imagen (data URI) no puede ejecutar nada. */}
      <img
        src={`data:image/svg+xml;base64,${btoa(qrSvg)}`}
        alt="Código QR para agregar la cuenta a tu app de autenticación"
        width={200} height={200}
        className="self-center rounded border border-zinc-200"
      />
      <p className="text-sm">
        Clave para cargar a mano:{' '}
        <code data-testid="totp-secret" className="select-all break-all rounded bg-zinc-100 px-2 py-1 font-mono text-xs">{secret}</code>
      </p>
      <CodeInput error={state.error ?? undefined} />
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Verificando…' : 'Activar y entrar'}</button>
    </form>
  )
}

export function TwoFactorVerifyForm() {
  const [state, action, pending] = useActionState(verifyTwoFactorAction, initial)
  const [useRecovery, setUseRecovery] = useState(false)

  return (
    <form action={action} className="flex w-full max-w-sm flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6">
      <h1 className="text-xl font-semibold">Verificación en dos pasos</h1>
      {useRecovery ? (
        <Field label="Código de recuperación" htmlFor="recovery_code" error={state.field === 'recovery_code' ? (state.error ?? undefined) : undefined}>
          <input id="recovery_code" name="recovery_code" required autoComplete="off" className={`${inputClass} font-mono`} />
        </Field>
      ) : (
        <CodeInput error={state.field === 'code' ? (state.error ?? undefined) : undefined} />
      )}
      <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Verificando…' : 'Entrar'}</button>
      <button type="button" onClick={() => setUseRecovery(!useRecovery)} className="text-left text-sm underline">
        {useRecovery ? 'Usar el código de la app' : 'Perdí el teléfono: usar un código de recuperación'}
      </button>
    </form>
  )
}
