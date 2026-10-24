export function SearchForm({ defaultValue = '' }: { defaultValue?: string }) {
  return (
    <form action="/buscar" role="search" className="flex w-full max-w-xs">
      <label htmlFor="q" className="sr-only">
        Buscar productos
      </label>
      <input
        id="q"
        name="q"
        type="search"
        defaultValue={defaultValue}
        placeholder="Buscar…"
        maxLength={100}
        className="w-full rounded-l-md border border-r-0 border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-primary"
      />
      <button type="submit" className="rounded-r-md bg-primary px-3 text-sm text-primary-fg">
        Ir
      </button>
    </form>
  )
}
