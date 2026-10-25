import { chromium } from 'playwright'
import AxeBuilder from '@axe-core/playwright'
import { mkdirSync, writeFileSync } from 'node:fs'

// Auditoría WCAG 2.2 AA con axe sobre tienda y panel, contra el stack e2e (ver bin/e2e.sh).
// Corre después de admin.e2e.mjs sobre la misma base recién sembrada.
const base = process.env.BASE_URL ?? 'http://frontend:3000'
const outDir = process.env.OUT_DIR ?? '/out'
const results = []
mkdirSync(outDir, { recursive: true })

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
const page = await context.newPage()

const step = async (name, fn) => {
  try { await fn(); results.push(`OK   ${name}`) } catch (e) {
    results.push(`FAIL ${name}: ${e.message}`)
    await page.screenshot({ path: `${outDir}/a11y-fail-${results.length}.png`, fullPage: true }).catch(() => {})
  }
}

// Audita la página tal como está (sirve también después de enviar un formulario).
const scan = async () => {
  const { violations } = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    // Indicador del dev server de Next: no existe en producción.
    .exclude('nextjs-portal')
    .analyze()

  if (violations.length > 0) {
    throw new Error(violations.map((v) =>
      `\n     [${v.impact}] ${v.id}: ${v.help} (${v.helpUrl})` +
      v.nodes.slice(0, 5).map((node) => `\n       - ${node.target.join(' ')}`).join(''),
    ).join(''))
  }
}

const audit = async (path) => {
  const response = await page.goto(`${base}${path}`)
  // Las 404 se auditan igual; cualquier otro error es un problema del stack, no de accesibilidad.
  if (response && response.status() >= 500) throw new Error(`HTTP ${response.status()}`)
  await page.waitForLoadState('networkidle')
  await scan()
}

// El primer Tab tiene que llegar al link para saltear el encabezado, y Enter mover el foco al contenido.
const skipLink = async (path) => {
  await page.goto(`${base}${path}`)
  await page.keyboard.press('Tab')
  const focused = await page.evaluate(() => document.activeElement?.textContent?.trim())
  if (focused !== 'Saltar al contenido') throw new Error(`el primer Tab enfoca "${focused}"`)
  await page.keyboard.press('Enter')
  await page.waitForFunction(() => document.activeElement?.id === 'contenido')
}

const storefront = [
  ['home', '/s/demo'],
  ['categoría', '/s/demo/c/remeras'],
  ['producto', '/s/demo/p/remera-oversize-algodon'],
  ['búsqueda con resultados', '/s/demo/buscar?q=remera'],
  ['búsqueda sin término', '/s/demo/buscar'],
  ['producto inexistente', '/s/demo/p/no-existe'],
]

for (const [name, path] of storefront) await step(`tienda escritorio: ${name}`, () => audit(path))

await page.setViewportSize({ width: 390, height: 844 })
for (const [name, path] of storefront) await step(`tienda móvil: ${name}`, () => audit(path))
await page.setViewportSize({ width: 1280, height: 900 })

await step('tienda: saltar al contenido con el teclado', () => skipLink('/s/demo'))

await step('panel: login', () => audit('/admin/login'))

await step('panel: login con error', async () => {
  // Otro email: el límite de intentos de login es por email+IP y admin.e2e.mjs ya usó los del dueño.
  await page.getByLabel('Email').fill('nadie@demo.test')
  await page.getByLabel('Contraseña').fill('mala')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.getByText('Email o contraseña incorrectos.').waitFor()
  await scan()
})

await step('panel: iniciar sesión', async () => {
  await page.getByLabel('Email').fill('owner@demo.test')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.waitForURL(`${base}/admin`)
})

const panel = [
  ['dashboard', '/admin'],
  ['productos', '/admin/products'],
  ['nuevo producto', '/admin/products/new'],
  ['categorías', '/admin/categories'],
  ['nueva categoría', '/admin/categories/new'],
  ['ajustes', '/admin/settings'],
  ['usuarios', '/admin/users'],
  ['mi cuenta', '/admin/account'],
]

for (const [name, path] of panel) await step(`panel: ${name}`, () => audit(path))

await step('panel: editar producto', async () => {
  await page.goto(`${base}/admin/products`)
  const href = await page.locator('a[href^="/admin/products/"]:not([href$="/new"])').first().getAttribute('href')
  await audit(href)
})

await step('panel: errores de validación en categoría', async () => {
  await page.goto(`${base}/admin/categories/new`)
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await page.getByRole('alert').first().waitFor()
  await scan()
})

await step('panel: saltar al contenido con el teclado', () => skipLink('/admin'))

await browser.close()
writeFileSync(`${outDir}/a11y-results.txt`, results.join('\n'))
console.log(results.join('\n'))
process.exitCode = results.some((line) => line.startsWith('FAIL')) ? 1 : 0
