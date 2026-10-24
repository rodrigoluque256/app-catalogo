'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'

export type NavItem = { href: string; label: string }

export function AdminNav({ items }: { items: NavItem[] }) {
  const pathname = usePathname()

  return (
    <nav aria-label="Panel" className="flex gap-1 overflow-x-auto md:flex-col">
      {items.map((item) => {
        const active = item.href === '/admin' ? pathname === '/admin' : pathname.startsWith(item.href)

        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={`whitespace-nowrap rounded-md px-3 py-2 text-sm ${active ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-200'}`}
          >
            {item.label}
          </Link>
        )
      })}
    </nav>
  )
}
