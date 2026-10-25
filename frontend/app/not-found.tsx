import Link from 'next/link'

export default function NotFound() {
  return (
    <main className="mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center gap-3 px-4 text-center">
      <h1 className="text-2xl font-semibold">No encontramos lo que buscabas</h1>
      <Link href="/" className="text-accent-text underline">
        Volver al inicio
      </Link>
    </main>
  )
}
