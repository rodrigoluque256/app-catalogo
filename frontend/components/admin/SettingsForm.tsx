'use client'

import { useState, useTransition } from 'react'
import { saveSettingsAction } from '@/app/admin/actions'
import { firstError } from '@/lib/admin/forms'
import type { FieldErrors, Store } from '@/lib/admin/types'
import { ImageUploader } from './ImageUploader'
import { buttonClass, Field, FormErrors, inputClass } from './ui'

export function SettingsForm({ store }: { store: Store }) {
  const [name, setName] = useState(store.name)
  const [primary, setPrimary] = useState(store.theme.primary_color)
  const [accent, setAccent] = useState(store.theme.accent_color)
  const [whatsapp, setWhatsapp] = useState(store.whatsapp.number ?? '')
  const [template, setTemplate] = useState(store.whatsapp.message_template ?? '')
  const [currency, setCurrency] = useState(store.currency)
  const [locale, setLocale] = useState(store.locale)
  // undefined = no se toca el logo; null = quitarlo; string = nueva key
  const [logoPath, setLogoPath] = useState<string | null | undefined>(undefined)
  const [logoUrl, setLogoUrl] = useState<string | null>(store.logo_url)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [pending, startTransition] = useTransition()

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setMessage(null)
    setSaved(false)

    startTransition(async () => {
      const result = await saveSettingsAction({
        name: name.trim(),
        primary_color: primary,
        accent_color: accent,
        whatsapp_number: whatsapp.trim().replace(/[\s()-]/g, '') || null,
        whatsapp_message_template: template.trim() || null,
        currency: currency.trim(),
        locale: locale.trim(),
        ...(logoPath !== undefined && { logo_path: logoPath }),
      })

      if (result.ok) setSaved(true)
      else {
        setErrors(result.errors)
        setMessage(result.message)
      }
    })
  }

  const colorField = (id: string, label: string, value: string, set: (value: string) => void, key: string) => (
    <Field label={label} htmlFor={id} error={firstError(errors, key)}>
      <div className="flex gap-2">
        <input type="color" aria-label={`${label} (selector)`} value={/^#[0-9a-f]{6}$/i.test(value) ? value : '#000000'} onChange={(e) => set(e.target.value)} className="h-9 w-12 cursor-pointer rounded border border-zinc-300" />
        <input id={id} className={inputClass} value={value} maxLength={7} onChange={(e) => set(e.target.value)} />
      </div>
    </Field>
  )

  return (
    <form onSubmit={submit} className="flex max-w-xl flex-col gap-5 rounded-lg border border-zinc-200 bg-white p-5" noValidate>
      <FormErrors message={message} errors={errors} shown={['name', 'logo_path', 'primary_color', 'accent_color', 'whatsapp_number', 'whatsapp_message_template', 'currency', 'locale']} />
      {saved && <p role="status" className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">Cambios guardados. La tienda se actualiza en unos segundos.</p>}

      <Field label="Nombre de la tienda" htmlFor="name" error={firstError(errors, 'name')}>
        <input id="name" className={inputClass} value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
      </Field>

      <div>
        <p className="mb-1 text-sm font-medium">Logo</p>
        <div className="flex items-center gap-4">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt="Logo actual" className="h-16 w-16 rounded border border-zinc-200 object-contain" />
          ) : (
            <div className="flex h-16 w-16 items-center justify-center rounded border border-dashed border-zinc-300 text-xs text-zinc-500">Sin logo</div>
          )}
          <div className="flex flex-col gap-2">
            <ImageUploader purpose="store_logo" label={logoUrl ? 'Cambiar logo' : 'Subir logo'} onUploaded={([image]) => { if (image) { setLogoPath(image.path); setLogoUrl(image.url) } }} />
            {logoUrl && <button type="button" className="text-left text-xs text-red-600" onClick={() => { setLogoPath(null); setLogoUrl(null) }}>Quitar logo</button>}
          </div>
        </div>
        {firstError(errors, 'logo_path') && <p role="alert" className="mt-1 text-xs text-red-600">{firstError(errors, 'logo_path')}</p>}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {colorField('primary', 'Color principal', primary, setPrimary, 'primary_color')}
        {colorField('accent', 'Color de acento', accent, setAccent, 'accent_color')}
      </div>

      <Field label="WhatsApp" htmlFor="whatsapp" error={firstError(errors, 'whatsapp_number')} hint="Con código de país, ej. +5491122334455">
        <input id="whatsapp" inputMode="tel" className={inputClass} value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} />
      </Field>
      <Field label="Mensaje de WhatsApp" htmlFor="template" error={firstError(errors, 'whatsapp_message_template')} hint="Usá {product} donde va el nombre del producto.">
        <textarea id="template" rows={2} className={inputClass} value={template} maxLength={500} onChange={(e) => setTemplate(e.target.value)} />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Moneda" htmlFor="currency" error={firstError(errors, 'currency')} hint="Código de 3 letras: ARS, USD…">
          <input id="currency" className={inputClass} value={currency} maxLength={3} onChange={(e) => setCurrency(e.target.value.toUpperCase())} />
        </Field>
        <Field label="Idioma / región" htmlFor="locale" error={firstError(errors, 'locale')} hint="Ej. es-AR">
          <input id="locale" className={inputClass} value={locale} maxLength={10} onChange={(e) => setLocale(e.target.value)} />
        </Field>
      </div>

      <div>
        <button type="submit" disabled={pending} className={buttonClass}>{pending ? 'Guardando…' : 'Guardar cambios'}</button>
      </div>
    </form>
  )
}
