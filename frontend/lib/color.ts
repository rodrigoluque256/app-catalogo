const DARK = '#18181b'

/** Luminancia relativa (WCAG) de un color #rrggbb; null si el formato no es válido. */
function luminance(hex: string): number | null {
  const match = /^#([0-9a-f]{6})$/i.exec(hex)

  if (!match?.[1]) return null

  const [r, g, b] = [0, 2, 4].map((i) => {
    const channel = parseInt(match[1]!.slice(i, i + 2), 16) / 255
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
  }) as [number, number, number]

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** Relación de contraste WCAG entre dos colores (1 a 21). Con un color inválido devuelve 1. */
export function contrastRatio(a: string, b: string): number {
  const [la, lb] = [luminance(a), luminance(b)]

  if (la === null || lb === null) return 1

  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)
}

/** Blanco o casi negro, el que mejor contraste tenga sobre el color de marca (WCAG, luminancia relativa). */
export function readableForeground(hex: string): '#ffffff' | '#18181b' {
  const value = luminance(hex)

  if (value === null) return '#ffffff'

  return value > 0.4 ? DARK : '#ffffff'
}

/** El acento de la tienda como color de texto sobre blanco, si llega a 4,5:1 (WCAG AA); si no, casi negro. */
export function readableAccent(hex: string): string {
  return contrastRatio(hex, '#ffffff') >= 4.5 ? hex : DARK
}
