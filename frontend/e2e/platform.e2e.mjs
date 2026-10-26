import { chromium } from 'playwright'
import AxeBuilder from '@axe-core/playwright'
import { createHmac } from 'node:crypto'
import { mkdirSync, writeFileSync } from 'node:fs'

// Super panel (/platform): login en dos pasos con 2FA (TOTP) contra el stack e2e (ver bin/e2e.sh).
// Usa el admin del seed (admin@plataforma.test, sin 2FA): el primer ingreso lo configura.
const base = process.env.BASE_URL ?? 'http://frontend:3000'
const outDir = process.env.OUT_DIR ?? '/out'
const results = []
mkdirSync(outDir, { recursive: true })

const browser = await chromium.launch()
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage()
page.on('pageerror', (e) => results.push(`PAGEERROR ${e.message}`))

const step = async (name, fn) => {
  try { await fn(); results.push(`OK   ${name}`) } catch (e) {
    results.push(`FAIL ${name}: ${e.message.split('\n')[0]} (url: ${page.url()})`)
    await page.screenshot({ path: `${outDir}/platform-fail-${results.length}.png`, fullPage: true }).catch(() => {})
  }
}

// Misma auditoría que a11y.e2e.mjs (WCAG 2.2 AA), sobre la página tal como está.
const axe = async () => {
  const { violations } = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .exclude('nextjs-portal')
    .analyze()
  if (violations.length > 0) throw new Error(violations.map((v) => `[${v.impact}] ${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`).join(' | '))
}

// TOTP (RFC 6238, SHA-1, 6 dígitos, pasos de 30 s) a partir de la clave en base32 que muestra la pantalla.
const totp = (secret, offset = 0) => {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  let bits = ''
  for (const char of secret.replace(/=+$/, '').toUpperCase()) bits += alphabet.indexOf(char).toString(2).padStart(5, '0')
  const key = Buffer.from(bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)))
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000) + offset))
  const hmac = createHmac('sha1', key).update(counter).digest()
  const start = hmac[hmac.length - 1] & 0xf
  return String((hmac.readUInt32BE(start) & 0x7fffffff) % 1_000_000).padStart(6, '0')
}

const login = async (password = 'password') => {
  await page.goto(`${base}/platform/login`)
  await page.getByLabel('Email').fill('admin@plataforma.test')
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Continuar' }).click()
}

const logout = async () => {
  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await page.waitForURL('**/platform/login')
}

let secret = ''
let recoveryCodes = []
const run = Date.now().toString(36)
const storeName = `Tienda E2E ${run}`
const storeSlug = `tienda-e2e-${run}`
const ownerEmail = `dueno-${run}@e2e.test`
let temporaryPassword = ''
let storeUrl = ''
page.on('dialog', (dialog) => dialog.accept())

await step('sin sesión, /platform redirige al login de la plataforma', async () => {
  await page.goto(`${base}/platform`)
  await page.waitForURL('**/platform/login')
  await axe()
})

await step('contraseña incorrecta muestra el error genérico', async () => {
  await login('incorrecta')
  await page.getByText('Email o contraseña incorrectos.').waitFor()
  await axe()
})

await step('primer ingreso: pide configurar el 2FA con QR y clave', async () => {
  await login()
  await page.waitForURL('**/platform/two-factor?setup=1')
  await page.getByRole('img', { name: /Código QR/ }).waitFor()
  secret = (await page.getByTestId('totp-secret').textContent()).trim()
  if (!/^[A-Z2-7]{32}$/.test(secret)) throw new Error(`clave inesperada: ${secret}`)
  await axe()
})

// Los códigos incorrectos los cubre Pest: acá cada intento cuenta para el límite de 5 por minuto del 2FA,
// y este recorrido ya usa exactamente 5 (activar, app, recuperación, reuso y segunda recuperación).
await step('el código correcto activa el 2FA y muestra 8 códigos de recuperación una vez', async () => {
  await page.getByLabel('Código de 6 dígitos').fill(totp(secret))
  await page.getByRole('button', { name: 'Activar y entrar' }).click()
  const codes = page.getByRole('list', { name: 'Códigos de recuperación' }).getByRole('listitem')
  await codes.first().waitFor()
  if ((await codes.count()) !== 8) throw new Error(`se esperaban 8 códigos, hay ${await codes.count()}`)
  recoveryCodes = (await codes.allTextContents()).map((code) => code.trim())
  await axe()
  await page.getByRole('button', { name: 'Ya los guardé, continuar' }).click()
  await page.getByRole('heading', { name: 'Hola, Admin Plataforma' }).waitFor()
  await axe()
})

await step('cerrar sesión', logout)

await step('segundo ingreso: pide el código de la app', async () => {
  await login()
  await page.waitForURL(/\/platform\/two-factor$/)
  await axe()
  // El código del paso actual ya se usó al activar: se usa el siguiente (la API tolera ±1 paso).
  await page.getByLabel('Código de 6 dígitos').fill(totp(secret, 1))
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.waitForURL(`${base}/platform`)
  await page.getByRole('heading', { name: 'Hola, Admin Plataforma' }).waitFor()
})

await step('el panel de tiendas no reconoce la sesión de la plataforma', async () => {
  await page.goto(`${base}/admin`)
  await page.waitForURL('**/admin/login')
  await page.goto(`${base}/platform`)
  await logout()
})

await step('con un código de recuperación se entra una sola vez', async () => {
  await login()
  await page.waitForURL(/\/platform\/two-factor$/)
  await page.getByRole('button', { name: /usar un código de recuperación/ }).click()
  await page.getByLabel('Código de recuperación').fill(recoveryCodes[0])
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.waitForURL(`${base}/platform`)
  await logout()

  await login()
  await page.waitForURL(/\/platform\/two-factor$/)
  await page.getByRole('button', { name: /usar un código de recuperación/ }).click()
  await page.getByLabel('Código de recuperación').fill(recoveryCodes[0])
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.getByText('El código de recuperación no es válido o ya se usó.').waitFor()

  // El desafío sigue vigente: con otro código entra (sin otro login, que gastaría el límite de intentos).
  await page.getByLabel('Código de recuperación').fill(recoveryCodes[1])
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.waitForURL(`${base}/platform`)
})

const storefrontStatus = async () => (await page.request.get(`${base}/s/${storeSlug}`)).status()

await step('tiendas: listado con filtros', async () => {
  await page.getByRole('link', { name: 'Tiendas', exact: true }).click()
  await page.waitForURL('**/platform/stores')
  await page.getByRole('link', { name: 'Tienda Demo' }).waitFor()
  await axe()
})

await step('crear tienda: subdominio sugerido y contraseña temporal del dueño', async () => {
  await page.getByRole('link', { name: '＋ Nueva tienda' }).click()
  await page.getByLabel('Nombre de la tienda').fill(storeName)
  if ((await page.getByLabel('Subdominio').inputValue()) !== storeSlug) throw new Error('no sugirió el subdominio')
  await page.getByLabel('Nombre', { exact: true }).fill('Dueña E2E')
  await page.getByLabel('Email').fill(ownerEmail)
  await axe()
  await page.getByRole('button', { name: 'Crear tienda' }).click()
  await page.getByText(`Contraseña temporal para ${ownerEmail}`).waitFor()
  temporaryPassword = (await page.locator('code').first().textContent()).trim()
  await axe()
  if ((await storefrontStatus()) !== 200) throw new Error('la tienda nueva no responde')
})

await step('crear tienda: subdominio reservado o repetido da error', async () => {
  await page.goto(`${base}/platform/stores/new`)
  await page.getByLabel('Nombre de la tienda').fill('Otra')
  await page.getByLabel('Subdominio').fill('platform')
  await page.getByLabel('Nombre', { exact: true }).fill('X')
  await page.getByLabel('Email').fill(`otro-${run}@e2e.test`)
  await page.getByRole('button', { name: 'Crear tienda' }).click()
  await page.getByText('Ese subdominio está reservado por la plataforma.').waitFor()
  await page.getByLabel('Subdominio').fill(storeSlug)
  await page.getByRole('button', { name: 'Crear tienda' }).click()
  await page.getByText('Ese subdominio ya está en uso.').waitFor()
})

await step('el dueño entra al panel de su tienda con la temporal y debe cambiarla', async () => {
  const owner = await (await browser.newContext()).newPage()
  await owner.goto(`${base}/admin/login`)
  await owner.getByLabel('Email').fill(ownerEmail)
  await owner.getByLabel('Contraseña').fill(temporaryPassword)
  await owner.getByRole('button', { name: 'Ingresar' }).click()
  await owner.waitForURL('**/admin/account')
  await owner.getByRole('heading', { name: 'Elegí tu contraseña' }).waitFor()
  await owner.context().close()
})

await step('suspender: la tienda deja de verse; reactivar: vuelve', async () => {
  await page.goto(`${base}/platform/stores?search=${storeSlug}`)
  await page.getByRole('link', { name: storeName }).click()
  await page.waitForURL(/\/platform\/stores\/[0-9a-f-]{36}$/)
  storeUrl = page.url()
  await page.getByRole('heading', { name: storeName }).waitFor()
  await axe()
  await page.getByRole('button', { name: 'Suspender tienda' }).click()
  await page.getByRole('button', { name: 'Reactivar tienda' }).waitFor()
  if ((await storefrontStatus()) !== 404) throw new Error('la tienda suspendida sigue respondiendo')
  await page.getByRole('button', { name: 'Reactivar tienda' }).click()
  await page.getByRole('button', { name: 'Suspender tienda' }).waitFor()
  if ((await storefrontStatus()) !== 200) throw new Error('la tienda reactivada no responde')
})

await step('borrar exige escribir el subdominio; restaurar la deja suspendida', async () => {
  const remove = page.getByRole('button', { name: 'Borrar tienda' })
  if (await remove.isEnabled()) throw new Error('el botón de borrar está habilitado sin confirmar')
  await page.getByLabel(`Escribí “${storeSlug}” para confirmar`).fill(storeSlug)
  await remove.click()
  await page.waitForURL('**/platform/stores?status=deleted')
  await page.getByRole('link', { name: storeName }).waitFor()
  if ((await storefrontStatus()) !== 404) throw new Error('la tienda borrada sigue respondiendo')

  await page.goto(storeUrl)
  await page.getByRole('button', { name: 'Restaurar tienda' }).click()
  await page.getByRole('button', { name: 'Reactivar tienda' }).waitFor()
  await axe()
})

await browser.close()
writeFileSync(`${outDir}/platform-results.txt`, results.join('\n'))
console.log(results.join('\n'))
process.exitCode = results.some((line) => line.startsWith('FAIL')) ? 1 : 0
