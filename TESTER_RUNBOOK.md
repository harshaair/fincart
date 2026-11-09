# FinCart — QA Test Execution Script & Tester Runbook

> **Document Type**: Manual QA Test Execution Script  
> **Target Audience**: Quality Assurance (QA) Engineers, Manual Testers, Evaluators  
> **Application Under Test**: FinCart Wealth, Tax & CA Advisory Platform  
> **Base URL**: Your deployed Vercel URL (or `http://localhost/fincart`)

---

## 1. Quick Reference: Test Credentials

Use these pre-configured test accounts to evaluate each user role:

| Persona / Role | Email | Password | Landing Portal | Purpose |
|---|---|---|---|---|
| **Standard Client** | `harshaair750@gmail.com` | `password123` | `/dashboard.php` | Test transactions, budgets, tax, goals, booking |
| **Verified CA / Expert** | `vs0021634@gmail.com` | `password123` | `/professional/dashboard.php` | Test appointment queue, acceptance, review feed |
| **Platform Administrator**| `admin@example.com` | `admin123` | `/admin/dashboard.php` | Test CA verification, user governance, audit reports |
| **New Client Registration** | Create in **TC-01** | `Fincart@2026!` | `/dashboard.php` | Test fresh user onboarding and initial states |

---

## 2. Test Execution Matrix Summary

| Test ID | Feature Under Test | Location / URL | Role | Expected Result Summary |
|---|---|---|---|---|
| **TC-01** | Client Registration & Strength Meter | `/login.php?tab=signup` | Guest | Strength bar turns green; user created & logged in |
| **TC-02** | Role-Based Access Control (RBAC) | `/login.php` | All | Correct redirection to user, CA, or admin portal |
| **TC-03** | Turbo Drive 8 SPA Navigation | Navigation Bar | Client | Instant transitions, zero screen flash, gold top bar |
| **TC-04** | Executive Financial Dashboard | `/dashboard.php` | Client | Real-time consolidated metrics & FY badge |
| **TC-05** | Add Transaction (Income/Expense) | `/add_transaction.php` | Client | Dynamic category switch; balance increases/decreases |
| **TC-06** | In-Place Transaction Edit & Delete | `/transactions.php` | Client | Pre-filled modal edits row; CSRF delete works |
| **TC-07** | Scoped Monthly Budget & Alert | `/budget.php` | Client | Over-budget red badge triggers when spent > limit |
| **TC-08** | Budget Rollover from Prev Month | `/budget.php` | Client | One-click button copies prior limits into new month |
| **TC-09** | Investments with Cash Outflow | `/investments.php` | Client | Deduct checkbox writes matching expense to ledger |
| **TC-10** | Asset Valuation Update & P&L | `/investments.php` | Client | Modal updates market price; green P&L recalculates |
| **TC-11** | Milestone Goals & Deposit Outflow | `/goals.php` | Client | Deposit updates goal % and deducts from balance |
| **TC-12** | Cashflow Analytics & Charts | `/analytics.php` | Client | 6-month bar chart and doughnut chart render cleanly |
| **TC-13** | Tax Planning (Old vs New Regime) | `/tax_planning.php` | Client | Sec 87A rebate applies; gives saving recommendation |
| **TC-14** | Tax Deductions (80C / 80D Caps) | `/tax_tracker.php` | Client | Section 80C caps at ₹1.5L; charts category totals |
| **TC-15** | ITR Preparation & Print Sheet | `/tax_summary.php` | Client | Clean `@media print` layout without nav clutter |
| **TC-16** | CA Directory & Appointment Booking | `/professionals.php` | Client | Conflict detection works; booking confirmation shown |
| **TC-17** | CA Portal — Queue Management | `/professional/dashboard.php` | Expert | CA accepts appointment; status updates to Confirmed |
| **TC-18** | Client Star Rating & Review | `/my_appointments.php` | Client | 1–5 star review updates CA's average rating |
| **TC-19** | Admin Portal — CA Verification | `/admin/requests.php` | Admin | Approve upgrades user to CA and lists in directory |
| **TC-20** | Automated Test Regression Suite | Terminal (`npm test`) | CLI | All 25 Vitest automated test cases pass |

---

## 3. Detailed Step-by-Step Test Scripts

---

### TC-01: Client Registration & Password Strength Meter

* **Where to Check**: `/login.php?tab=signup`
* **Role**: Guest / Unauthenticated User
* **Input Data**:
  - **Weak Password Test**: `1234`
  - **Full Name**: `Tester Alpha`
  - **Email**: `tester_alpha_2026@test.com` *(use unique timestamp if needed)*
  - **Password**: `Fincart@2026!`
  - **Confirm Password**: `Fincart@2026!`
* **Step-by-Step Actions**:
  1. Open `/login.php` and click the **Create Account** tab.
  2. In the password field, type `1234`. Observe the strength indicator.
  3. Clear it and type `Fincart@2026!`. Click the eye icon (`👁️`) on both fields.
  4. Fill in the full name and email address above.
  5. Click **Create My Account**.
* **Expected Output**:
  - Step 2: Strength bar is **Red** with status `"Weak"`.
  - Step 3: Strength bar turns **Green** with status `"Strong"`. Eye icon reveals plain text `Fincart@2026!`, clicking again hides it (`🙈`).
  - Step 5: Account is created, user session is initialized, and browser is redirected directly to `/dashboard.php` with welcome message.

---

### TC-02: Role-Based Authentication & Portal Routing

* **Where to Check**: `/login.php`
* **Role**: All User Roles
* **Input Data**:
  - Client: `harshaair750@gmail.com` / `password123`
  - Expert: `vs0021634@gmail.com` / `password123`
  - Admin: `admin@example.com` / `admin123`
* **Step-by-Step Actions**:
  1. Open `/login.php`.
  2. Log in with Client credentials.
  3. Log out via navigation bar.
  4. Open `/login.php`, toggle to the **CA / Expert Portal** pill, and log in with Expert credentials.
  5. Log out.
  6. Log in with Admin credentials.
* **Expected Output**:
  - Client login lands on `/dashboard.php`.
  - Expert login lands on `/professional/dashboard.php`.
  - Admin login lands on `/admin/dashboard.php`.
  - Each portal displays appropriate role badges and dedicated action buttons.

---

### TC-03: Instant SPA Transitions via Turbo Drive 8

* **Where to Check**: Any authenticated screen (e.g., `/dashboard.php`)
* **Role**: Client
* **Input Data**: None (Click navigation items)
* **Step-by-Step Actions**:
  1. From `/dashboard.php`, click **Transactions** in the top navigation bar.
  2. Observe the browser viewport, screen flash, and top progress bar.
  3. Click **Budgets → Investments → Goals → Analytics → Tax Planning**.
  4. Shrink browser window width to mobile view (< 768px).
  5. Tap the mobile hamburger toggle (`☰`), then tap **Budgets**.
* **Expected Output**:
  - Page transitions **instantly without full document reload** (zero white blank flicker).
  - A subtle luxury gold progress bar slides across the very top edge of the browser.
  - In mobile view: hamburger toggle smoothly opens the side drawer; clicking any link closes the drawer automatically and renders the target page.

---

### TC-04: Executive Financial Dashboard Aggregations

* **Where to Check**: `/dashboard.php`
* **Role**: Client
* **Input Data**: None
* **Step-by-Step Actions**:
  1. Navigate to `/dashboard.php`.
  2. Inspect the 4 executive metric cards at the top.
  3. Inspect the Indian Financial Year banner.
  4. Inspect the Quick Navigation Hub.
* **Expected Output**:
  - Displays: **Net Cash Balance**, **Monthly Inflow**, **Monthly Outflow**, and **Savings Efficiency (%)**.
  - All metrics match the exact sum of transactions recorded for the logged-in user.
  - Active Indian Financial Year badge is clearly shown (e.g., `FY 2026-27 (Apr 1 - Mar 31)`).
  - Quick action links point to Tax Optimization, Milestone Goals, and Expert Network.

---

### TC-05: Add Income & Expense Transactions (Dynamic Categories)

* **Where to Check**: `/add_transaction.php`
* **Role**: Client
* **Input Data**:
  - **Transaction 1 (Income)**: Type = Income, Category = `Salary`, Amount = `85000`, Date = Today, Description = `Tech Consulting Salary`
  - **Transaction 2 (Expense)**: Type = Expense, Category = `Food`, Amount = `4500`, Date = Today, Description = `Monthly Grocery`
* **Step-by-Step Actions**:
  1. Go to `/add_transaction.php`.
  2. Click the **Income** button. Inspect the category dropdown.
  3. Fill in Transaction 1 details and click **Save Transaction**.
  4. Return to `/add_transaction.php` and click the **Expense** button. Inspect category dropdown.
  5. Fill in Transaction 2 details and click **Save Transaction**.
* **Expected Output**:
  - Clicking **Income** turns the button green and populates: *Salary, Business, Investment, Freelance, Other*.
  - Clicking **Expense** turns the button red and populates: *Food, Transportation, Utilities, Shopping, Healthcare, Entertainment, Education, Other*.
  - Both transactions appear at the top of `/transactions.php`. Net balance increases by ₹85,000, then decreases by ₹4,500.

---

### TC-06: In-Place Transaction Editing & Deletion

* **Where to Check**: `/transactions.php`
* **Role**: Client
* **Input Data**:
  - **Edited Amount**: Change `4500` to `5000`
  - **Edited Description**: `Monthly Grocery + Supplements`
* **Step-by-Step Actions**:
  1. On `/transactions.php`, locate the `Food` transaction created in TC-05.
  2. Click the **Edit** button on that row.
  3. In the modal, change Amount to `5000` and Description to the text above.
  4. Click **Save Changes**.
  5. Locate any test transaction and click **Delete** (confirm the browser prompt).
* **Expected Output**:
  - Edit modal opens smoothly in-place without page reload, pre-filled with existing values.
  - After saving, transaction row immediately reflects ₹5,000.
  - After deleting, row is removed and total balance recalculates accordingly.

---

### TC-07: Scoped Monthly Budget & Over-Budget Warning

* **Where to Check**: `/budget.php`
* **Role**: Client
* **Input Data**:
  - **Category**: `Food`
  - **Monthly Limit**: `3000`
  - **Over-Budget Trigger Expense**: Add an expense of `3500` under `Food` via `/add_transaction.php`.
* **Step-by-Step Actions**:
  1. Navigate to `/budget.php`.
  2. In the "Set Category Budget" form, select Category = `Food` and Limit = `3000`.
  3. Click **Set Limit**.
  4. Navigate to `/add_transaction.php` and add an Expense: Category = `Food`, Amount = `3500`.
  5. Return to `/budget.php`.
* **Expected Output**:
  - `Food` budget card displays **₹3,500 of ₹3,000 spent (116.7%)**.
  - Visual progress bar turns **Red**.
  - A red **"OVER BUDGET"** badge is displayed next to the category.
  - The Chart.js allocation pie chart updates with the category limit.

---

### TC-08: One-Click Budget Limit Copy from Previous Month

* **Where to Check**: `/budget.php`
* **Role**: Client
* **Input Data**: None (Navigate calendar months)
* **Step-by-Step Actions**:
  1. On `/budget.php`, verify at least one budget limit exists for the current month.
  2. Click the right arrow (`&rarr;`) on the month navigation bar to advance to next month.
  3. Locate the **"📋 Copy Limits from Previous Month"** button.
  4. Click the button.
* **Expected Output**:
  - Next month's budget list instantly inherits the exact category limits from the previous month.
  - All spent amounts for the new month start at ₹0 (0%).

---

### TC-09: Asset Portfolio & Double-Entry Cash Outflow

* **Where to Check**: `/investments.php`
* **Role**: Client
* **Input Data**:
  - **Asset Name**: `HDFC Balanced Advantage Fund`
  - **Asset Type**: `Mutual Funds`
  - **Purchase Price**: `25000`
  - **Current Valuation**: `28000`
  - **Deduct Checkbox**: `[✓] Deduct from cash balance` (Ensure CHECKED)
* **Step-by-Step Actions**:
  1. Note your current **Net Cash Balance** on `/dashboard.php`.
  2. Open `/investments.php`.
  3. Fill in the asset fields above, keeping the cash deduction checkbox checked.
  4. Click **Add Asset to Portfolio**.
  5. Navigate to `/transactions.php`.
* **Expected Output**:
  - Asset appears in portfolio with **Unrealized Gain: +₹3,000 (+12.00%)** in green.
  - Chart.js doughnut chart renders portfolio asset allocation.
  - On `/transactions.php`, a new Expense transaction of **₹25,000** has been automatically written: Category = `Investment`, Description = *"Asset Acquisition: HDFC Balanced Advantage Fund"*.
  - Net Cash Balance has decreased by exactly ₹25,000.

---

### TC-10: In-Place Asset Valuation Update & P&L Calculation

* **Where to Check**: `/investments.php`
* **Role**: Client
* **Input Data**:
  - **New Market Valuation**: `32000`
* **Step-by-Step Actions**:
  1. On `/investments.php`, locate the `HDFC Balanced Advantage Fund` asset.
  2. Click the **Update Value** button on the card.
  3. In the modal, change the valuation to `32000`.
  4. Click **Update Valuation**.
* **Expected Output**:
  - Modal opens in-place pre-populated with current value.
  - After saving, asset updates immediately to **Current Value: ₹32,000** and **Unrealized Gain: +₹7,000 (+28.00%)**.

---

### TC-11: Milestone Goals & Atomic Deposit Deduction

* **Where to Check**: `/goals.php`
* **Role**: Client
* **Input Data**:
  - **Goal Title**: `New Car Downpayment`
  - **Target Amount**: `200000`
  - **Target Date**: `2027-12-31`
  - **Initial Deposit**: `20000`
  - **Subsequent Deposit**: `30000` with `[✓] Deduct from cash balance`
* **Step-by-Step Actions**:
  1. Go to `/goals.php`.
  2. In "Establish New Milestone Goal", enter Title, Target Amount, Date, and Initial Deposit = `20000`. Click **Establish Goal**.
  3. On the created goal card, click **+ Deposit**.
  4. Enter `30000`, ensure `[✓] Deduct from cash balance` is checked, and click **Credit Deposit**.
  5. Navigate to `/transactions.php`.
* **Expected Output**:
  - Goal progress bar advances to **₹50,000 of ₹200,000 (25%)**.
  - On `/transactions.php`, an automatic Expense of **₹30,000** appears under `Savings Transfer`.
  - Cash balance decreases by ₹30,000.

---

### TC-12: Cashflow Trends & Category Analytics (Chart.js)

* **Where to Check**: `/analytics.php`
* **Role**: Client
* **Input Data**: None (View charts and toggle month filter)
* **Step-by-Step Actions**:
  1. Navigate to `/analytics.php`.
  2. Inspect the **6-Month Cashflow Trend** bar chart.
  3. Inspect the **Expense Category Breakdown** doughnut chart.
  4. Select a different month in the "Select Analysis Month" dropdown.
* **Expected Output**:
  - The bar chart cleanly displays green inflow bars and red outflow bars.
  - The doughnut chart displays proportional slices for expenses.
  - Hovering over chart elements shows tooltips with exact rupee values.
  - No JavaScript canvas collision errors appear in DevTools Console.

---

### TC-13: Statutory Indian Tax Planning (Regime Comparison & Sec 87A)

* **Where to Check**: `/tax_planning.php`
* **Role**: Client
* **Input Data**:
  - **Scenario A (Income ≤ ₹7.5L Salaried)**:
    - Gross Salary = `750000`
  - **Scenario B (High Earner with 80C/80D Deductions)**:
    - Gross Salary = `1500000`
    - Section 80C = `150000`
    - Section 80D = `50000`
    - NPS 80CCD(1B) = `50000`
* **Step-by-Step Actions**:
  1. Open `/tax_planning.php`.
  2. For Scenario A: Enter `750000` in Annual Salary and click **Calculate Tax Comparison**.
  3. For Scenario B: Enter `1500000` and fill in the 80C, 80D, and NPS fields above. Click calculate.
* **Expected Output**:
  - **Scenario A**:
    - Taxable income = ₹7,50,000 - ₹75,000 (Std Deduction) = ₹6,75,000.
    - **New Tax Regime Net Tax = ₹0** (Full ₹25,000 Section 87A rebate applied).
    - Recommendation banner states: *"New Regime saves you ₹... Choose New Regime."*
  - **Scenario B**:
    - Old Regime applies all ₹2,50,000 in Chapter VI-A deductions.
    - New Regime applies standard deduction and uses statutory slab rates.
    - Summary highlights the optimal regime and exact rupee savings.

---

### TC-14: Tax Deductions Tracker (Section 80C & 80D Caps)

* **Where to Check**: `/tax_tracker.php`
* **Role**: Client
* **Input Data**:
  - **Record 1 (80C)**: Category = `80C - PPF`, Amount = `180000`, Date = Today, Description = `PPF Annual Contribution`
  - **Record 2 (80D)**: Category = `80D - Health Insurance (Self & Family)`, Amount = `25000`, Date = Today, Description = `Star Health Premium`
* **Step-by-Step Actions**:
  1. Navigate to `/tax_tracker.php`.
  2. Select active Financial Year (e.g., `FY 2026-27`).
  3. Add Record 1 and click **Save Deduction**.
  4. Add Record 2 and click **Save Deduction**.
  5. Inspect the statutory summary cards.
* **Expected Output**:
  - Even though ₹1,80,000 was entered for Section 80C, the summary card enforces the **Statutory Ceiling of ₹1,50,000** (₹30,000 excess is flagged as non-deductible).
  - Section 80D reflects ₹25,000.
  - Deductions doughnut chart displays the category proportions.

---

### TC-15: ITR Preparation Worksheet & Print Layout

* **Where to Check**: `/tax_summary.php` and `/itr_preparation.php`
* **Role**: Client
* **Input Data**: None
* **Step-by-Step Actions**:
  1. Navigate to `/tax_summary.php`.
  2. Inspect the computed taxable income and deductions summary.
  3. Press `Ctrl + P` (or `Cmd + P` on Mac) to open Print Preview.
* **Expected Output**:
  - Page renders a structured ITR computation statement.
  - In Print Preview: Navigation bars, buttons, and footers are **hidden** (`@media print`); output is a clean, archival tax summary document.

---

### TC-16: CA Directory & 1-on-1 Consultation Booking

* **Where to Check**: `/professionals.php` → `/booking.php`
* **Role**: Client
* **Input Data**:
  - Advisor: Select *CA Vinit Singh* (or any listed CA)
  - Date: Select tomorrow's date
  - Time Slot: `10:00:00`
  - Notes: `Advisory regarding Old vs New Regime optimization for FY 2026-27.`
* **Step-by-Step Actions**:
  1. Navigate to `/professionals.php`.
  2. Locate an active CA and click **Book Consultation**.
  3. On `/booking.php`, select date, time slot, and enter consultation notes.
  4. Click **Confirm Booking**.
  5. Try booking the exact same CA, date, and time slot again.
* **Expected Output**:
  - First booking: Redirects to `/booking_confirmation.php` with green checkmark and appointment summary.
  - Second booking attempt on identical slot: Shows collision warning: *"This time slot is already booked. Please select another."*

---

### TC-17: CA Portal — Consultation Queue Management

* **Where to Check**: `/professional/dashboard.php`
* **Role**: Verified CA / Expert (`vs0021634@gmail.com` / `password123`)
* **Input Data**: None
* **Step-by-Step Actions**:
  1. Log out of client account and log in with CA credentials above.
  2. On `/professional/dashboard.php`, locate the appointment booked in TC-16 under "Appointment Requests".
  3. Click **Accept**.
* **Expected Output**:
  - Appointment status changes immediately from `Pending` to **Confirmed**.
  - A green success notice appears confirming schedule update.

---

### TC-18: Client Star Rating & Feedback Submission

* **Where to Check**: `/my_appointments.php`
* **Role**: Client (`harshaair750@gmail.com` / `password123`)
* **Input Data**:
  - Rating: `5 Stars` (`★★★★★`)
  - Feedback: `Exceptional advice on capital gains tax and 80C planning. Saved me ₹35,000!`
* **Step-by-Step Actions**:
  1. Log in as Client and navigate to `/my_appointments.php`.
  2. Locate the confirmed appointment.
  3. Click **Rate & Review**.
  4. Select `5 Stars`, enter feedback text above, and click **Submit Rating**.
  5. Check the CA's card on `/professionals.php`.
* **Expected Output**:
  - Modal submits review successfully without full reload.
  - CA's average star rating and review count increment on `/professionals.php`.

---

### TC-19: Admin Portal — CA Verification & Governance

* **Where to Check**: `/admin/dashboard.php` → `/admin/requests.php`
* **Role**: Platform Administrator (`admin@example.com` / `admin123`)
* **Input Data**: None
* **Step-by-Step Actions**:
  1. Log in as Administrator.
  2. On `/admin/dashboard.php`, verify platform-wide KPIs (Users, Professionals, Transactions, Appointments).
  3. Click **Pending Requests** (`/admin/requests.php`).
  4. If a pending application is present, inspect credentials and click **Approve**.
  5. Navigate to `/admin/users.php` and search for any client email.
* **Expected Output**:
  - Admin dashboard displays accurate counts.
  - Approving an application promotes user role to `professional` and publishes profile to public directory.
  - `/admin/users.php` lists all users with role-editing capability.

---

### TC-20: Automated CI/CD Regression Test Suite

* **Where to Check**: Terminal / Command Line in project root
* **Role**: Developer / Automation QA
* **Command to Run**:
  ```powershell
  npm test
  ```
* **Expected Output**:
  ```
   RUN  v1.6.1 C:/Users/Harsh/Desktop/fincart

   ✓ tests/tax.test.js               (9 tests)
   ✓ tests/budget.test.js            (2 tests)
   ✓ tests/ratings.test.js           (4 tests)
   ✓ tests/auth_and_security.test.js (7 tests)
   ✓ tests/deductions.test.js        (3 tests)

   Test Files  5 passed (5)
        Tests  25 passed (25)
     Duration  < 1s
  ```
  - **All 25 unit test cases pass with 0 failures**.
