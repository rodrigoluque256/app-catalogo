// Dominio raíz sin subdominio de tienda (las tiendas se sirven bajo /s/[store] vía proxy.ts).
export default function Landing() {
  return (
    <main className="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center gap-3 px-4 text-center">
      <h1 className="text-3xl font-semibold">Catálogo</h1>
      <p className="text-zinc-600">Visitá la dirección de una tienda, por ejemplo <code>demo.localhost:3000</code>.</p>
    </main>
  )
}
