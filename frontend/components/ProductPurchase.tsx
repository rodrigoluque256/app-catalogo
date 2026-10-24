'use client'

import { useMemo, useState } from 'react'
import { formatMoney } from '@/lib/money'
import { effectivePrice, findVariant, optionGroups } from '@/lib/products'
import type { Product } from '@/lib/types'
import { buildWhatsAppMessage, buildWhatsAppUrl } from '@/lib/whatsapp'

type Props = {
  product: Product
  currency: string
  locale: string
  whatsappNumber: string | null
  whatsappTemplate: string | null
}

export function ProductPurchase({ product, currency, locale, whatsappNumber, whatsappTemplate }: Props) {
  const groups = useMemo(() => optionGroups(product.variants), [product.variants])
  const groupNames = Object.keys(groups)
  const [selection, setSelection] = useState<Record<string, string>>({})

  const complete = groupNames.every((name) => selection[name] !== undefined)
  const variant = complete && groupNames.length > 0 ? findVariant(product.variants, selection) : null
  const needsChoice = groupNames.length > 0 && !complete
  const unavailable = groupNames.length > 0 && complete && (variant === null || !variant.in_stock)
  const price = effectivePrice(product, variant)

  /** ¿Existe alguna variante con stock que combine esta opción con lo ya elegido en los otros grupos? */
  const optionAvailable = (name: string, value: string) =>
    product.variants.some(
      (candidate) =>
        candidate.in_stock &&
        candidate.options[name] === value &&
        Object.entries(selection).every(([other, chosen]) => other === name || candidate.options[other] === chosen),
    )

  function openWhatsApp() {
    const message = buildWhatsAppMessage({
      template: whatsappTemplate,
      productName: product.name,
      options: selection,
      productUrl: window.location.href,
    })
    const url = buildWhatsAppUrl(whatsappNumber, message)

    if (url) window.open(url, '_blank', 'noopener,noreferrer')
  }

  const canOrder = whatsappNumber !== null && !needsChoice && !unavailable

  return (
    <div className="flex flex-col gap-5">
      <p className="flex items-baseline gap-3">
        <span className="text-2xl font-semibold">{formatMoney(price, currency, locale)}</span>
        {product.compare_at_amount !== null && product.compare_at_amount > price && (
          <span className="text-zinc-400 line-through">{formatMoney(product.compare_at_amount, currency, locale)}</span>
        )}
      </p>

      {groupNames.map((name) => (
        <fieldset key={name}>
          <legend className="mb-2 text-sm font-medium capitalize">
            {name}
            {selection[name] && <span className="ml-1 font-normal text-zinc-500">: {selection[name]}</span>}
          </legend>
          <div className="flex flex-wrap gap-2">
            {groups[name]!.map((value) => {
              const selected = selection[name] === value
              const available = optionAvailable(name, value)

              return (
                <button
                  key={value}
                  type="button"
                  aria-pressed={selected}
                  onClick={() => setSelection((current) => ({ ...current, [name]: value }))}
                  className={`min-w-11 rounded-md border px-3 py-1.5 text-sm transition-colors ${
                    selected ? 'border-primary bg-primary text-primary-fg' : 'border-zinc-300 bg-white hover:border-zinc-500'
                  } ${available ? '' : 'text-zinc-400 line-through'}`}
                >
                  {value}
                </button>
              )
            })}
          </div>
        </fieldset>
      ))}

      {unavailable && <p className="text-sm text-red-600">Esa combinación no está disponible.</p>}

      {whatsappNumber === null ? (
        <p className="text-sm text-zinc-500">Esta tienda todavía no configuró un WhatsApp de contacto.</p>
      ) : (
        <button
          type="button"
          onClick={openWhatsApp}
          disabled={!canOrder}
          className="flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-5 py-3 font-semibold text-zinc-900 transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <svg aria-hidden viewBox="0 0 24 24" className="h-5 w-5 fill-current">
            <path d="M12.04 2a9.9 9.9 0 0 0-8.43 15.1L2 22l5.05-1.58A9.93 9.93 0 1 0 12.04 2Zm5.8 14.07c-.25.7-1.45 1.34-2 1.4-.52.05-1.16.07-1.87-.12-.43-.13-.98-.32-1.69-.62-2.97-1.29-4.9-4.3-5.05-4.5-.15-.2-1.2-1.6-1.2-3.04 0-1.45.76-2.16 1.03-2.45.27-.3.59-.37.79-.37h.57c.18 0 .43-.07.67.51.25.6.84 2.05.91 2.2.08.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.18.3.78 1.28 1.67 2.07 1.14 1.02 2.1 1.34 2.4 1.49.3.15.47.12.65-.07.17-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.72.81 2.02.96.3.15.5.22.57.35.08.12.08.72-.17 1.42Z" />
          </svg>
          {needsChoice ? 'Elegí una opción para consultar' : 'Consultar por WhatsApp'}
        </button>
      )}
    </div>
  )
}
