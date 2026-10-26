import { chromium } from 'playwright'
import { mkdirSync, writeFileSync } from 'node:fs'

// Recorre el panel de administración en un navegador real contra el stack de docker-compose (ver README).
// Crea datos con un sufijo único, así se puede repetir sin chocar con corridas anteriores.
const base = process.env.BASE_URL ?? 'http://frontend:3000'
const outDir = process.env.OUT_DIR ?? '/out'
const run = Date.now().toString(36)
const productName = `Buzo E2E ${run}`
const categoryName = `Cat E2E ${run}`
const shot = (page, name) => page.screenshot({ path: `${outDir}/${name}.png`, fullPage: true })
const results = []
mkdirSync(outDir, { recursive: true })
const step = async (name, fn) => {
  try { await fn(); results.push(`OK   ${name}`) } catch (e) {
    results.push(`FAIL ${name}: ${e.message.split('\n')[0]} (url: ${page.url()})`)
    await shot(page, `fail-${results.length}`).catch(() => {})
  }
}

const browser = await chromium.launch()
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage()
page.on('pageerror', (e) => results.push(`PAGEERROR ${e.message}`))

await step('sin sesión redirige a login', async () => {
  await page.goto(`${base}/admin/products`)
  await page.waitForURL('**/admin/login')
})

await step('login con clave incorrecta muestra error genérico', async () => {
  await page.getByLabel('Email').fill('owner@demo.test')
  await page.getByLabel('Contraseña').fill('mala')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.getByText('Email o contraseña incorrectos.').waitFor()
})

await step('login correcto lleva al dashboard', async () => {
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.waitForURL(`${base}/admin`)
  await page.getByText('Primeros pasos').waitFor()
  await shot(page, '1-dashboard')
})

await step('crear categoría', async () => {
  await page.goto(`${base}/admin/categories/new`)
  await page.getByLabel('Nombre').fill(categoryName)
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await page.waitForURL('**/admin/categories')
  await page.getByRole('link', { name: categoryName }).first().waitFor()
  await shot(page, '2-categorias')
})

await step('categoría padre inválida / nombre vacío muestra error de validación', async () => {
  await page.goto(`${base}/admin/categories/new`)
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await page.getByRole('alert').first().waitFor()
})

await step('crear producto con variantes', async () => {
  await page.goto(`${base}/admin/products/new`)
  await page.getByLabel('Nombre', { exact: true }).fill(productName)
  await page.getByLabel('Precio', { exact: true }).fill('12.500,50')
  await page.getByLabel('Categoría').selectOption({ label: categoryName })
  await page.getByLabel('Estado').selectOption('active')
  await page.getByRole('button', { name: '＋ Opción' }).click()
  await page.getByLabel('Nombre de la opción 1').fill('talle')
  await page.getByRole('button', { name: '＋ Variante' }).click()
  await page.getByLabel('talle de la variante 1').fill('M')
  await page.getByLabel('Stock de la variante 1').fill('4')
  await page.getByRole('button', { name: '＋ Variante' }).click()
  await page.getByLabel('talle de la variante 2').fill('L')
  await page.getByLabel('Stock de la variante 2').fill('0')
  await shot(page, '3-form-producto')
  await page.getByRole('button', { name: 'Crear producto' }).click()
  await page.waitForURL('**/admin/products')
  await page.getByText(productName).waitFor()
  await shot(page, '4-lista-productos')
})

await step('validación del lado del cliente: precio inválido', async () => {
  await page.goto(`${base}/admin/products/new`)
  await page.getByLabel('Nombre', { exact: true }).fill('X')
  await page.getByLabel('Precio', { exact: true }).fill('gratis')
  await page.getByRole('button', { name: 'Crear producto' }).click()
  await page.getByText('Ingresá un precio válido.').waitFor()
})

await step('error del servidor (SKU duplicado) se muestra junto a la variante', async () => {
  await page.goto(`${base}/admin/products/new`)
  await page.getByLabel('Nombre', { exact: true }).fill('Dup')
  await page.getByLabel('Precio', { exact: true }).fill('100')
  await page.getByRole('button', { name: '＋ Opción' }).click()
  await page.getByLabel('Nombre de la opción 1').fill('talle')
  for (const n of [1, 2]) {
    await page.getByRole('button', { name: '＋ Variante' }).click()
    await page.getByLabel(`talle de la variante ${n}`).fill(String(n))
    await page.getByLabel(`SKU de la variante ${n}`).fill('IGUAL')
  }
  await page.getByRole('button', { name: 'Crear producto' }).click()
  await page.getByRole('alert').first().waitFor()
  await shot(page, '5-error-servidor')
})

await step('editar producto: cambia el precio', async () => {
  await page.goto(`${base}/admin/products`)
  await page.getByRole('link', { name: productName }).first().click()
  await page.getByLabel('Precio', { exact: true }).waitFor()
  if ((await page.getByLabel('Precio', { exact: true }).inputValue()) !== '12500.50') throw new Error('precio previo incorrecto')
  await page.getByLabel('Precio', { exact: true }).fill('15000')
  await page.getByRole('button', { name: 'Guardar cambios' }).click()
  await page.waitForURL('**/admin/products')
  await page.getByText('15.000').first().waitFor()
})

await step('subir imagen: muestra el resultado (error de R2 esperado en dev)', async () => {
  await page.goto(`${base}/admin/products/new`)
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64')
  await page.locator('#upload-product_image').setInputFiles({ name: 'p.png', mimeType: 'image/png', buffer: png })
  await page.waitForTimeout(2500)
  results.push('INFO upload -> ' + ((await page.getByRole('alert').allTextContents()).join(' | ') || 'sin alertas (¿subió?)'))
})

await step('configuración de la tienda', async () => {
  await page.goto(`${base}/admin/settings`)
  await page.getByLabel('Color principal', { exact: true }).fill('#0F766E')
  await page.getByLabel('WhatsApp', { exact: true }).fill('+54 9 11 5555-1234')
  await page.getByRole('button', { name: 'Guardar cambios' }).click()
  await page.getByText('Cambios guardados').waitFor()
  await shot(page, '6-settings')
})

await step('configuración inválida muestra el error del campo', async () => {
  await page.getByLabel('WhatsApp', { exact: true }).fill('abc')
  await page.getByRole('button', { name: 'Guardar cambios' }).click()
  await page.getByRole('alert').first().waitFor()
})

await step('mi plan muestra el plan, el uso y los planes', async () => {
  await page.getByRole('link', { name: 'Mi plan' }).click()
  await page.getByRole('heading', { name: /^Plan Intermediate/ }).waitFor()
  await page.getByRole('progressbar', { name: 'Productos' }).waitFor()
  await page.getByRole('radio', { name: /Anual/ }).click()
  await page.getByText('por año', { exact: false }).first().waitFor()
})

// ---- gestión de usuarios -------------------------------------------------------------------------
const newEmail = `e2e-${run}@demo.test`
const strongPassword = `Clave${run}9876`
let temporary = ''

const logout = async () => {
  // El indicador flotante de Next (solo dev) tapa este botón: se activa con el teclado.
  await page.getByRole('button', { name: 'Cerrar sesión' }).first().focus()
  await page.keyboard.press('Enter')
  await page.waitForURL('**/admin/login')
}
const login = async (email, password) => {
  await page.goto(`${base}/admin/login`)
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Ingresar' }).click()
}

await step('usuarios: el dueño crea un editor y ve la contraseña temporal una sola vez', async () => {
  await page.goto(`${base}/admin/users`)
  await page.getByLabel('Nombre', { exact: true }).fill('Editora E2E')
  await page.getByLabel('Email', { exact: true }).fill(newEmail)
  await page.getByRole('button', { name: 'Crear usuario' }).click()
  const code = page.locator('[role=status] code')
  await code.waitFor()
  temporary = (await code.innerText()).trim()
  if (temporary.length !== 14) throw new Error(`contraseña temporal inesperada: ${temporary}`)
  await page.getByText(newEmail).first().waitFor()
  await shot(page, '7-usuarios')
  await page.reload()
  if ((await page.locator('[role=status] code').count()) !== 0) throw new Error('la contraseña temporal volvió a mostrarse')
})

await step('usuarios: email duplicado muestra error', async () => {
  await page.getByLabel('Nombre', { exact: true }).fill('Otra')
  await page.getByLabel('Email', { exact: true }).fill(newEmail)
  await page.getByRole('button', { name: 'Crear usuario' }).click()
  await page.getByRole('alert').first().waitFor()
})

await step('primer ingreso: obliga a cambiar la contraseña y bloquea el resto del panel', async () => {
  await logout()
  await login(newEmail, temporary)
  await page.waitForURL('**/admin/account')
  await page.goto(`${base}/admin/products`)
  await page.waitForURL('**/admin/account')
  await shot(page, '8-cambio-forzado')
})

await step('cambio de contraseña: rechaza débil y acepta una buena', async () => {
  await page.getByLabel('Contraseña temporal').fill(temporary)
  await page.getByLabel('Nueva contraseña', { exact: true }).fill('corta1')
  await page.getByLabel('Repetí la nueva contraseña').fill('corta1')
  await page.getByRole('button', { name: 'Guardar contraseña' }).click()
  await page.getByRole('alert').first().waitFor()
  await page.getByLabel('Nueva contraseña', { exact: true }).fill(strongPassword)
  await page.getByLabel('Repetí la nueva contraseña').fill(strongPassword)
  await page.getByRole('button', { name: 'Guardar contraseña' }).click()
  await page.waitForURL(`${base}/admin`)
})

await step('el editor ve solo Productos y Categorías, y no puede administrar usuarios', async () => {
  const nav = page.getByRole('navigation', { name: 'Panel' })
  await nav.getByRole('link', { name: 'Productos' }).waitFor()
  for (const hidden of ['Usuarios', 'Mi tienda']) {
    if ((await nav.getByRole('link', { name: hidden }).count()) !== 0) throw new Error(`el editor ve "${hidden}"`)
  }
  await page.goto(`${base}/admin/users`)
  await page.getByText('Solo el dueño de la tienda puede administrar usuarios.').waitFor()
})

await step('resetear la clave de la editora: la anterior deja de funcionar y vuelve a ser forzada', async () => {
  await logout()
  await login('owner@demo.test', 'password')
  await page.waitForURL(`${base}/admin`)
  await page.goto(`${base}/admin/users`)
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('row', { name: new RegExp(newEmail) }).getByRole('button', { name: 'Resetear clave' }).click()
  const code = page.locator('[role=status] code')
  await code.waitFor()
  const reset = (await code.innerText()).trim()
  await logout()
  await login(newEmail, strongPassword)
  await page.getByText('Email o contraseña incorrectos.').waitFor()
  await page.getByLabel('Contraseña').fill(reset)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.waitForURL('**/admin/account')
  await logout()
})

await step('cambiar rol, no poder eliminarse a uno mismo y eliminar a la editora', async () => {
  await login('owner@demo.test', 'password')
  await page.waitForURL(`${base}/admin`)
  await page.goto(`${base}/admin/users`)
  const own = page.getByRole('row', { name: /owner@demo.test/ })
  if ((await own.getByRole('button', { name: 'Eliminar' }).count()) !== 0) throw new Error('puede eliminarse a sí mismo')
  const row = page.getByRole('row', { name: new RegExp(newEmail) })
  await row.getByRole('button', { name: 'Editar' }).click()
  await page.getByLabel(`Rol de ${newEmail}`).selectOption('owner')
  await page.getByRole('button', { name: 'Guardar', exact: true }).click()
  await page.getByRole('row', { name: new RegExp(`${newEmail}.*Dueño`) }).waitFor()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('row', { name: new RegExp(newEmail) }).getByRole('button', { name: 'Eliminar' }).click()
  await page.getByText(newEmail).waitFor({ state: 'detached' })
  await logout()
  await login(newEmail, strongPassword)
  await page.getByText('Email o contraseña incorrectos.').waitFor()
  await login('owner@demo.test', 'password')
  await page.waitForURL(`${base}/admin`)
})

await step('cerrar sesión', async () => {
  await page.goto(`${base}/admin`)
  // El indicador flotante de Next (solo dev) tapa este botón: se activa con el teclado.
  await page.getByRole('button', { name: 'Cerrar sesión' }).first().focus()
  await page.keyboard.press('Enter')
  await page.waitForURL('**/admin/login')
  await page.goto(`${base}/admin/products`)
  await page.waitForURL('**/admin/login')
})

await browser.close()
writeFileSync(`${outDir}/results.txt`, results.join('\n'))
console.log(results.join('\n'))
// Código de salida != 0 si algo falló, para scripts y CI.
process.exitCode = results.some((line) => /^(FAIL|PAGEERROR)/.test(line)) ? 1 : 0
