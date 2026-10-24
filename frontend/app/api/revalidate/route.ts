import { timingSafeEqual } from 'node:crypto'
import { revalidateTag } from 'next/cache'
import { NextResponse } from 'next/server'

const TAG_PATTERN = /^store:[a-z0-9-]+(?::(?:categories|products|product:[a-z0-9-]+))?$/

function validSecret(received: string | null): boolean {
  const expected = process.env.REVALIDATE_SECRET

  if (!expected || !received) return false

  const a = Buffer.from(received)
  const b = Buffer.from(expected)

  return a.length === b.length && timingSafeEqual(a, b)
}

/** Lo llama el backend (RevalidateStorefront) con { tags: [...] } y el header x-revalidate-secret. */
export async function POST(request: Request) {
  if (!validSecret(request.headers.get('x-revalidate-secret'))) {
    return NextResponse.json({ message: 'No autorizado' }, { status: 401 })
  }

  const body: unknown = await request.json().catch(() => null)
  const requested = typeof body === 'object' && body !== null && 'tags' in body ? (body as { tags: unknown }).tags : null

  if (!Array.isArray(requested) || requested.length === 0 || requested.length > 20) {
    return NextResponse.json({ message: 'tags inválidos' }, { status: 422 })
  }

  const valid = requested.filter((tag): tag is string => typeof tag === 'string' && TAG_PATTERN.test(tag))

  // expire: 0 => el próximo visitante ve el dato nuevo (sin servir el viejo mientras se regenera).
  for (const tag of valid) revalidateTag(tag, { expire: 0 })

  return NextResponse.json({ revalidated: valid })
}
