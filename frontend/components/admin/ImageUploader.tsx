'use client'

import { useRef, useState } from 'react'
import { presignUploadAction } from '@/app/admin/actions'
import { secondaryButtonClass } from './ui'

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp']
const MAX_BYTES = 5 * 1024 * 1024

export type UploadedImage = { path: string; url: string }

/**
 * 1) pide una URL firmada al servidor (BFF), 2) sube el archivo directo a R2 con PUT, 3) devuelve la key.
 * Requiere CORS en el bucket para el origen del panel.
 */
export async function uploadImage(
  file: File,
  purpose: 'product_image' | 'store_logo',
): Promise<{ ok: true; image: UploadedImage } | { ok: false; message: string }> {
  if (!ALLOWED.includes(file.type)) return { ok: false, message: `${file.name}: solo JPEG, PNG o WebP.` }
  if (file.size > MAX_BYTES) return { ok: false, message: `${file.name}: supera los 5 MB.` }

  const signed = await presignUploadAction(purpose, file.type, file.size)
  if (!signed.ok) return { ok: false, message: `${file.name}: ${signed.message}` }

  try {
    const response = await fetch(signed.upload.upload_url, {
      method: 'PUT',
      headers: { ...signed.upload.headers, 'Content-Type': file.type },
      body: file,
    })

    if (!response.ok) return { ok: false, message: `${file.name}: falló la subida (${response.status}).` }
  } catch {
    return { ok: false, message: `${file.name}: no se pudo subir. Revisá tu conexión (o el CORS del bucket).` }
  }

  return { ok: true, image: { path: signed.upload.path, url: signed.upload.public_url } }
}

type Props = {
  purpose: 'product_image' | 'store_logo'
  multiple?: boolean
  label: string
  onUploaded: (images: UploadedImage[]) => void
}

export function ImageUploader({ purpose, multiple = false, label, onUploaded }: Props) {
  const input = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState<string[]>([])

  async function handle(files: FileList | null) {
    if (!files || files.length === 0) return

    setBusy(true)
    setErrors([])

    const results = await Promise.all(Array.from(files).map((file) => uploadImage(file, purpose)))
    const uploaded = results.flatMap((result) => (result.ok ? [result.image] : []))

    setErrors(results.flatMap((result) => (result.ok ? [] : [result.message])))
    if (uploaded.length > 0) onUploaded(uploaded)
    setBusy(false)
    if (input.current) input.current.value = ''
  }

  return (
    <div className="flex flex-col gap-2">
      <input
        ref={input}
        type="file"
        accept={ALLOWED.join(',')}
        multiple={multiple}
        className="sr-only"
        id={`upload-${purpose}`}
        onChange={(event) => void handle(event.target.files)}
      />
      <button type="button" disabled={busy} onClick={() => input.current?.click()} className={secondaryButtonClass}>
        {busy ? 'Subiendo…' : label}
      </button>
      {errors.length > 0 && (
        <ul role="alert" className="text-xs text-red-600">
          {errors.map((error) => (
            <li key={error}>{error}</li>
          ))}
        </ul>
      )}
    </div>
  )
}
