'use client'

import Link from 'next/link'
import { useState, useTransition } from 'react'
import { createStoreAction, type CreateStoreResult } from '@/app/platform/actions'
import { buttonClass, Field, FormErrors, inputClass, secondaryButtonClass } from '@/components/admin/ui'
import { firstError } from '@/lib/admin/forms'
import type { FieldErrors } from '@/lib/admin/types'
import { slugify } from '@/lib/slug'

type Created = Extract<CreateStoreResult, { ok: true }>

const fields = ['name', 'slug', 'owner_name', 'owner_email']

export function CreateStoreForm({ storefrontTemplate }: { storefrontTemplate: string }) {
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [slugEdited, setSlugEdited] = useState(false)
  const [ownerName, setOwnerName] = useState('')
  const [ownerEmail, setOwnerEmail] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [created, setCreated] = useState<Created | null>(null)
  const [pending, startTransition] = useTransition()

  const url = (value: string) => storefrontTemplate.replace('{slug}', value || 'subdominio')

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setMessage(null)

    startTransition(async () => {
      const result = await createStoreAction({ name, slug, owner_name: ownerName, owner_email: ownerEmail })

      if (result.ok) {
        setCreated(result)
      } else {
        setErrors(result.errors)
        // Si el error es de un campo, ya se muestra junto a él: el resumen solo para los demás casos.
        setMessage(Object.keys(result.errors).some((key) => fields.includes(key)) ? null : result.message)
      }
    })
  }

  if (created) {
    return (
      <div className="flex max-w-xl flex-col gap-4">
        <p role="status" className="rounded-md bg-green-50 p-3 text-sm text-green-800">
          Tienda creada: <a href={url(created.slug)} target="_blank" rel="noreferrer" className="font-medium underline">{url(created.slug)}<span className="sr-only"> (se abre en otra pestaña)</span></a>
        </p>
        <p className="rounded-md border border-zinc-200 bg-white p-3 text-sm">
          Le enviamos la invitación al dueño (<strong>{created.email}</strong>) para que elija su contraseña. El link vence en 72 horas;
          si no le llega, reenviala desde el detalle de la tienda.
        </p>
        <div className="flex gap-3">
          <Link href={`/platform/stores/${created.storeId}`} className={buttonClass}>Ver la tienda creada</Link>
          <Link href="/platform/stores" className={secondaryButtonClass}>Volver al listado</Link>
        </div>
      </div>
    )
  }

  return (
    <form onSubmit={submit} className="flex max-w-xl flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5" noValidate>
      <FormErrors message={message} errors={errors} shown={fields} />
      <Field label="Nombre de la tienda" htmlFor="name" error={firstError(errors, 'name')}>
        <input
          id="name" className={inputClass} value={name} maxLength={120} required
          onChange={(e) => {
            setName(e.target.value)
            if (!slugEdited) setSlug(slugify(e.target.value))
          }}
        />
      </Field>
      <Field label="Subdominio" htmlFor="slug" error={firstError(errors, 'slug')} hint={`La tienda va a quedar en ${url(slug)}`}>
        <input
          id="slug" className={`${inputClass} font-mono`} value={slug} maxLength={63} required
          onChange={(e) => {
            setSlug(e.target.value.toLowerCase())
            setSlugEdited(true)
          }}
        />
      </Field>
      <fieldset className="flex flex-col gap-4 border-t border-zinc-200 pt-4">
        <legend className="pr-2 text-sm font-semibold">Dueño de la tienda</legend>
        <Field label="Nombre" htmlFor="owner_name" error={firstError(errors, 'owner_name')}>
          <input id="owner_name" className={inputClass} value={ownerName} maxLength={120} required onChange={(e) => setOwnerName(e.target.value)} />
        </Field>
        <Field label="Email" htmlFor="owner_email" error={firstError(errors, 'owner_email')} hint="Le llega una invitación para elegir su contraseña.">
          <input id="owner_email" type="email" className={inputClass} value={ownerEmail} maxLength={255} required onChange={(e) => setOwnerEmail(e.target.value)} />
        </Field>
      </fieldset>
      <div className="flex gap-3">
        <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Creando…' : 'Crear tienda'}</button>
        <Link href="/platform/stores" className={secondaryButtonClass}>Cancelar</Link>
      </div>
    </form>
  )
}
