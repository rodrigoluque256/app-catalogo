'use client'

import { useState, useTransition } from 'react'
import { createUserAction, deleteUserAction, resetUserPasswordAction, updateUserAction } from '@/app/admin/actions'
import { firstError } from '@/lib/admin/forms'
import type { AdminUser, CredentialResult, FieldErrors, UserRole } from '@/lib/admin/types'
import { TemporaryPassword } from './TemporaryPassword'
import { buttonClass, Field, FormErrors, inputClass, secondaryButtonClass } from './ui'

const ROLE_LABELS: Record<UserRole, string> = { owner: 'Dueño', editor: 'Editor' }

type Notice = { email: string; password: string }

export function UsersManager({ users }: { users: AdminUser[] }) {
  const [notice, setNotice] = useState<Notice | null>(null)
  const [editing, setEditing] = useState<number | null>(null)
  const [rowError, setRowError] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  const show = (result: CredentialResult) => {
    if (result.ok) setNotice({ email: result.email, password: result.temporaryPassword })
    else setRowError(result.message)
  }

  function reset(user: AdminUser) {
    if (!window.confirm(`¿Resetear la contraseña de ${user.email}? Se cerrarán todas sus sesiones.`)) return
    setRowError(null)
    startTransition(async () => show(await resetUserPasswordAction(user.id, user.email)))
  }

  function remove(user: AdminUser) {
    if (!window.confirm(`¿Eliminar a ${user.name} (${user.email})? Perderá el acceso de inmediato.`)) return
    setRowError(null)
    startTransition(async () => {
      const result = await deleteUserAction(user.id)
      if (!result.ok) setRowError(result.errors.user?.[0] ?? result.errors.role?.[0] ?? result.message)
    })
  }

  return (
    <div className="flex flex-col gap-6">
      {notice && <TemporaryPassword email={notice.email} password={notice.password} onClose={() => setNotice(null)} />}
      {rowError && <p role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{rowError}</p>}

      <NewUserForm onCreated={(result) => { setRowError(null); show(result) }} />

      <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table className="w-full min-w-[40rem] text-sm">
          <thead className="border-b border-zinc-200 text-left text-xs text-zinc-500">
            <tr>
              <th className="p-3 font-medium">Persona</th>
              <th className="p-3 font-medium">Rol</th>
              <th className="p-3 font-medium">Estado</th>
              <th className="p-3" />
            </tr>
          </thead>
          <tbody>
            {users.map((user) =>
              editing === user.id ? (
                <EditRow key={user.id} user={user} onDone={() => setEditing(null)} />
              ) : (
                <tr key={user.id} className="border-b border-zinc-100 last:border-0">
                  <td className="p-3">
                    <p className="font-medium">{user.name}{user.is_current_user && <span className="ml-2 text-xs text-zinc-500">(vos)</span>}</p>
                    <p className="text-xs text-zinc-500">{user.email}</p>
                  </td>
                  <td className="p-3">{user.role ? ROLE_LABELS[user.role] : '—'}</td>
                  <td className="p-3">
                    {user.must_change_password ? (
                      <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Pendiente de cambiar clave</span>
                    ) : (
                      <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Activo</span>
                    )}
                  </td>
                  <td className="space-x-3 p-3 text-right">
                    <button type="button" className="underline" onClick={() => { setRowError(null); setEditing(user.id) }}>Editar</button>
                    <button type="button" className="underline" disabled={pending} onClick={() => reset(user)}>Resetear clave</button>
                    {!user.is_current_user && (
                      <button type="button" className="text-red-600 underline" disabled={pending} onClick={() => remove(user)}>Eliminar</button>
                    )}
                  </td>
                </tr>
              ),
            )}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-zinc-500">
        <strong>Dueño:</strong> todo, incluida la configuración de la tienda y los usuarios. <strong>Editor:</strong> solo productos y categorías.
      </p>
    </div>
  )
}

function NewUserForm({ onCreated }: { onCreated: (result: CredentialResult) => void }) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [role, setRole] = useState<UserRole>('editor')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setMessage(null)

    startTransition(async () => {
      const result = await createUserAction({ name: name.trim(), email: email.trim(), role })

      if (result.ok) {
        setName('')
        setEmail('')
        setRole('editor')
        onCreated(result)
      } else {
        setErrors(result.errors)
        setMessage(result.message)
      }
    })
  }

  return (
    <form onSubmit={submit} className="rounded-lg border border-zinc-200 bg-white p-5" noValidate aria-labelledby="nuevo-usuario">
      <h2 id="nuevo-usuario" className="mb-3 font-semibold">Agregar persona</h2>
      <FormErrors message={message} errors={errors} shown={['name', 'email', 'role']} />
      <div className="mt-3 grid gap-4 sm:grid-cols-4">
        <Field label="Nombre" htmlFor="user-name" error={firstError(errors, 'name')}>
          <input id="user-name" className={inputClass} value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
        </Field>
        <Field label="Email" htmlFor="user-email" error={firstError(errors, 'email')}>
          <input id="user-email" type="email" className={inputClass} value={email} maxLength={255} onChange={(e) => setEmail(e.target.value)} />
        </Field>
        <Field label="Rol" htmlFor="user-role" error={firstError(errors, 'role')}>
          <select id="user-role" className={inputClass} value={role} onChange={(e) => setRole(e.target.value as UserRole)}>
            <option value="editor">Editor</option>
            <option value="owner">Dueño</option>
          </select>
        </Field>
        <div className="flex items-end">
          <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Creando…' : 'Crear usuario'}</button>
        </div>
      </div>
      <p className="mt-2 text-xs text-zinc-500">Se genera una contraseña temporal que verás una sola vez.</p>
    </form>
  )
}

function EditRow({ user, onDone }: { user: AdminUser; onDone: () => void }) {
  const [name, setName] = useState(user.name)
  const [role, setRole] = useState<UserRole>(user.role ?? 'editor')
  const [error, setError] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  function save() {
    setError(null)
    startTransition(async () => {
      const result = await updateUserAction(user.id, { name: name.trim(), role })

      if (result.ok) onDone()
      else setError(result.errors.role?.[0] ?? result.errors.name?.[0] ?? result.message)
    })
  }

  return (
    <tr className="border-b border-zinc-100 bg-zinc-50">
      <td className="p-3">
        <input aria-label={`Nombre de ${user.email}`} className={inputClass} value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
        <p className="mt-1 text-xs text-zinc-500">{user.email}</p>
      </td>
      <td className="p-3">
        <select aria-label={`Rol de ${user.email}`} className={inputClass} value={role} disabled={user.is_current_user} onChange={(e) => setRole(e.target.value as UserRole)}>
          <option value="editor">Editor</option>
          <option value="owner">Dueño</option>
        </select>
        {user.is_current_user && <p className="mt-1 text-xs text-zinc-500">No podés cambiar tu propio rol.</p>}
      </td>
      <td className="p-3" colSpan={2}>
        <div className="flex items-center justify-end gap-2">
          {error && <span role="alert" className="text-xs text-red-600">{error}</span>}
          <button type="button" disabled={pending} onClick={save} className={buttonClass}>{pending ? 'Guardando…' : 'Guardar'}</button>
          <button type="button" onClick={onDone} className={secondaryButtonClass}>Cancelar</button>
        </div>
      </td>
    </tr>
  )
}
