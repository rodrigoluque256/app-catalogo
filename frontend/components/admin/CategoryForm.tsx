'use client'

import Link from 'next/link'
import { useState, useTransition } from 'react'
import { saveCategoryAction } from '@/app/admin/actions'
import { firstError } from '@/lib/admin/forms'
import type { Category, FieldErrors } from '@/lib/admin/types'
import { buttonClass, Field, FormErrors, inputClass, secondaryButtonClass } from './ui'

type Props = { category: Category | null; parents: Category[] }

export function CategoryForm({ category, parents }: Props) {
  const [name, setName] = useState(category?.name ?? '')
  const [slug, setSlug] = useState(category?.slug ?? '')
  const [description, setDescription] = useState(category?.description ?? '')
  const [parentId, setParentId] = useState(category?.parent_id ?? '')
  const [position, setPosition] = useState(String(category?.position ?? 0))
  const [isActive, setIsActive] = useState(category?.is_active ?? true)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setMessage(null)

    startTransition(async () => {
      const result = await saveCategoryAction(category?.id ?? null, {
        name: name.trim(),
        slug: slug.trim() || null,
        description: description.trim() || null,
        parent_id: parentId || null,
        position: Number(position) || 0,
        is_active: isActive,
      })

      if (result && !result.ok) {
        setErrors(result.errors)
        setMessage(result.message)
      }
    })
  }

  return (
    <form onSubmit={submit} className="flex max-w-xl flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5" noValidate>
      <FormErrors message={message} errors={errors} shown={['name', 'slug', 'description', 'parent_id', 'position']} />
      <Field label="Nombre" htmlFor="name" error={firstError(errors, 'name')}>
        <input id="name" className={inputClass} value={name} maxLength={120} required onChange={(e) => setName(e.target.value)} />
      </Field>
      <Field label="Categoría padre" htmlFor="parent" error={firstError(errors, 'parent_id')}>
        <select id="parent" className={inputClass} value={parentId} onChange={(e) => setParentId(e.target.value)}>
          <option value="">Ninguna (categoría principal)</option>
          {parents.filter((parent) => parent.id !== category?.id).map((parent) => (
            <option key={parent.id} value={parent.id}>{parent.name}</option>
          ))}
        </select>
      </Field>
      <Field label="Descripción" htmlFor="description" error={firstError(errors, 'description')}>
        <textarea id="description" rows={3} className={inputClass} value={description} maxLength={2000} onChange={(e) => setDescription(e.target.value)} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Slug (URL)" htmlFor="slug" error={firstError(errors, 'slug')} hint="Vacío = se genera del nombre.">
          <input id="slug" className={inputClass} value={slug} maxLength={140} onChange={(e) => setSlug(e.target.value)} />
        </Field>
        <Field label="Orden" htmlFor="position" error={firstError(errors, 'position')} hint="Menor = primero.">
          <input id="position" inputMode="numeric" className={inputClass} value={position} onChange={(e) => setPosition(e.target.value)} />
        </Field>
      </div>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
        Visible en la tienda
      </label>
      <div className="flex gap-3">
        <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Guardando…' : category ? 'Guardar cambios' : 'Crear categoría'}</button>
        <Link href="/admin/categories" className={secondaryButtonClass}>Cancelar</Link>
      </div>
    </form>
  )
}
