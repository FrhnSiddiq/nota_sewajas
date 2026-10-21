# SewaJas — Full Audit Report

Source reconciliation: 2026-09-28

This report began as a source snapshot dated 2026-07-28. Findings below have been reconciled against the current repository where indicated. Status describes source code only; it does not confirm production deployment, production database migrations, hosting configuration, or runtime behavior.

Status labels: **OPEN** = still present in source; **PARTIAL** = some paths are addressed but behavior remains inconsistent; **RESOLVED IN SOURCE** = the old finding is no longer present in the audited source; **REVIEW** = not confirmed sufficiently to assert as a current defect.

---

## 1. Executive Summary

The source contains substantial functionality and several remaining inconsistencies. This is a repository audit, not a production health check. Some items in the original July snapshot have since been fixed or were inaccurate when compared with current routes/models.

**Modules audited:**
- Customer (CRUD, soft delete, restore, search, duplicate phone)
- Rental (create, return, payment, status, QR, guarantee)
- Rental receipts (show, print/PDF, QR, WhatsApp; handled through rental receipt flows)
- Payment (create, update, delete, void, refund)
- Product (CRUD, QR, stock)
- Reports (revenue, transactions, stock, returns)
- Dashboard (periodic data polling, widgets + charts)
- Search (global cross-entity)
- Notifications, Broadcast, Settings, Branch, User

**Overall source risk: Mixed; open transaction, return-reporting, and authorization-scope concerns remain. No production risk rating can be established from source alone.**

**Recent behavior confirmed in source:**
- ActivityLog model-created handling resolves the file writer from the container and reports file-write exceptions. Database record creation remains in calling controllers/services and can still fail its transaction.
- User create/edit requires a main branch for Admin Toko; `UserController` stores Admin Toko with `branch_access_type = all`, while Sales uses `specific`. Query-level scope still varies by feature.
- Product codes are generated server-side and are non-editable in create/edit forms. Products have a unique code index; concurrent collision can fail the create request.
- Stock is stored by size in `product_sizes`; the Sales stock form sends `size_id`, and the controller validates product ownership before changing one size and syncing aggregate product stock.
- Notification actions are owner-scoped; form requests redirect with flash data while fetch/AJAX requests return JSON for the frontend to consume.
- Logout confirmation is custom; cancel sends no logout request, while confirmation uses existing POST/CSRF logout flow.

---

## 2. Findings from Original BUG Register (Current Status)

### BUG-01 [PARTIAL]: Payment Number Race Condition
| Field | Value |
|-------|-------|
| Issue | `RentalService::generatePaymentNumber()` uses locking/sequence logic in its path, but `PaymentService::create()` and other timestamp-based paths do not use the same generator. Concurrent requests can collide. |
| Root Cause | Payment number generation is not centralized across all payment paths. |
| Files | `app/Services/PaymentService.php`, `app/Services/RentalService.php` |
| Risk | `payments.payment_number` has a unique index, so a collision should fail the request rather than persist as a duplicate. |
| Fix Plan | Route all payment creation paths through one locked/sequence-safe generator. |

### BUG-02 [RESOLVED IN SOURCE]: Invoice Number Has DB-Level Uniqueness
| Field | Value |
|-------|-------|
| Issue | The original audit reported missing database uniqueness. Current source defines a unique index on `rentals.invoice_number`; the generator also uses lock/retry logic. |
| Root Cause | The earlier finding predates the current migration/source state. |
| Files | Rental invoice migration(s), `app/Services/RentalService.php` |
| Risk | The repository source has a database guard; whether production has run the relevant migrations is not verified here. |
| Fix Plan | No source change indicated by this finding. Verify production migration state separately if needed. |

### BUG-03 [OPEN]: `confirmReturnAjax` Reads Missing Model Attributes
| Field | Value |
|-------|-------|
| Issue | The response builds `size` and `price` from `$item->size` and `$item->price`, while `RentalItem` stores `product_size` and `price_per_day` and has no accessors for those short names. |
| Root Cause | The serializer reads model attributes that are not defined. This is not simply a JSON key-name mismatch. |
| Files | `app/Http/Controllers/RentalController.php`, `resources/views/rentals/show.blade.php` |
| Risk | Size/price values in the return payload may be null; browser behavior was not runtime-tested in this documentation task. |
| Fix Plan | Serialize from `product_size` and `price_per_day`, then verify the response and panel. |

### BUG-04 [OPEN]: `RentalReturn` Table Never Populated by Active Return Flow
| Field | Value |
|-------|-------|
| Issue | `RentalReturn` model and table exist, but the active return flow does not create `RentalReturn` records. The dashboard `DashboardService` queries `rental_returns` for a return-today metric. |
| Root Cause | Business logic was refactored to store return data on `rental_items` and `rentals` directly, but the old `rental_returns` table queries were never removed from dashboard. |
| Files | `app/Services/RentalService.php`, `app/Models/RentalReturn.php`, `app/Services/DashboardService.php` |
| Risk | Dashboard metrics depending on this table may be zero or inconsistent with returns recorded in `rentals`/`rental_items`. |
| Fix Plan | Either (a) create `RentalReturn` records in `processReturn()`, or (b) rewrite dashboard to count rentals with `rental_status = 'returned'` and `actual_return_date = today()`. |

### BUG-05 [RESOLVED IN SOURCE]: `products.update` Route Duplication
| Field | Value |
|-------|-------|
| Issue | The July snapshot reported duplicate PATCH/PUT routes. Current `routes/web.php` contains one `PUT /products/{product}` route for update. |
| Root Cause | Historical explanation from the July snapshot; not re-established in current source. |
| Files | `routes/web.php` |
| Risk | The duplicate-route risk is not present in the audited source. |
| Fix Plan | No source change indicated by this finding. |

### BUG-06 [RESOLVED IN SOURCE]: Duplicate Report Routes
| Field | Value |
|-------|-------|
| Issue | The July snapshot reported duplicate `reports/stock` and `reports/returns` routes. Current source contains one route definition for each. |
| Root Cause | Historical explanation from the July snapshot; not re-established in current source. |
| Files | `routes/web.php` |
| Risk | The duplicate-route risk is not present in the audited source. Current report routes are restricted to Super Admin/Admin Toko. |
| Fix Plan | No source change indicated by this finding. |

### BUG-07 [OPEN]: `RentalController::updateStatus` Late Fee Formula Inconsistency
| Field | Value |
|-------|-------|
| Issue | `RentalService::processReturn()` computes the fee from subtotal/duration and a 0.5 multiplier, whereas `RentalController::updateStatus()` uses `config('app.late_fee_per_day', 0)`. |
| Root Cause | Business formulas differ between the service and controller paths. |
| Files | `app/Http/Controllers/RentalController.php`, `app/Services/RentalService.php` |
| Risk | Late fee amount can differ by return path. |
| Fix Plan | Use one shared formula and configuration source, then test both paths. |

### BUG-08 [OPEN]: `RentalItem` Size Attribute Serialization
| Field | Value |
|-------|-------|
| Issue | `product_size` is fillable, but the model has no `size` accessor; controller paths that read `$item->size` do not resolve that database column automatically. |
| Root Cause | A serializer/view path uses an undefined short attribute. |
| Files | `app/Models/RentalItem.php`, `resources/views/rentals/show.blade.php` |
| Risk | Size can be missing in payload/UI paths that use `size`. |
| Fix Plan | Read `product_size` explicitly or define an accessor and align consumers. |

### BUG-09 [OPEN]: `Product` Model `getPhotoUrlAttribute` Uses Unsplash External URL
| Field | Value |
|-------|-------|
| Issue | Default product photo falls back to `https://images.unsplash.com/...`. If offline or API changes, images break. |
| Root Cause | Hardcoded external dependency. |
| Files | `app/Models/Product.php` |
| Risk | Broken images in production. |
| Fix Plan | Use local placeholder image or SVG data URI. |

### BUG-10 [OPEN]: `User` Model Avatar Falls Back to External Service
| Field | Value |
|-------|-------|
| Issue | `User::getAvatarUrlAttribute` uses `ui-avatars.com` API. Same offline risk. |
| Root Cause | External dependency. |
| Files | `app/Models/User.php` |
| Risk | Broken avatars. |
| Fix Plan | Use local generated SVG or initial avatar component. |

---

## 3. High-Priority Issues

### HIGH-01 [PARTIAL]: Activity Log Record Creation Is Distributed
Several controller/service paths still construct `ActivityLog::create([...])` directly. The current source also has `ActivityLogFormatter`, `ActivityLogFileService`, and a model-created callback that resolves the file service through the container and reports file-write failures. The file service does not centralize creation of every database record.

| Files | 8+ controllers/services |
| Risk | Inconsistent logs, missing fields, hard to change format. |
| Fix Plan | Consider a single database logging API if desired; preserve current transaction/audit behavior. |

### HIGH-02 [PARTIAL]: Authorization Rules Are Split Across Routes and Controllers
Policies are registered in `AuthServiceProvider`, but most route access uses `CheckRole` and some actions use inline checks; not every action invokes a policy. This is not, by itself, evidence of an authorization bypass.

| Files | All controllers |
| Risk | Rules can drift across route groups, controllers, and branch-scoped queries. |
| Fix Plan | Audit authorization at route/controller/query level and keep them consistent; policy use is one possible pattern. |

### HIGH-03 [OPEN]: `RentalService::createRental` Locks Products More Than Once
In `RentalService::createRental()`, product records are locked during validation and locked again in the item creation loop.

| Files | `app/Services/RentalService.php`, `createRental()` |
| Risk | Unnecessary DB lock contention; slower under load. |
| Fix Plan | Reuse the locked product instances from Step 1. |

### HIGH-04 [REVIEW]: Guarantee Update Logic Duplication
The July snapshot reported overlapping guarantee handling in `RentalController::update()` and `RentalService::createRental()`. The exact duplication and behavioral impact have not been re-established as a current defect in this reconciliation.

| Files | `app/Http/Controllers/RentalController.php` |
| Risk | Potential maintenance duplication if both paths implement the same behavior. |
| Fix Plan | Re-compare the current create/update paths before proposing a refactor. |

### HIGH-05 [REVIEW]: Phone Normalization Finding Not Reconfirmed
The July snapshot associated phone normalization with `RentalController::update()`, but this reconciliation did not establish a current data defect in that path. `CustomerController` contains phone normalization logic; runtime data consistency is not proven from source alone.

| Files | `app/Http/Controllers/CustomerController.php`, `app/Http/Controllers/RentalController.php` |
| Risk | No current risk is asserted from this historical finding. |
| Fix Plan | Reproduce with actual persisted phone values before making code changes. |

### HIGH-06 [OPEN, SCOPE QUALIFIED]: Search Includes Soft-Deleted Customers
| Field | Value |
|-------|-------|
| Issue | `SearchController::index()` uses `Customer::withTrashed()`, so soft-deleted customers can appear. Query scope differs by role: Sales are limited by ownership; Admin Toko currently has `branch_access_type = all`. |
| Root Cause | Deleted records are included in the search query; branch/ownership scope is role-specific. |
| Files | `app/Http/Controllers/SearchController.php` |
| Risk | Deleted records may appear without clear UI indication. This source review does not claim that every role sees every branch. |
| Fix Plan | Confirm intended role visibility and mark/de-scope deleted results if required. |

### HIGH-07 [OPEN]: `RentalController::edit()` Restricts Products to "Jas%" Category
| Field | Value |
|-------|-------|
| Issue | `RentalController::edit()` filters products to categories whose names start with "Jas". This hardcoded business rule can prevent editing rentals with other product categories. |
| Root Cause | Legacy hardcoded filter. |
| Files | `app/Http/Controllers/RentalController.php` |
| Risk | Data integrity; rentals with non-Jas products cannot be edited. |
| Fix Plan | Remove hardcoded filter; show all products or use rental's existing products + available products. |

### HIGH-08 [OPEN]: `Rental` Model `status_label` Has Additional Status Labels
`Rental::statusLabels()` includes `'booked'` and `'completed'` which are never set by any controller or seeder. The actual statuses are `waiting`, `active`, `overdue`, `returned`, `cancelled`.

| Files | `app/Models/Rental.php`, `statusLabels()` |
| Risk | UI shows "Booked" or "Completed" badges if data is inconsistent. |
| Fix Plan | Remove phantom statuses or add migration/constants for them. |

### HIGH-09 [OPEN, IMPACT UNMEASURED]: Dashboard Polling Every 10 Seconds
| Field | Value |
|-------|-------|
| Issue | `_dashboard_common.blade.php` polls `/dashboard/data` every 10 seconds. No exponential backoff is present in the inspected flow. |
| Root Cause | Aggressive polling. |
| Files | `resources/views/dashboard/_dashboard_common.blade.php` |
| Risk | May add request load with many sessions; overload has not been demonstrated by a load test. |
| Fix Plan | Measure first; add backoff/pause if observed load warrants it. |

### HIGH-10 [OPEN, UNIQUE INDEX GUARD]: `ProductController::generateCode()` Concurrency
| Field | Value |
|-------|-------|
| Issue | `ProductController::generateCode()` generates sequential `PRD-XXX` codes without `lockForUpdate()`. The unique index on `products.code` prevents a duplicate from being persisted, but concurrent requests can hit a constraint error. |
| Root Cause | No pessimistic lock. |
| Files | `app/Http/Controllers/ProductController.php` |
| Risk | Failed create request during a concurrent collision; silent duplicate persistence is guarded by the unique index. |
| Fix Plan | Use an atomic sequence or retry on unique-index collision; retain the database unique index as the final guard. |

---

## 4. Medium-Priority Issues

UI consistency observations below describe style variation in the source; they are not production regressions or automatic fix requirements.

### MED-01 [OBSERVATION]: Inconsistent Card Classes Across Views
- `customers/index.blade.php` uses `ds-card` and `card-container`
- `rentals/index.blade.php` uses `card-container`
- `products/index.blade.php` uses `card`
- `reports/*.blade.php` uses `card`
- `dashboard/*.blade.php` uses `ds-card`

### MED-02 [OBSERVATION]: Inconsistent Button Classes
- Some views use `btn-primary` / `btn-secondary` (CSS classes)
- Some views use inline Tailwind button classes (for example, rental detail actions).
- Some views use the `components.btn` Blade include

### MED-03 [OBSERVATION]: Inconsistent Avatar Rendering
- Sidebar user card: gradient `#2563eb → #1d4ed8` (inline style)
- Customer table: gradient `#6366f1 → #8b5cf6` (inline style)
- Search page: solid `bg-blue-50 font-bold text-blue-700` (no gradient)
- Customer profile: uses external `ui-avatars.com`

### MED-04 [REVIEW]: `RentalController::scanQr()` Accepts Receipt Prefix
The scan page accepts `RCPT-INV2026070310014` format but `Rental` model `invoice_number` is generated with `INV` prefix only. Receipt numbers are `RCPT-INV...` but they point to the same rental. This is OK functionally but confusing.

### MED-05 [OBSERVATION]: `CustomerController::index()` Filter Labels
Route param is `status` but values are `active`, `deactivated`, `all`. The view uses `request('status')`. The `deactivatedCount` query uses `onlyTrashed()`. This is correct but the naming is inconsistent with the user-facing "Dinonaktifkan" label.

### MED-06 [REVIEW]: Payment Status Recalculation Logging
The July snapshot reported a `recalculate_payment_status` activity entry on each recalculation. Current frequency/volume has not been measured in runtime; treat log-volume impact as a review item.

### MED-07 [REVIEW]: Rental Receipt Output
`RentalReceiptController` exists and has show/PDF/QR/WhatsApp methods. Source inventory confirms these paths exist; output correctness and browser/PDF runtime behavior were not exercised in this documentation task.

### MED-08 [OPEN, CSS SCOPE]: `notifications/index.blade.php` Uses `btn-sm`
`class="btn-secondary btn-sm"` — `btn-sm` is not defined in CSS.

### MED-09 [RESOLVED IN SOURCE]: `remaining_amount` Accessor
The current `Rental` model defines a `remaining_amount` accessor; the earlier claim that the computed attribute was missing is not current.

### MED-10 [REVIEW]: `Payment` Model Lacks `SoftDeletes`
The current model uses hard deletion on the applicable path. Whether payments must be soft-deleted is a retention/business requirement not established by this source audit; do not add a migration solely from this finding.

---

## 5. Low-Priority / Technical Debt

### LOW-01 [PRESENT]: Legacy `.bb_tmp_` File in Controllers
`app/Http/Controllers/.bb_tmp_confirm_return_ajax_snippet_note.txt` — leftover from AI coding session.

### LOW-02 [REVIEW]: Historical TODO Markdown Inventory
The July snapshot listed several TODO files. Their current ownership/use was not re-audited as part of the source reconciliation; treat cleanup as repository housekeeping, not a confirmed defect.

### LOW-03 [NOT A DEFECT]: Root `package-lock.json`
The repository has a root `package.json`/Vite setup, so a root npm lockfile is expected and is not, by itself, a problem.

### LOW-04 [RESOLVED IN SOURCE]: Composer Package Name
Current `composer.json` identifies the project as `sewajas/rental-jas`; the earlier Laravel boilerplate-name finding no longer applies.

### LOW-05 [REVIEW]: Role Helper and Middleware Coexist
The model exposes role helpers and routes use `CheckRole`. Their coexistence is not inherently a defect; audit consistency where permissions are changed.

### LOW-06 [REVIEW]: `waiting` Not Accepted by `updateStatus`
`RentalController::updateStatus()` validates `rental_status` as `active,returned,overdue` only — `waiting` is excluded.

This may be intentional because the endpoint handles a limited set of status transitions; verify expected workflow before treating it as a bug.

### LOW-07 [RESOLVED IN SOURCE]: `CustomerPolicy` Registration
`AuthServiceProvider` maps `CustomerPolicy` to `Customer`; the earlier uncertainty is resolved in the current source.

### LOW-08 [REVIEW]: `displayedPhotoModal` State Name
The July snapshot noted the `displayedPhotoModal` state name. It appears only as an initialization in the current template search; intended use/typo has not been established.

### LOW-09 [OPEN, NON-BLOCKING]: `lucide.createIcons()` Called in Multiple Views
Called in `DOMContentLoaded`, `alpine:init`, and `@push('scripts')` in multiple views. Should be called once globally.

### LOW-10 [OPEN]: `RentalService::generateQrCode()` Uses `file_put_contents` Without Error Handling
If `storage/app/public/qrcodes/rentals/` is not writable, the QR generation fails silently or with a PHP warning.

---

## 6. UI Consistency Audit

| Component | Current State | Required State |
|-----------|--------------|----------------|
| **Cards** | Mix of `ds-card`, `card`, `card-container`, `stat-card` with different shadows, borders, radii | Unified `ds-card` class: `rounded-xl border shadow-sm hover:shadow-md transition-all` |
| **Buttons** | Mix of `btn-primary`, `btn-secondary`, inline Tailwind, missing `rounded-xl` on some | All buttons: `rounded-xl border min-h-[44px] px-5 hover:-translate-y-0.5 transition-all focus:ring-2` |
| **Search Boxes** | Navbar has `ds-search` (height 50px, rounded-[18px]); page search uses `form-input` (rounded-xl) | Unified: `form-input pl-10 rounded-xl` with icon vertically centered |
| **Dropdowns** | Some use `appearance-none` with custom chevron; others are plain selects | Unified: `form-input pr-10 appearance-none` with consistent chevron |
| **Tables** | `elegant-table` exists but some tables use plain `w-full` or `table` | All tables wrapped in `ds-card overflow-hidden` with `elegant-table` |
| **Forms** | Labels sometimes `text-sm font-medium`, sometimes `text-xs font-semibold uppercase` | Unified: `block text-sm font-semibold mb-1.5` |
| **Avatar** | Mix of inline gradients, solid blue boxes, external APIs | Use `avatar-premium` class (gradient indigo→violet + ring) |
| **Badges** | Mix of `badge-green`, `badge-red`, inline `bg-amber-100`, `badge-menunggu`, etc. | Unified: `badge` component with `type` param |
| **Empty States** | Custom HTML in each view; `components.empty-state` exists but rarely used | Use `components.empty-state` everywhere |
| **Modals** | Shared SweetAlert2 `confirmAction()` is used for common confirmation flows, including logout; feature-specific modals remain | Reuse the existing helper where appropriate; this audit does not recommend creating a duplicate global modal |
| **Pagination** | `components.pagination` exists; some views use `$paginator->links()` without component | Always use `components.pagination` |

---

## 7. Dependency Graph (Simplified)

```
Routes (web.php)
├── DashboardController → DashboardService → [Customer, Product, Rental, Payment, RentalReturn, ActivityLog, Guarantee]
├── CustomerController → Customer, Rental, Payment, Guarantee, ActivityLog
├── RentalController → RentalService → [Rental, RentalItem, Product, ProductSize, Payment, Guarantee, ActivityLog, QrCode]
│   └── RentalReceiptController → Rental, DomPDF
├── PaymentController → PaymentService → [Rental, Payment, ActivityLog, RentalService]
├── ProductController → Product, ProductSize, Category, QrCode
├── SearchController → Customer, Rental, Product
├── ReportController → [Rental, Product, Branch, Category, Payment, DomPDF]
├── BranchController → Branch
├── UserController → User
├── CategoryController → Category
├── NotificationController → Notification
├── BroadcastController → BroadcastTemplate, BroadcastSchedule
└── SettingsController → Setting (via DB)

Models
├── User ──┬── branch
│         ├── branchAccess (user_branch_access)
│         ├── rentals (created_by)
│         ├── customer (hasOne)
│         └── notifications
├── Customer ──┬── branch
│             ├── user
│             ├── rentals
│             └── broadcastLogs
├── Rental ──┬── branch
│           ├── customer
│           ├── createdBy
│           ├── returnedBy
│           ├── items
│           ├── guarantees
│           ├── payments
│           ├── returnRecord
│           ├── activityLogs
│           └── statusHistories
├── RentalItem ──┬── rental
│               └── product
├── Payment ──┬── rental
│           └── receivedBy
├── Product ──┬── branch
│           ├── category
│           ├── sizes (ProductSize)
│           └── rentalItems
├── Guarantee ── rental
├── Branch ──┬── users
│           ├── rentals
│           ├── customers
│           └── products
└── RentalReturn ── rental
```

---

## 8. Current Status Summary

This is a status index, not a severity score or a production release gate.

| Status | Findings |
|--------|----------|
| Open/partial findings | BUG-01, BUG-03, BUG-04, BUG-07, BUG-08, BUG-09, BUG-10 |
| Open/partial architecture, authorization, or performance | HIGH-01, HIGH-02, HIGH-03, HIGH-06, HIGH-07, HIGH-08, HIGH-09, HIGH-10 |
| Review / impact not established | HIGH-04, HIGH-05, MED-04, MED-06, MED-07, MED-10, LOW-02, LOW-05, LOW-06, LOW-08 |
| Resolved in current source | BUG-02, BUG-05, BUG-06, MED-09, LOW-04, LOW-07 |
| UI observations / technical debt | MED-01, MED-02, MED-03, MED-05, MED-08, LOW-01, LOW-09, LOW-10 |
| Not a defect by itself | LOW-03 |

---

## 9. Suggested Follow-Up Order

1. Reproduce and resolve payment-number and late-fee inconsistencies across every write path.
2. Verify the return payload and dashboard metric against a test database; correct the `RentalItem` attribute mapping and `rental_returns` query/source contract.
3. Review Admin Toko branch scope and soft-deleted customer search against intended access rules. Current Admin Toko `branch_access_type = all` must be considered in that review.
4. Exercise product and invoice code generation under concurrent requests; the unique indexes guard persisted duplicates but a collision may fail the request.
5. Schedule remaining UI/technical debt by user impact. Findings marked resolved/review above are not current fix instructions.

No application source, migration, or database changes were made as part of this documentation reconciliation.

---

*End of Audit Report*
