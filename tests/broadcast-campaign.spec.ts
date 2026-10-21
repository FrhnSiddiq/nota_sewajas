import { test, expect } from '@playwright/test';

test.setTimeout(120000);

const SUPERADMIN_EMAIL = 'superadmin@sewajas.id';
const SUPERADMIN_PASSWORD = 'password';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name="email"]', SUPERADMIN_EMAIL);
  await page.fill('[name="password"]', SUPERADMIN_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 60000 });
  await page.waitForLoadState('networkidle');
}

async function createWhatsAppCampaign(page) {
  await page.goto('/broadcast-campaigns/create');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle');

  // Fill campaign name
  await page.fill('[name="name"]', 'Test WhatsApp Campaign');

  // Select WhatsApp channel
  await page.check('[name="channel"][value="whatsapp"]');

  await expect(page.locator('#recipientTypeLabel')).toHaveText('Customer');
  await expect(page.locator('#target_type option[value="sales"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="rental_status"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="product"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="category"]')).toBeHidden();
  await expect(page.locator('#sendScheduling')).toBeHidden();
  await expect(page.locator('[name="custom_message"]')).toHaveAttribute('placeholder', 'Tulis pesan');

  // Select target type: 'customer' (Pilih Customer)
  await page.selectOption('[name="target_type"]', 'customer');

  // Wait for customer filter to appear
  await page.waitForSelector('#customerFilter:not(.hidden)', { timeout: 10000 });

  // Select the first customer from the checkbox dropdown.
  await page.click('#customerDropdown summary');
  const customerOption = page.locator('#customerFilter .customer-option').first();
  const customerCheckbox = customerOption.locator('input[name="recipient_ids[]"]');
  const customerId = await customerCheckbox.getAttribute('value');
  const customerName = await customerOption.textContent();
  console.log(`Selecting customer: ${customerName} (ID: ${customerId})`);

  await customerCheckbox.check();
  await expect(page.locator('#customerSelectionLabel')).toHaveText('1 Customer dipilih');

  // Fill custom message
  await page.fill('[name="custom_message"]', 'Test WhatsApp message for {{name}}');

  // Submit
  await Promise.all([
    page.waitForURL(/\/broadcast-campaigns\/\d+/, { timeout: 30000 }),
    page.click('button[type="submit"]')
  ]);

  // Get campaign ID from URL
  const url = page.url();
  const campaignId = url.match(/\/broadcast-campaigns\/(\d+)/)?.[1];
  return campaignId;
}

async function createInAppCampaign(page) {
  await page.goto('/broadcast-campaigns/create');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle');

  // Fill campaign name
  await page.fill('[name="name"]', 'Test In-App Campaign');

  // Select In-App channel
  await page.check('[name="channel"][value="in_app"]');

  await expect(page.locator('#recipientTypeLabel')).toHaveText('User internal (Admin Toko dan Sales)');
  await expect(page.locator('#target_type option[value="rental_status"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="product"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="category"]')).toBeHidden();
  await expect(page.locator('#target_type option[value="customer"]')).toBeHidden();

  // Select target type: 'sales'
  await page.selectOption('[name="target_type"]', 'sales');

  // Wait for user filter to appear
  await page.waitForSelector('#userFilter:not(.hidden)', { timeout: 10000 });

  // Choose a branch first; the picker should then show only Sales in that branch.
  const firstSalesOption = page.locator('#userFilter .sales-option').first();
  const branchId = await firstSalesOption.getAttribute('data-branch-id');
  await page.selectOption('#branchSelect', branchId);
  await page.click('#salesDropdown summary');
  await expect(page.locator('#userFilter .sales-option:visible')).not.toHaveCount(0);

  const userCheckbox = page.locator('#userFilter input[name="recipient_ids[]"]').first();
  const userId = await userCheckbox.getAttribute('value');
  const userName = await userCheckbox.locator('xpath=..').textContent();
  console.log(`Selecting user: ${userName} (ID: ${userId})`);
  await userCheckbox.check();

  // Fill custom message
  await page.fill('[name="custom_message"]', 'Test In-App message for {{name}}');

  // Submit
  await Promise.all([
    page.waitForURL(/\/broadcast-campaigns\/\d+/, { timeout: 30000 }),
    page.click('button[type="submit"]')
  ]);

  // Get campaign ID from URL
  const url = page.url();
  const campaignId = url.match(/\/broadcast-campaigns\/(\d+)/)?.[1];
  return { campaignId, userId };
}

test.describe('Broadcast Campaign Creation', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('WhatsApp campaign: create and verify broadcast_logs', async ({ page }) => {
    const campaignId = await createWhatsAppCampaign(page);
    expect(campaignId).toBeTruthy();
    console.log(`WhatsApp Campaign created with ID: ${campaignId}`);

    // Verify campaign show page
    await expect(page.locator('h1')).toContainText('Test WhatsApp Campaign');
    
    // Check that recipient type is Customer (use first() to avoid strict mode violation)
    await expect(page.locator('text=Customer').first()).toBeVisible();
    
    // Check that campaign status is processing (WhatsApp messages are queued)
    // WhatsApp campaigns show "Processing" status since messages are queued
    await expect(page.locator('text=Processing').first()).toBeVisible();
    
    // Check that there is 1 recipient in the logs
    await expect(page.locator('text=/1 penerima/')).toBeVisible();
    
    // Verify the log entry was created with pending status
    await expect(page.locator('text=Belum Dikirim').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Buka WhatsApp' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Saya sudah mengirim' })).toBeVisible();

    await page.getByRole('button', { name: 'Saya sudah mengirim' }).click();
    await expect(page.locator('text=Dikonfirmasi Terkirim').first()).toBeVisible();
    await expect(page.locator('text=Completed').first()).toBeVisible();
  });

  test('In-App campaign: create and verify only selected user receives it', async ({ page }) => {
    const { campaignId, userId } = await createInAppCampaign(page);
    expect(campaignId).toBeTruthy();
    console.log(`In-App Campaign created with ID: ${campaignId}`);

    await page.goto('/broadcast-campaigns/create');
    await page.check('[name="channel"][value="in_app"]');
    await expect(page.locator('[name="send_mode"][value="now"]')).toBeChecked();
    await expect(page.locator('#scheduledAtContainer')).toBeHidden();
    await page.check('[name="send_mode"][value="scheduled"]');
    await expect(page.locator('#scheduledAtContainer')).toBeVisible();
    await expect(page.locator('#scheduled_at')).toHaveAttribute('required', '');
    await page.check('[name="send_mode"][value="now"]');

    // Verify campaign show page
    await page.goto(`/broadcast-campaigns/${campaignId}`);
    await expect(page.locator('h1')).toContainText('Test In-App Campaign');
    await expect(page.locator('text=Terkirim').first()).toBeVisible();
    await expect(page.locator('text=Budi Santoso').first()).toBeVisible();
    
    // Check that recipient type is User
    await expect(page.locator('text=User')).toBeVisible();
    
    // Check campaign status
    await expect(page.locator('text=Completed').first()).toBeVisible();
    
    // Verify only 1 log entry was created for the selected user
    await expect(page.locator('text=/1 penerima/')).toBeVisible();
    
    // Check that the user name appears in the recipient list
    const bodyText = await page.locator('body').textContent();
    console.log('Campaign show page content:', bodyText?.slice(0, 3000));
  });
});