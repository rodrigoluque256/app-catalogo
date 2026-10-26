import { PageHeader } from '@/components/admin/ui'
import { requirePlatformSession } from '@/lib/platform/api'

export default async function PlatformHome() {
  const session = await requirePlatformSession()

  return (
    <>
      <PageHeader title={`Hola, ${session.admin.name}`} />
      <section className="max-w-2xl rounded-lg border border-zinc-200 bg-white p-5 text-sm text-zinc-700">
        <h2 className="mb-2 font-semibold text-zinc-900">Gestión de tiendas</h2>
        <p>Próximamente: listar, crear, suspender, reactivar y borrar tiendas (P2.1b).</p>
      </section>
    </>
  )
}
