/** Blanco o casi negro, el que mejor contraste tenga sobre el color de marca (WCAG, luminancia relativa). */
export function readableForeground(hex: string): '#ffffff' | '#18181b' {
  const match = /^#([0-9a-f]{6})$/i.exec(hex)

  if (!match?.[1]) return '#ffffff'

  const [r, g, b] = [0, 2, 4].map((i) => {
    const channel = parseInt(match[1]!.slice(i, i + 2), 16) / 255
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
  }) as [number, number, number]

  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b

  return luminance > 0.4 ? '#18181b' : '#ffffff'
}
