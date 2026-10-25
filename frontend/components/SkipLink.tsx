/** Primer elemento enfocable de la página: permite saltear encabezado y navegación (WCAG 2.4.1). */
export function SkipLink({ target = 'contenido' }: { target?: string }) {
  return (
    <a
      href={`#${target}`}
      className="sr-only rounded-md bg-zinc-900 px-3 py-2 text-sm text-white focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50"
    >
      Saltar al contenido
    </a>
  )
}
