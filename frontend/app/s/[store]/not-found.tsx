import Link from 'next/link'

export default function StoreNotFound() {
  return (
    <div className="flex flex-col items-center gap-3 py-16 text-center">
      <h1 className="text-xl font-semibold">No encontramos esa página</h1>
      <Link href="/" className="text-accent-text underline">
        Ver todos los productos
      </Link>
    </div>
  )
}
