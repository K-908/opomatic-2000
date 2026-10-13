import { expect, test } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await page.goto('/')
  await page.getByLabel('Nombre de usuario').fill('Gogloglo')
  await page.getByLabel('Contraseña').fill(process.env.OPOMATIC_E2E_GOGLOGLO_PASSWORD ?? '')
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page.getByRole('heading', { name: 'Resumen' })).toBeVisible()
})

test('frontend and API respond from the local test installation', async ({ page, request }) => {
  const health = await request.get('/health')
  expect(health.ok()).toBeTruthy()

  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Resumen' })).toBeVisible()
  await expect(page.getByText('Tu preparación, pregunta a pregunta.')).toHaveCount(0)
})

test('primary empty screens remain usable at 320 pixels', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 })

  for (const route of ['/', '/topics', '/questions', '/questions/new', '/tests/new', '/attempts', '/statistics']) {
    await page.goto(route)
    await expect(page.locator(route === '/' ? 'h2' : 'h1').first()).toBeVisible()
    const overflows = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(overflows, `${route} has horizontal overflow at 320px`).toBeFalsy()
  }
})
