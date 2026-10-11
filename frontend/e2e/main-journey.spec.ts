import { expect, test } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await page.goto('/')
  await page.getByLabel('Nombre de usuario').fill('Gogloglo')
  await page.getByLabel('Contraseña').fill(process.env.OPOMATIC_E2E_GOGLOGLO_PASSWORD ?? '')
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page.getByRole('heading', { name: 'Tu preparación, pregunta a pregunta.' })).toBeVisible()
})

test('creates content, completes a test and updates history and statistics', async ({ page }) => {
  await page.goto('/topics')
  await page.getByLabel('Nombre').fill('Tema E2E')
  await page.getByRole('button', { name: 'Crear tema' }).click()
  await expect(page.getByRole('heading', { name: 'Tema E2E' })).toBeVisible()

  await page.goto('/questions/new')
  await page.getByLabel('Tema').selectOption({ label: 'Tema E2E' })
  await page.getByLabel('Enunciado').fill('¿Cuál es la respuesta del recorrido E2E?')
  for (let position = 1; position <= 4; position += 1) {
    await page.getByLabel(`Opción ${position}`, { exact: true }).fill(`Respuesta ${position}`)
  }
  await page.getByLabel('Opción 2 correcta').check()
  await page.getByRole('button', { name: 'Guardar pregunta' }).click()
  await expect(page.getByRole('heading', { name: 'Pregunta guardada' })).toBeVisible()

  await page.goto('/tests/new')
  await page.getByRole('checkbox', { name: /Tema E2E/ }).check()
  await page.getByLabel('Número de preguntas').fill('1')
  await page.getByRole('button', { name: 'Comenzar test' }).click()
  await expect(page.getByRole('heading', { name: 'Pregunta 1 de 1' })).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  const questionCard = await page.locator('.question-card').boundingBox()
  const questionMap = await page.locator('.question-map').boundingBox()
  const questionIndex = await page.locator('.question-index').boundingBox()
  expect(questionCard).not.toBeNull()
  expect(questionMap).not.toBeNull()
  expect(questionIndex).not.toBeNull()
  expect(questionCard!.y).toBeLessThan(questionMap!.y)
  expect(questionIndex!.width).toBeLessThanOrEqual(48)

  await page.getByRole('radio').first().check()
  await page.getByRole('button', { name: 'Revisar y finalizar' }).click()
  await expect(page.getByRole('dialog')).toContainText('Has contestado todas las preguntas')
  await page.getByRole('button', { name: 'Confirmar entrega' }).click()

  await expect(page).toHaveURL(/\/attempts\/\d+$/)
  await expect(page.getByText('Pregunta 1 de 1')).toBeVisible()
  await expect(page.getByText('Tu respuesta')).toBeVisible()
  await expect(page.getByText('Respuesta correcta')).toBeVisible()

  await page.goto('/attempts')
  await expect(page.getByRole('link', { name: /Tema E2E/ })).toBeVisible()

  await page.goto('/')
  await expect(page.getByText('Tests terminados').locator('..').getByText('1', { exact: true })).toBeVisible()
  await expect(page.getByText('Preguntas contestadas').locator('..').getByText('1', { exact: true })).toBeVisible()

  await page.goto('/statistics')
  const topicRow = page.getByRole('row', { name: /Tema E2E/ })
  await expect(topicRow).toContainText('1')
  await expect(topicRow).not.toContainText('Sin datos')

  await page.setViewportSize({ width: 320, height: 720 })
  await expect(topicRow).toBeVisible()
  const overflows = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
  expect(overflows).toBeFalsy()

  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await page.getByLabel('Nombre de usuario').fill('Virginia')
  await page.getByLabel('Contraseña').fill(process.env.OPOMATIC_E2E_VIRGINIA_PASSWORD ?? '')
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page.getByText('Tests terminados').locator('..').getByText('0', { exact: true })).toBeVisible()

  await page.goto('/accesses')
  await expect(page.getByRole('heading', { name: 'Accesos recientes' })).toBeVisible()
  await expect(page.locator('.access-list code').first()).toContainText(/127\.0\.0\.1|::1/)
})
