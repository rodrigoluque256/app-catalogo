'use client'

import Image from 'next/image'
import { useState } from 'react'
import type { ProductImage } from '@/lib/types'

export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const [active, setActive] = useState(0)
  const current = images[active]

  if (!current) {
    return (
      <div className="flex aspect-square items-center justify-center rounded-lg bg-zinc-100 text-zinc-600">Sin imagen</div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="relative aspect-square overflow-hidden rounded-lg bg-zinc-100">
        <Image
          src={current.url}
          alt={current.alt_text ?? name}
          fill
          priority
          sizes="(min-width: 1024px) 50vw, 100vw"
          className="object-cover"
        />
      </div>
      {images.length > 1 && (
        <ul className="flex gap-2 overflow-x-auto">
          {images.map((image, index) => (
            <li key={image.id}>
              <button
                type="button"
                onClick={() => setActive(index)}
                aria-label={`Ver imagen ${index + 1}`}
                aria-current={index === active}
                className={`relative block h-16 w-16 overflow-hidden rounded-md border-2 ${index === active ? 'border-primary' : 'border-transparent'}`}
              >
                <Image src={image.url} alt="" fill sizes="64px" className="object-cover" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
