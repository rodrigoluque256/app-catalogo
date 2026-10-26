'use client'

import Link from 'next/link'
import { useState, useTransition } from 'react'
import { saveProductAction } from '@/app/admin/actions'
import { buildProductPayload, firstError, newKey, type ProductForm as FormState, type VariantForm } from '@/lib/admin/forms'
import type { Category, FieldErrors, ProductStatus } from '@/lib/admin/types'
import { ImageUploader } from './ImageUploader'
import { buttonClass, Field, FormErrors, inputClass, secondaryButtonClass } from './ui'

type Props = { productId: string | null; initial: FormState; categories: Category[]; maxImages: number; planName: string }

export function ProductForm({ productId, initial, categories, maxImages, planName }: Props) {
  const [form, setForm] = useState<FormState>(initial)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [pending, startTransition] = useTransition()

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((current) => ({ ...current, [key]: value }))
  const error = (key: string) => firstError(errors, key)

  function submit(event: React.FormEvent) {
    event.preventDefault()

    const built = buildProductPayload(form)

    if (!built.ok) {
      setErrors(built.errors)
      setMessage('Revisá los campos marcados.')
      return
    }

    setErrors({})
    setMessage(null)

    startTransition(async () => {
      const result = await saveProductAction(productId, built.payload)

      if (result && !result.ok) {
        setErrors(result.errors)
        setMessage(result.message)
      }
    })
  }

  // ---- variantes
  const addOption = () =>
    setForm((current) => ({
      ...current,
      optionNames: [...current.optionNames, ''],
      variants: current.variants.map((variant) => ({ ...variant, values: [...variant.values, ''] })),
    }))

  const removeOption = (index: number) =>
    setForm((current) => ({
      ...current,
      optionNames: current.optionNames.filter((_, i) => i !== index),
      variants: current.variants.map((variant) => ({ ...variant, values: variant.values.filter((_, i) => i !== index) })),
    }))

  const renameOption = (index: number, name: string) =>
    set('optionNames', form.optionNames.map((current, i) => (i === index ? name : current)))

  const addVariant = () =>
    set('variants', [
      ...form.variants,
      { key: newKey(), values: form.optionNames.map(() => ''), sku: '', price: '', stock: '', isActive: true },
    ])

  const updateVariant = (index: number, patch: Partial<VariantForm>) =>
    set('variants', form.variants.map((variant, i) => (i === index ? { ...variant, ...patch } : variant)))

  const updateValue = (variantIndex: number, optionIndex: number, value: string) =>
    updateVariant(variantIndex, {
      values: (form.variants[variantIndex]?.values ?? []).map((current, i) => (i === optionIndex ? value : current)),
    })

  // ---- imágenes
  const moveImage = (from: number, to: number) => {
    if (to < 0 || to >= form.images.length) return
    const next = [...form.images]
    const [moved] = next.splice(from, 1)
    if (moved) next.splice(to, 0, moved)
    set('images', next)
  }

  return (
    <form onSubmit={submit} className="flex max-w-4xl flex-col gap-8" noValidate>
      <FormErrors message={message} errors={errors} shown={['name', 'slug', 'description', 'category_id', 'sku', 'price_amount', 'compare_at_amount', 'status', 'variants', 'images']} />

      <section className="grid gap-4 rounded-lg border border-zinc-200 bg-white p-5 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <Field label="Nombre" htmlFor="name" error={error('name')}>
            <input id="name" className={inputClass} value={form.name} maxLength={160} required onChange={(e) => set('name', e.target.value)} />
          </Field>
        </div>
        <div className="sm:col-span-2">
          <Field label="Descripción" htmlFor="description" error={error('description')}>
            <textarea id="description" rows={4} className={inputClass} value={form.description} maxLength={5000} onChange={(e) => set('description', e.target.value)} />
          </Field>
        </div>
        <Field label="Precio" htmlFor="price" error={error('price_amount')} hint="En pesos, ej. 18500 o 18.500,50">
          <input id="price" inputMode="decimal" className={inputClass} value={form.price} onChange={(e) => set('price', e.target.value)} />
        </Field>
        <Field label="Precio anterior (tachado)" htmlFor="compareAt" error={error('compare_at_amount')} hint="Opcional. Debe ser mayor al precio.">
          <input id="compareAt" inputMode="decimal" className={inputClass} value={form.compareAt} onChange={(e) => set('compareAt', e.target.value)} />
        </Field>
        <Field label="Categoría" htmlFor="category" error={error('category_id')}>
          <select id="category" className={inputClass} value={form.categoryId} onChange={(e) => set('categoryId', e.target.value)}>
            <option value="">Sin categoría</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Estado" htmlFor="status" error={error('status')} hint="Solo los productos activos se ven en la tienda.">
          <select id="status" className={inputClass} value={form.status} onChange={(e) => set('status', e.target.value as ProductStatus)}>
            <option value="draft">Borrador</option>
            <option value="active">Activo</option>
            <option value="archived">Archivado</option>
          </select>
        </Field>
        <Field label="SKU" htmlFor="sku" error={error('sku')}>
          <input id="sku" className={inputClass} value={form.sku} maxLength={64} onChange={(e) => set('sku', e.target.value)} />
        </Field>
        <Field label="Slug (URL)" htmlFor="slug" error={error('slug')} hint="Vacío = se genera del nombre. Solo minúsculas, números y guiones.">
          <input id="slug" className={inputClass} value={form.slug} maxLength={180} onChange={(e) => set('slug', e.target.value)} />
        </Field>
        <label className="flex items-center gap-2 text-sm sm:col-span-2">
          <input type="checkbox" checked={form.isFeatured} onChange={(e) => set('isFeatured', e.target.checked)} />
          Producto destacado (aparece primero en el inicio)
        </label>
      </section>

      <section className="rounded-lg border border-zinc-200 bg-white p-5" aria-labelledby="imagenes">
        <h2 id="imagenes" className="mb-1 font-semibold">Imágenes</h2>
        <p className="mb-3 text-xs text-zinc-500">JPEG, PNG o WebP de hasta 5 MB. La primera es la principal.</p>
        {error('images') && <p role="alert" className="mb-2 text-xs text-red-600">{error('images')}</p>}

        <ul className="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {form.images.map((image, index) => (
            <li key={image.key} className="flex flex-col gap-1 rounded-md border border-zinc-200 p-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={image.url} alt={image.altText} className="aspect-square w-full rounded object-cover" />
              {index === 0 && <span className="text-xs font-medium text-green-700">Principal</span>}
              {error(`images.${index}.path`) && <span role="alert" className="text-xs text-red-600">{error(`images.${index}.path`)}</span>}
              <input
                aria-label={`Texto alternativo de la imagen ${index + 1}`}
                placeholder="Texto alternativo"
                className={`${inputClass} !py-1 text-xs`}
                value={image.altText}
                maxLength={160}
                onChange={(e) => set('images', form.images.map((current, i) => (i === index ? { ...current, altText: e.target.value } : current)))}
              />
              <div className="flex justify-between text-xs">
                <button type="button" disabled={index === 0} onClick={() => moveImage(index, index - 1)} aria-label="Mover a la izquierda">←</button>
                <button type="button" className="text-red-600" onClick={() => set('images', form.images.filter((_, i) => i !== index))}>Quitar</button>
                <button type="button" disabled={index === form.images.length - 1} onClick={() => moveImage(index, index + 1)} aria-label="Mover a la derecha">→</button>
              </div>
            </li>
          ))}
        </ul>

        {form.images.length < maxImages ? (
          <ImageUploader
            purpose="product_image"
            multiple
            label="Subir imágenes"
            onUploaded={(uploaded) =>
              setForm((current) => ({
                ...current,
                // Si subió varias de golpe, se queda con las que entran en el plan.
                images: [...current.images, ...uploaded.map((image) => ({ key: newKey(), path: image.path, url: image.url, altText: '' }))].slice(
                  0,
                  Math.max(maxImages, current.images.length),
                ),
              }))
            }
          />
        ) : (
          <p className="text-sm text-zinc-600">
            Tu plan {planName} permite hasta {maxImages} {maxImages === 1 ? 'foto' : 'fotos'} por producto.{' '}
            <Link href="/admin/plan" className="underline">Ver planes</Link>
          </p>
        )}
      </section>

      <section className="rounded-lg border border-zinc-200 bg-white p-5" aria-labelledby="variantes">
        <h2 id="variantes" className="mb-1 font-semibold">Variantes</h2>
        <p className="mb-3 text-xs text-zinc-500">
          Opcional. Definí las opciones (talle, color…) y una fila por combinación. Stock vacío = sin control de stock; precio vacío = el del producto.
          Al guardar se reemplazan todas las variantes.
        </p>
        {error('variants') && <p role="alert" className="mb-2 text-xs text-red-600">{error('variants')}</p>}

        <div className="mb-3 flex flex-wrap items-center gap-2">
          {form.optionNames.map((name, index) => (
            <span key={index} className="flex items-center gap-1 rounded-md border border-zinc-300 bg-zinc-50 pr-1">
              <input
                aria-label={`Nombre de la opción ${index + 1}`}
                placeholder="ej. talle"
                className="w-24 bg-transparent px-2 py-1 text-sm"
                value={name}
                maxLength={50}
                onChange={(e) => renameOption(index, e.target.value)}
              />
              <button type="button" aria-label={`Quitar opción ${name || index + 1}`} className="px-1 text-zinc-500 hover:text-red-600" onClick={() => removeOption(index)}>×</button>
            </span>
          ))}
          <button type="button" className={secondaryButtonClass} onClick={addOption} disabled={form.optionNames.length >= 5}>＋ Opción</button>
        </div>

        {form.optionNames.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
              <thead>
                <tr className="text-left text-xs text-zinc-500">
                  {form.optionNames.map((name, index) => (
                    <th key={index} className="pb-1 pr-2 font-medium">{name || `Opción ${index + 1}`}</th>
                  ))}
                  <th className="pb-1 pr-2 font-medium">SKU</th>
                  <th className="pb-1 pr-2 font-medium">Precio</th>
                  <th className="pb-1 pr-2 font-medium">Stock</th>
                  <th className="pb-1 pr-2 font-medium">Activa</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {form.variants.map((variant, index) => (
                  <tr key={variant.key} className="align-top">
                    {form.optionNames.map((name, optionIndex) => (
                      <td key={optionIndex} className="py-1 pr-2">
                        <input aria-label={`${name || 'Opción'} de la variante ${index + 1}`} className={inputClass} value={variant.values[optionIndex] ?? ''} maxLength={50} onChange={(e) => updateValue(index, optionIndex, e.target.value)} />
                      </td>
                    ))}
                    <td className="py-1 pr-2">
                      <input aria-label={`SKU de la variante ${index + 1}`} className={inputClass} value={variant.sku} maxLength={64} onChange={(e) => updateVariant(index, { sku: e.target.value })} />
                      {error(`variants.${index}.sku`) && <span role="alert" className="text-xs text-red-600">{error(`variants.${index}.sku`)}</span>}
                    </td>
                    <td className="py-1 pr-2">
                      <input aria-label={`Precio de la variante ${index + 1}`} inputMode="decimal" className={inputClass} value={variant.price} onChange={(e) => updateVariant(index, { price: e.target.value })} />
                      {error(`variants.${index}.price_amount`) && <span role="alert" className="text-xs text-red-600">{error(`variants.${index}.price_amount`)}</span>}
                    </td>
                    <td className="py-1 pr-2">
                      <input aria-label={`Stock de la variante ${index + 1}`} inputMode="numeric" className={`${inputClass} w-20`} value={variant.stock} onChange={(e) => updateVariant(index, { stock: e.target.value })} />
                      {error(`variants.${index}.stock`) && <span role="alert" className="text-xs text-red-600">{error(`variants.${index}.stock`)}</span>}
                    </td>
                    <td className="py-2 pr-2">
                      <input type="checkbox" aria-label={`Variante ${index + 1} activa`} checked={variant.isActive} onChange={(e) => updateVariant(index, { isActive: e.target.checked })} />
                    </td>
                    <td className="py-1">
                      <button type="button" className="text-xs text-red-600" onClick={() => set('variants', form.variants.filter((_, i) => i !== index))}>Quitar</button>
                      {(error(`variants.${index}.options`) ?? error(`variants.${index}.options.0`)) && (
                        <p role="alert" className="text-xs text-red-600">{error(`variants.${index}.options`) ?? error(`variants.${index}.options.0`)}</p>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {form.optionNames.length > 0 && (
          <button type="button" className={`${secondaryButtonClass} mt-3`} onClick={addVariant}>＋ Variante</button>
        )}
      </section>

      <div className="flex gap-3">
        <button type="submit" disabled={pending} className={buttonClass}>
          {pending ? 'Guardando…' : productId ? 'Guardar cambios' : 'Crear producto'}
        </button>
        <Link href="/admin/products" className={secondaryButtonClass}>Cancelar</Link>
      </div>
    </form>
  )
}
