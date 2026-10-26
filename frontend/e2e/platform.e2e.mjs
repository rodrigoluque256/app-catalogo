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
let recoveryCode = ''

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

await step('un código incorrecto no activa el 2FA', async () => {
  await page.getByLabel('Código de 6 dígitos').fill('000000')
  await page.getByRole('button', { name: 'Activar y entrar' }).click()
  await page.getByText('El código no es válido o ya se usó.').waitFor()
})

await step('el código correcto activa el 2FA y muestra 8 códigos de recuperación una vez', async () => {
  await page.getByLabel('Código de 6 dígitos').fill(totp(secret))
  await page.getByRole('button', { name: 'Activar y entrar' }).click()
  const codes = page.getByRole('list', { name: 'Códigos de recuperación' }).getByRole('listitem')
  await codes.first().waitFor()
  if ((await codes.count()) !== 8) throw new Error(`se esperaban 8 códigos, hay ${await codes.count()}`)
  recoveryCode = (await codes.first().textContent()).trim()
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
  await page.getByLabel('Código de recuperación').fill(recoveryCode)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.waitForURL(`${base}/platform`)
  await logout()

  await login()
  await page.waitForURL(/\/platform\/two-factor$/)
  await page.getByRole('button', { name: /usar un código de recuperación/ }).click()
  await page.getByLabel('Código de recuperación').fill(recoveryCode)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.getByText('El código de recuperación no es válido o ya se usó.').waitFor()
})

await browser.close()
writeFileSync(`${outDir}/platform-results.txt`, results.join('\n'))
console.log(results.join('\n'))
process.exitCode = results.some((line) => line.startsWith('FAIL')) ? 1 : 0
