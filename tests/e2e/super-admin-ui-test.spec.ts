import { test, expect, Page, BrowserContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const SUPER_ADMIN_EMAIL = 'superadmin@jasrental.id';
const SUPER_ADMIN_PASSWORD = 'password';

interface TestResult {
  page: string;
  status: 'PASS' | 'FAIL' | 'SKIP';
  details: string[];
}

const results: TestResult[] = [];

function addResult(page: string, status: 'PASS' | 'FAIL' | 'SKIP', details: string[]) {
  results.push({ page, status, details });
  console.log(`[${status}] ${page}: ${details.join('; ')}`);
}

async function login(page: Page): Promise<boolean> {
  try {
    await page.goto(`${BASE_URL}/login`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    await page.waitForSelector('input[name="password"]', { timeout: 10000 });
    
    await page.fill('input[name="email"]', SUPER_ADMIN_EMAIL);
    await page.fill('input[name="password"]', SUPER_ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    const loginIndicators = [
      'text=Selamat Siang',
      'text=Selamat Pagi', 
      'text=Selamat Sore',
      'text=Selamat Malam',
      'text=Super Administrator',
      'button:has-text("Keluar")',
      '[href*="/logout"]',
      '.user-menu'
    ];
    
    let isLoggedIn = false;
    for (const indicator of loginIndicators) {
      try {
        const visible = await page.locator(indicator).first().isVisible({ timeout: 2000 });
        if (visible) {
          isLoggedIn = true;
          console.log(`Login detected via: ${indicator}`);
          break;
        }
      } catch {}
    }
    
    if (!isLoggedIn) {
      const stillOnLogin = page.url().includes('/login');
      if (stillOnLogin) {
        const errorMsg = await page.locator('.alert-danger, .error, [role="alert"]').textContent().catch(() => '');
        console.error('Login failed - still on login page:', errorMsg);
      }
    } else {
      console.log('Login successful, current URL:', page.url());
    }
    
    return isLoggedIn;
  } catch (error) {
    console.error('Login failed:', error);
    return false;
  }
}

async function testNavigation(page: Page, menuText: string, expectedUrl: string): Promise<boolean> {
  try {
    // Try multiple selectors for the sidebar menu items
    const selectors = [
      `a.sidebar-item:has-text("${menuText}")`,
      `a:has-text("${menuText}")`,
      `button:has-text("${menuText}")`,
      `[role="menuitem"]:has-text("${menuText}")`
    ];
    
    for (const selector of selectors) {
      try {
        const element = page.locator(selector).first();
        if (await element.isVisible({ timeout: 2000 })) {
          await element.click();
          await page.waitForLoadState('networkidle');
          await page.waitForTimeout(1000);
          const url = page.url();
          if (url.includes(expectedUrl)) {
            return true;
          }
        }
      } catch {}
    }
    return false;
  } catch {
    return false;
  }
}

async function testSearch(page: Page, searchSelector: string = 'input[type="search"], input[placeholder*="cari" i], input[placeholder*="search" i], input[name="search"], input[name="q"]'): Promise<boolean> {
  try {
    const searchInput = page.locator(searchSelector).first();
    if (await searchInput.isVisible({ timeout: 2000 })) {
      await searchInput.fill('test');
      await page.waitForTimeout(500);
      await searchInput.clear();
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

async function testFilters(page: Page): Promise<boolean> {
  try {
    const filterSelects = page.locator('select[name*="filter" i], select[name*="status" i], select[name*="category" i], select[name*="branch" i], select[id*="filter" i], select[name="status"], select[name="branch_id"]');
    const count = await filterSelects.count();
    if (count > 0) {
      for (let i = 0; i < Math.min(count, 3); i++) {
        const select = filterSelects.nth(i);
        const options = await select.locator('option').all();
        if (options.length > 1) {
          await select.selectOption({ index: 1 });
          await page.waitForTimeout(500);
          await select.selectOption({ index: 0 });
        }
      }
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

async function testPagination(page: Page): Promise<boolean> {
  try {
    const pagination = page.locator('.pagination, nav[aria-label="pagination"], .paginate, [class*="pagination" i]');
    if (await pagination.first().isVisible({ timeout: 2000 })) {
      const nextBtn = page.locator('a:has-text("Next"), a:has-text("»"), button:has-text("Next"), [rel="next"]').first();
      if (await nextBtn.isVisible({ timeout: 1000 })) {
        await nextBtn.click();
        await page.waitForTimeout(500);
        const prevBtn = page.locator('a:has-text("Previous"), a:has-text("«"), button:has-text("Previous"), [rel="prev"]').first();
        if (await prevBtn.isVisible({ timeout: 1000 })) {
          await prevBtn.click();
          await page.waitForTimeout(500);
        }
        return true;
      }
    }
    return false;
  } catch {
    return false;
  }
}

async function testDetailView(page: Page): Promise<boolean> {
  try {
    const detailLinks = page.locator('a:has-text("Detail"), a:has-text("Lihat"), a[href*="/show"], a[href*="/detail"], button:has-text("Detail"), [title*="detail" i]');
    const count = await detailLinks.count();
    if (count > 0) {
      await detailLinks.first().click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
      await page.goBack();
      await page.waitForLoadState('networkidle');
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

async function testModals(page: Page): Promise<boolean> {
  try {
    const modalTriggers = page.locator('button:has-text("Tambah"), button:has-text("Create"), button:has-text("Add"), button[data-bs-toggle="modal"], [data-bs-target*="modal"], button[wire\\:click*="create"], button[wire\\:click*="openModal"], a[href*="/create"]');
    const count = await modalTriggers.count();
    if (count > 0) {
      await modalTriggers.first().click();
      await page.waitForTimeout(500);
      const modal = page.locator('.modal.show, [role="dialog"][aria-modal="true"]').first();
      if (await modal.isVisible({ timeout: 2000 })) {
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        return true;
      }
      // Check if it navigated to a create page instead of modal
      const url = page.url();
      if (url.includes('/create')) {
        await page.goBack();
        await page.waitForLoadState('networkidle');
        return true;
      }
    }
    return false;
  } catch {
    return false;
  }
}

async function testButtons(page: Page): Promise<boolean> {
  try {
    const backBtn = page.locator('a:has-text("Kembali"), a:has-text("Back"), button:has-text("Kembali"), button:has-text("Back"), [href*="index"], [wire\\:click*="back"]').first();
    if (await backBtn.isVisible({ timeout: 2000 })) {
      await backBtn.click();
      await page.waitForLoadState('networkidle');
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

async function testPage(page: Page, pageName: string, urlPath: string, menuText: string): Promise<void> {
  const details: string[] = [];
  let allPassed = true;

  try {
    const navSuccess = await testNavigation(page, menuText, urlPath);
    if (!navSuccess) {
      details.push(`Navigation failed - could not find menu "${menuText}" or navigate to ${urlPath}`);
      allPassed = false;
    } else {
      details.push('Navigation OK');
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    const hasData = await page.locator('table tbody tr, .data-row, [wire\\:id], .card, .table-responsive').first().isVisible({ timeout: 5000 }).catch(() => false);
    if (hasData) {
      details.push('Data loads OK');
    } else {
      details.push('WARNING: No data visible');
    }

    if (await testSearch(page)) {
      details.push('Search OK');
    } else {
      details.push('Search: Not available or failed');
    }

    if (await testFilters(page)) {
      details.push('Filters OK');
    } else {
      details.push('Filters: Not available or failed');
    }

    if (await testPagination(page)) {
      details.push('Pagination OK');
    } else {
      details.push('Pagination: Not available or failed');
    }

    if (await testDetailView(page)) {
      details.push('Detail view OK');
    } else {
      details.push('Detail view: Not available or failed');
    }

    if (await testModals(page)) {
      details.push('Modals/Create OK');
    } else {
      details.push('Modals/Create: Not available or failed');
    }

    if (await testButtons(page)) {
      details.push('Back button OK');
    } else {
      details.push('Back button: Not available or failed');
    }

  } catch (error) {
    details.push(`Error: ${error}`);
    allPassed = false;
  }

  addResult(pageName, allPassed ? 'PASS' : 'FAIL', details);
}

async function testPageDirect(page: Page, pageName: string, urlPath: string): Promise<void> {
  const details: string[] = [];
  let allPassed = true;

  try {
    await page.goto(`${BASE_URL}${urlPath}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    const url = page.url();
    if (url.includes(urlPath)) {
      details.push('Direct navigation OK');
    } else {
      details.push(`Navigation failed - expected ${urlPath}`);
      allPassed = false;
    }

    const hasData = await page.locator('table tbody tr, .data-row, [wire\\:id], .card, .table-responsive').first().isVisible({ timeout: 5000 }).catch(() => false);
    if (hasData) {
      details.push('Data loads OK');
    } else {
      details.push('WARNING: No data visible');
    }

    if (await testSearch(page)) {
      details.push('Search OK');
    } else {
      details.push('Search: Not available or failed');
    }

    if (await testFilters(page)) {
      details.push('Filters OK');
    } else {
      details.push('Filters: Not available or failed');
    }

    if (await testPagination(page)) {
      details.push('Pagination OK');
    } else {
      details.push('Pagination: Not available or failed');
    }

    if (await testDetailView(page)) {
      details.push('Detail view OK');
    } else {
      details.push('Detail view: Not available or failed');
    }

    if (await testModals(page)) {
      details.push('Modals/Create OK');
    } else {
      details.push('Modals/Create: Not available or failed');
    }

    if (await testButtons(page)) {
      details.push('Back button OK');
    } else {
      details.push('Back button: Not available or failed');
    }

  } catch (error) {
    details.push(`Error: ${error}`);
    allPassed = false;
  }

  addResult(pageName, allPassed ? 'PASS' : 'FAIL', details);
}

test.describe('Super Admin Full UI Testing', () => {
  let context: BrowserContext;
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
    page.setDefaultTimeout(30000);
    
    const loggedIn = await login(page);
    if (!loggedIn) {
      throw new Error('Failed to login as Super Admin');
    }
    console.log('✓ Logged in as Super Admin');
  });

  test.afterAll(async () => {
    await context.close();
    console.log('\n=== TEST SUMMARY ===');
    results.forEach(r => {
      console.log(`[${r.status}] ${r.page}`);
      r.details.forEach(d => console.log(`  - ${d}`));
    });
    
    const passCount = results.filter(r => r.status === 'PASS').length;
    const failCount = results.filter(r => r.status === 'FAIL').length;
    const skipCount = results.filter(r => r.status === 'SKIP').length;
    console.log(`\nTotal: ${results.length} | PASS: ${passCount} | FAIL: ${failCount} | SKIP: ${skipCount}`);
  });

  test('1. Dashboard', async () => {
    await testPage(page, 'Dashboard', '/dashboard', 'Dashboard');
  });

  test('2. Pengguna (User Management)', async () => {
    await testPage(page, 'Pengguna', '/users', 'Pengguna');
  });

  test('3. Cabang (Branches)', async () => {
    await testPage(page, 'Cabang', '/branches', 'Cabang');
  });

  test('4. Kategori (Categories)', async () => {
    await testPage(page, 'Kategori', '/categories', 'Kategori');
  });

  test('5. Produk (Products)', async () => {
    await testPage(page, 'Produk', '/products', 'Produk');
  });

  test('6. Pelanggan (Customers)', async () => {
    await testPage(page, 'Pelanggan', '/customers', 'Pelanggan');
  });

  test('7. Penyewaan (Rental)', async () => {
    await testPage(page, 'Penyewaan', '/rentals', 'Penyewaan');
  });

  test('8. Scan QR', async () => {
    await testPage(page, 'Scan QR', '/rentals/scan', 'Scan QR');
  });

  test('9. Broadcast (Campaigns)', async () => {
    await testPage(page, 'Broadcast', '/broadcast-campaigns', 'Broadcast');
  });

  test('10. Laporan Revenue', async () => {
    await testPageDirect(page, 'Laporan Revenue', '/reports/revenue');
  });

  test('11. Laporan Transaksi', async () => {
    await testPageDirect(page, 'Laporan Transaksi', '/reports/transactions');
  });

  test('12. Laporan Returns', async () => {
    await testPageDirect(page, 'Laporan Returns', '/reports/returns');
  });

  test('13. Laporan Stok', async () => {
    await testPageDirect(page, 'Laporan Stok', '/reports/stock');
  });

  test('14. Laporan Pelanggan', async () => {
    await testPageDirect(page, 'Laporan Pelanggan', '/reports/customers');
  });

  test('15. Laporan Pembayaran', async () => {
    await testPageDirect(page, 'Laporan Pembayaran', '/reports/payments');
  });

  test('16. Log Keseluruhan (Activity Log)', async () => {
    await testPage(page, 'Log Keseluruhan', '/reports/activity-log', 'Log Keseluruhan');
  });

  test('17. Notifikasi (via bell icon)', async () => {
    const details: string[] = [];
    let allPassed = true;
    
    try {
      // Test bell icon in navbar
      const bellIcon = page.locator('a[href*="/notifications"]').first();
      if (await bellIcon.isVisible({ timeout: 3000 })) {
        await bellIcon.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
        details.push('Bell icon navigation OK');
        
        // Test search, filters on notifications page
        if (await testSearch(page)) details.push('Search OK'); else details.push('Search: Not available');
        if (await testFilters(page)) details.push('Filters OK'); else details.push('Filters: Not available');
        if (await testPagination(page)) details.push('Pagination OK'); else details.push('Pagination: Not available');
        if (await testDetailView(page)) details.push('Detail view OK'); else details.push('Detail view: Not available');
      } else {
        details.push('Bell icon not found in navbar');
        allPassed = false;
      }
    } catch (error) {
      details.push(`Error: ${error}`);
      allPassed = false;
    }
    
    addResult('Notifikasi', allPassed ? 'PASS' : 'FAIL', details);
  });

  test('18. Pengaturan (Settings)', async () => {
    await testPage(page, 'Pengaturan', '/settings', 'Pengaturan');
  });

  test('19. Profil', async () => {
    await testPage(page, 'Profil', '/profile', 'Profil');
  });
});