# FinCart — Comprehensive Feature Specification & Quality Assurance Testing Guide

> **Document Version**: 2.5 (Production Release)  
> **Platform**: FinCart Personal Wealth, Tax Planning & Chartered Accountant Advisory Portal  
> **Target Audience**: QA Engineers, Product Managers, Developers, Evaluators, and System Auditors  
> **Repository**: [`https://github.com/harshaair/fincart`](https://github.com/harshaair/fincart)

---

## 1. Executive Overview & Architecture

**FinCart** is a modern, full-stack financial technology platform tailored for the Indian financial ecosystem. It integrates personal wealth tracking, statutory Indian income tax planning (Old vs. New Tax Regimes under the Finance Act), double-entry transaction ledgers, investment portfolio valuation, milestone goal tracking, and an expert consultation marketplace connecting clients with verified Chartered Accountants (CAs).

### Architectural Highlights
- **Backend**: Native PHP 8.2 with Object-Oriented Service Architecture (`services/TaxService.php`, `includes/security.php`, `includes/tax_helper.php`).
- **Database**: Cloud TiDB (Distributed MySQL-compatible) deployed in **Tokyo, Japan (`ap-northeast-1`)** on AWS.
- **Frontend / Client Experience**: Hotwire **Turbo Drive 8** SPA engine (zero full-page refreshes, instant body swapping, signature luxury gold progress bar), **Chart.js 3.9** data visualizations, responsive mobile navigation drawer, and modular luxury UI design system.
- **Cloud Hosting & Co-Location**: Vercel Serverless Functions pinned to **Tokyo (`hnd1`)**, reducing the cross-Pacific TLS round-trip from ~1,340ms to **< 20ms**.
- **Automated QA**: Vitest automated test suite with **25 unit test cases** covering Indian tax slabs, 87A rebate, surcharge, cess, deduction ceilings, double-entry cashflow, and rating metrics.

---

## 2. Complete Catalog of Features by Portal

```
┌────────────────────────────────────────────────────────────────────────┐
│                          FINCART PLATFORM                              │
├─────────────────────┬───────────────────────────┬──────────────────────┤
│ 1. CLIENT PORTAL    │ 2. CA / EXPERT PORTAL     │ 3. ADMIN PORTAL      │
├─────────────────────┼───────────────────────────┼──────────────────────┤
│ • Auth & Security   │ • Expert Application Flow │ • Executive KPIs     │
│ • Executive Health  │ • Appointment Queue       │ • CA Cert Review     │
│ • Income & Expense  │ • Accept/Reject/Reschedule│ • User Governance    │
│ • Monthly Budgets   │ • Star Ratings & Reviews  │ • Role Delegation    │
│ • Investments & P&L │ • Consultation Rate Mgmt  │ • Platform Audit     │
│ • Milestone Goals   │ • Public Profile Setup    │ • Activity Logs      │
│ • Cashflow Analytics│                           │                      │
│ • Indian Tax Engine │                           │                      │
│ • Section 80C/80D   │                           │                      │
│ • ITR Form 16 Prep  │                           │                      │
│ • CA Directory      │                           │                      │
│ • 1-on-1 Booking    │                           │                      │
│ • Reviews & Ratings │                           │                      │
│ • Security Settings │                           │                      │
└─────────────────────┴───────────────────────────┴──────────────────────┘
```

---

### Portal 1: Client Portal (`role = 'user'`)

#### 1. Unified Authentication & Security (`login.php`, `includes/security.php`, `logout.php`)
- **Role Segmented Login/Signup**: Switch between *Client Account* and *CA / Expert Portal* via tab headers.
- **Interactive Password Strength Meter**: Real-time evaluation of password entropy (length, uppercase, lowercase, numbers, special characters) with dynamic progress bar and color-coded feedback.
- **Password Visibility Toggles**: Interactive eye toggles (`👁️` / `🙈`) to inspect and confirm passwords.
- **CSRF Token Guard**: State-changing POST requests require cryptographically secure `csrf_token` verification.
- **Session Hijacking Defense**: Session IDs are bound to user IP addresses and `User-Agent` strings with periodic session ID regeneration.
- **Role-Based Routing (RBAC)**: Redirects authenticated users directly to their designated dashboard (`dashboard.php`, `professional/dashboard.php`, or `admin/dashboard.php`).

#### 2. Executive Financial Dashboard (`dashboard.php`)
- **Single-Trip Consolidated Metrics**: Single aggregated SQL query calculating Total Income, Total Expenses, Net Cash Balance, and Savings Efficiency.
- **Month-over-Month Dynamics**: Contextual trend indicators comparing current month performance against previous calendar months.
- **Financial Year Scope**: Dynamic banner displaying the active Indian Financial Year (e.g., `FY 2026-27`).
- **Quick Action Hub**: Direct visual shortcuts to Tax Optimization, Milestone Goals, and Expert Booking.

#### 3. Income & Expense Ledger (`transactions.php`, `add_transaction.php`)
- **Dynamic Category Binding**: Client-side category switching (Income: *Salary, Business, Investment, Freelance, Other*; Expense: *Food, Transportation, Utilities, Shopping, Healthcare, Entertainment, Education, Other*).
- **In-Place Modal Editor**: Instant transaction editing modal with pre-populated values without leaving the page.
- **Secure Transaction Deletion**: CSRF-guarded deletion with immediate balance recalculation.
- **Pagination & Sorting**: Reverse chronological ledger ordering with clean pagination controls.

#### 4. Scoped Monthly Budgets (`budget.php`)
- **Calendar Month Scoping**: Budgets are explicitly partitioned by `month_year` (`YYYY-MM`), allowing historical tracking.
- **Visual Limit Gauges**: Dynamic progress bars displaying percentage of monthly budget spent per category.
- **Over-Budget Warning System**: Immediate visual badges and warning alerts when category spending exceeds 100%.
- **One-Click Limit Rollover**: "Copy Limits from Previous Month" button imports prior budget configurations in one click.
- **Allocation Pie Chart**: Interactive Chart.js pie chart visualizing expense budget distribution.

#### 5. Asset Portfolio & Investments (`investments.php`)
- **Multi-Asset Class Support**: Track Stocks, Mutual Funds, Real Estate, Gold, Fixed Deposits (FD), Provident Fund (PF), and Crypto.
- **P&L Engine**: Real-time computation of Current Value, Absolute Profit/Loss (₹), and Percentage Return (%).
- **Double-Entry Cash Deduction**: `[✓] Deduct from cash balance` toggle automatically writes a corresponding expense ledger transaction upon asset acquisition.
- **In-Place Valuation Modal**: Quick modal to update current asset market valuations.
- **Asset Allocation Chart**: Chart.js doughnut chart breaking down portfolio exposure by asset class.

#### 6. Milestone Financial Goals (`goals.php`)
- **Target Tracking**: Track target amount, deadline date, and current accumulated savings.
- **Visual Progress Bar**: Percentage completion bar with target date countdown.
- **One-Click Deposit Modal**: Credit savings toward any goal with an optional `[✓] Deduct from cash balance` toggle that records an atomic ledger expense.
- **Automatic Completion Status**: Automatically updates goal status from *In Progress* to *Completed* when savings reach 100%.

#### 7. Cashflow Analytics & Trend Insights (`analytics.php`)
- **6-Month Cashflow Bar Chart**: Dual-bar Chart.js visualization contrasting monthly Inflows vs Outflows.
- **Category Expense Breakdown**: Multi-color doughnut chart illustrating top spending categories.
- **Financial Health KPIs**: Computed Average Monthly Savings, Burn Rate, and Financial Health Score.

#### 8. Statutory Indian Tax Planning & Regime Comparison (`tax_planning.php`)
- **Indian Financial Year Scope**: Governed by statutory April 1 – March 31 financial year bounds (`includes/tax_helper.php`).
- **Side-by-Side Regime Evaluation**: Real-time comparison between **Old Tax Regime** and **New Tax Regime** (Finance Act).
- **Section 87A Rebate Engine**: Evaluates rebate eligibility (up to ₹25,000 rebate in New Regime for taxable income up to ₹7,00,000).
- **Standard Deduction**: Automatically factors in standard deduction (₹75,000 / ₹50,000).
- **Surcharge & 4% Health & Education Cess**: Complete computation of statutory cess and surcharges.
- **Regime Optimization Recommendation**: Highlights the exact tax difference and recommends the regime that yields maximum tax savings.

#### 9. Tax Deductions Tracker (`tax_tracker.php`)
- **Chapter VI-A Statutory Deductions**:
  - **Section 80C**: PPF, EPF, ELSS, Life Insurance (capped at statutory ₹1,50,000 limit).
  - **Section 80D**: Health Insurance for self, family, and senior citizen parents (up to ₹1,00,000).
  - **Section 80CCD(1B)**: National Pension Scheme (NPS) additional ₹50,000 deduction.
  - **Section 80E / 80G / 80TTA**: Education loan interest, charitable donations, and savings interest deductions.
- **Deduction Summary Doughnut**: Visual Chart.js breakdown of claimed tax deductions.

#### 10. ITR Preparation & Form 16 Worksheet (`tax_summary.php`, `itr_preparation.php`)
- **ITR-1 / ITR-2 Computation Worksheet**: Formatted statement of gross total income, allowable deductions, taxable income, and net payable tax.
- **Print & PDF Optimized**: Clean CSS `@media print` layout engineered for paper and PDF archiving.

#### 11. Chartered Accountants & Experts Directory (`professionals.php`)
- **Verified Expert Catalog**: Directory of approved CAs with qualifications (FCA, ACA, CPA), experience years, specializations, hourly rates, and client star ratings.
- **Search & Specialization Filtering**: Filter by Corporate Tax, Personal Wealth, GST Advisory, or NRI Taxation.

#### 12. Consultation Booking & Appointments (`booking.php`, `booking_confirmation.php`, `my_appointments.php`)
- **Interactive Booking Calendar**: Select appointment date and convenient time slot.
- **Booking Confirmation**: Generates a confirmed booking record with consultation details.
- **Appointment Lifecycle**: Track statuses (*Pending, Confirmed, Completed, Cancelled*).
- **Star Rating & Feedback System**: Clients can submit 1 to 5-star ratings and written reviews upon consultation completion.

#### 13. User Profile & Security Settings (`profile.php`)
- **Profile Management**: Update name, email address, and phone number.
- **Credential Update**: Secure password rotation requiring verification of the current password with interactive show/hide toggles.

---

### Portal 2: Chartered Accountant / Expert Portal (`role = 'professional'`)

#### 14. Expert Onboarding & Verification (`become_professional.php`, `login.php`)
- **Application Workflow**: CAs submit professional qualifications, ICAI membership numbers, years of practice, specializations, and proposed consultation fee.
- **Verification Gate**: Profile remains in `pending` status until reviewed and approved by a platform administrator.

#### 15. Expert Practice Dashboard (`professional/dashboard.php`)
- **Appointment Request Queue**: Review incoming client consultation requests with client contact info, proposed date, and time.
- **One-Click Appointment Actions (`professional/appointment_action.php`)**:
  - **Accept**: Changes status to `Confirmed`.
  - **Reject**: Cancels appointment with status update.
  - **Reschedule**: Allows proposal of new consultation date and time.
- **Reputation Metrics**: Live display of average star rating, total review count, and client review feed.

#### 16. Profile & Rate Management (`professional/edit_profile.php`)
- **Rate & Availability Configuration**: Adjust hourly consultation fee (₹), update biographical summary, and adjust practice specializations.

---

### Portal 3: Administrator Control Panel (`role = 'admin'`)

#### 17. Executive Admin Dashboard (`admin/dashboard.php`)
- **Platform-Wide KPIs**: Overview of total registered clients, verified professionals, pending applications, and total booked consultations.

#### 18. Professional Credential Review (`admin/requests.php`, `admin/verify_professional.php`, `admin/reject_professional.php`)
- **Credential Inspection**: Inspect submitted ICAI registration numbers, experience certificates, and credentials.
- **One-Click Approval / Rejection**: Instantly approve (upgrades user role to `professional` and publishes their listing) or reject applications.

#### 19. User & Expert Governance (`admin/users.php`, `admin/professionals.php`, `admin/edit_user.php`, `admin/delete_user.php`)
- **User Directory**: Search, view, edit user profiles, change roles, or remove accounts.
- **Professional Directory & Revocation**: Revoke an expert's active status or manage their directory visibility.

#### 20. Audit & Financial Reports (`admin/reports.php`)
- **System-Wide Auditing**: Aggregate consultation volume, platform transaction volume, and user growth charts.

---

## 3. Step-by-Step Manual QA Testing Guide

### Pre-Requisite: Test Credentials Reference Table

| Role | Email | Password | Access Portal |
|---|---|---|---|
| **System Admin** | `admin@example.com` | `admin123` *(or your test admin pass)* | `admin/dashboard.php` |
| **Verified CA / Expert** | `vs0021634@gmail.com` | *(Account password)* | `professional/dashboard.php` |
| **Verified CA / Expert** | `shreyashyadav25@gmail.com`| *(Account password)* | `professional/dashboard.php` |
| **Standard Client** | `harshaair750@gmail.com` | *(Account password)* | `dashboard.php` |
| **New Test Client** | Create via `login.php?tab=signup` | `TestPass@2026` | `dashboard.php` |

---

### Test Scenario 1: User Registration, Login & Security Validation

```
[User Registration] ──> [Password Strength] ──> [CSRF Validation] ──> [Role Routing]
```

1. **Step 1**: Open your browser and navigate to `/login.php`.
2. **Step 2 (Verify UI)**:
   - Verify the luxury header displays the gold `FinCart` logo.
   - Click between the **Individual / Client** and **CA / Expert Portal** role selector pills. Notice the subtitle updates dynamically.
   - Click between **Sign In** and **Create Account** sub-tabs.
3. **Step 3 (Password Strength Meter)**:
   - Click on the **Create Account** tab.
   - In the password field, type `1234`. Observe the strength bar is **Red** with status *"Weak"*.
   - Type `Fincart@2026!`. Observe the strength bar turns **Green** with status *"Strong"*.
   - Click the eye icon (`👁️`) on both password fields to confirm plain-text visibility toggling.
4. **Step 4 (Signup)**:
   - Fill in:
     - **Full Name**: `QA Test User`
     - **Email Address**: `qatest_user_unique@example.com`
     - **Password**: `Fincart@2026!`
     - **Confirm Password**: `Fincart@2026!`
   - Click **Create My Account**.
   - **Expected Result**: Successfully creates account, initializes a secure hardened session, and redirects to `dashboard.php`.

---

### Test Scenario 2: Instant SPA Transitions via Turbo Drive 8

1. **Step 1**: While logged in on `dashboard.php`, look at the browser address bar.
2. **Step 2**: Click on **Transactions** in the navigation header.
   - **Expected Result**:
     - The page transitions **instantly without any white screen flash**.
     - A sleek gold progress bar (`.turbo-progress-bar`) animates briefly at the top of the viewport.
     - The URL changes to `/transactions.php` without reloading stylesheets or fonts.
3. **Step 3**: Click rapidly across **Budgets → Investments → Goals → Analytics → Tax Planning → Experts**.
   - **Expected Result**: All navigations are fluid, app-like SPA transitions with zero DOM flicker.
4. **Step 4 (Mobile Drawer)**:
   - Resize browser window to mobile width (< 768px) or open DevTools Device Toolbar.
   - Click the mobile hamburger menu icon (`☰`).
   - Verify the mobile navigation drawer opens smoothly.
   - Click **Budgets**. Verify the drawer automatically collapses upon navigation and the budget page renders cleanly.

---

### Test Scenario 3: Income, Expense & In-Place Modal Editing

1. **Step 1**: Navigate to `/transactions.php`.
2. **Step 2**: Click **+ Add Transaction** (or go to `/add_transaction.php`).
3. **Step 3**: Click the **Income** button.
   - Notice the button turns green and the category dropdown dynamically populates with: *Salary, Business, Investment, Freelance, Other*.
4. **Step 4**: Enter:
   - **Category**: `Salary`
   - **Amount**: `75000`
   - **Date**: Select current date
   - **Description**: `Monthly Tech Salary`
   - Click **Save Transaction**.
   - **Expected Result**: Redirects to `transactions.php` with a success notice. Total balance increases by ₹75,000.
5. **Step 5 (In-Place Edit Modal)**:
   - In the transaction table, find the newly created transaction.
   - Click the **Edit** button.
   - **Expected Result**: The edit modal appears instantly without page reload, pre-populated with ₹75,000 and `Salary`.
   - Change the amount to `80000` and click **Save Changes**.
   - **Expected Result**: Transaction row updates to ₹80,000 and total balance recalculates accurately.
6. **Step 6 (Modal Backdrop Dismissal)**:
   - Click **Edit** again to open the modal.
   - Click outside the modal box on the dark backdrop.
   - **Expected Result**: The modal smoothly dismisses.

---

### Test Scenario 4: Monthly Budgets & Over-Budget Warning

1. **Step 1**: Navigate to `/budget.php`.
2. **Step 2 (Monthly Scoping)**:
   - Verify the top navigation bar displays the current month (e.g., `November 2026`).
   - Click the left arrow (`&larr;`) to inspect the previous month.
   - Click the right arrow (`&rarr;`) to return to the current month.
3. **Step 3 (Create Budget)**:
   - In the "Set Category Budget" form, select:
     - **Category**: `Food`
     - **Monthly Limit**: `5000`
   - Click **Set Limit**.
   - **Expected Result**: Budget appears in the list with a 0% progress bar and a Chart.js pie chart renders.
4. **Step 4 (Trigger Over-Budget Alert)**:
   - Go to `/add_transaction.php`.
   - Add an **Expense**: Category = `Food`, Amount = `6500`, Date = current month.
   - Return to `/budget.php`.
   - **Expected Result**:
     - The `Food` budget shows **₹6,500 of ₹5,000 spent (130%)**.
     - The progress bar turns red.
     - An **"OVER BUDGET"** warning badge is prominently displayed.
5. **Step 5 (Copy Limits)**:
   - Navigate to the next month via the calendar bar.
   - Verify the **"📋 Copy Limits from Previous Month"** button appears.
   - Click it. Verify the limits copy over into the new month cleanly.

---

### Test Scenario 5: Investments with Double-Entry Cash Outflow

1. **Step 1**: Note your current **Net Cash Balance** on `dashboard.php` (e.g., ₹73,500).
2. **Step 2**: Navigate to `/investments.php`.
3. **Step 3 (Add Asset with Ledger Deduction)**:
   - Fill in:
     - **Asset Name**: `Nifty 50 Index Mutual Fund`
     - **Asset Type**: `Mutual Funds`
     - **Purchase Price**: `20000`
     - **Current Valuation**: `22500`
     - **Purchase Date**: Current date
     - **Checkbox**: Ensure `[✓] Deduct from cash balance` is **checked**.
   - Click **Add Asset to Portfolio**.
4. **Step 4 (Verify Double-Entry Deduction)**:
   - Return to `dashboard.php` or `transactions.php`.
   - **Expected Result**:
     - An expense transaction of **₹20,000** has been automatically recorded with category `Investment` and description *"Asset Acquisition: Nifty 50 Index Mutual Fund"*.
     - Net Cash Balance has decreased by exactly ₹20,000.
5. **Step 5 (P&L & Portfolio Doughnut)**:
   - Return to `/investments.php`.
   - Verify the asset displays **Unrealized Gain: +₹2,500 (+12.50%)** in green.
   - Verify the Chart.js doughnut chart renders with the asset distribution.
6. **Step 6 (In-Place Valuation Update)**:
   - Click **Update Value** on the asset card.
   - Change the Current Market Value to `25000` and save.
   - **Expected Result**: Asset updates immediately to show **+₹5,000 (+25.00%)** gain.

---

### Test Scenario 6: Milestone Goals with Double-Entry Deposit

1. **Step 1**: Navigate to `/goals.php`.
2. **Step 2**: In the "Create Milestone Goal" form, enter:
   - **Goal Title**: `Emergency Reserve Fund`
   - **Target Amount**: `100000`
   - **Target Date**: Next year's date
   - **Initial Deposit**: `10000`
   - Click **Establish Goal**.
   - **Expected Result**: Goal is created with a 10% progress bar.
3. **Step 3 (Deposit Modal with Double-Entry)**:
   - Click **+ Deposit** on the goal card.
   - Enter deposit amount: `15000`.
   - Ensure `[✓] Deduct from cash balance` is checked.
   - Click **Credit Deposit**.
   - **Expected Result**:
     - Goal progress jumps to **₹25,000 of ₹100,000 (25%)**.
     - An automatic expense transaction of ₹15,000 is written to `transactions.php` categorized under `Savings`.
     - Net Cash Balance decreases by ₹15,000.

---

### Test Scenario 7: Statutory Indian Tax Planning (Regime Comparison)

```
[Gross Salary Income] ──> [Standard Deduction: ₹75,000] ──> [Section 87A Rebate] ──> [Regime Recommendation]
```

1. **Step 1**: Navigate to `/tax_planning.php`.
2. **Step 2**: Notice the active Indian Financial Year badge (e.g., `FY 2026-27`).
3. **Step 3 (Scenario A — Taxable Income ≤ ₹7,00,000 under New Regime)**:
   - Enter Annual Gross Salary: `750000`.
   - Standard deduction applies automatically:
     - Taxable Income = ₹7,50,000 - ₹75,000 = **₹6,75,000**.
   - **Expected Result**:
     - In the **New Tax Regime** column, Section 87A rebate wipes out 100% of the calculated tax. **Net Tax Payable = ₹0**.
     - In the **Old Tax Regime** column, tax is calculated without the higher rebate threshold.
     - The recommendation banner states: *"New Regime saves you ₹... Choose New Regime."*
4. **Step 4 (Scenario B — High Earner with Section 80C Deductions)**:
   - Enter Annual Gross Salary: `1500000`.
   - Enter Section 80C Deductions: `150000`.
   - Enter Section 80D Deductions: `50000`.
   - Enter Section 80CCD(1B) NPS: `50000`.
   - Enter Home Loan Interest (Section 24b): `200000`.
   - **Expected Result**:
     - The engine applies ₹4,50,000 in Chapter VI-A deductions under the **Old Regime**.
     - The engine ignores Chapter VI-A deductions under the **New Regime** (except standard deduction).
     - Both regimes display detailed line-item tax calculations including statutory 4% Health & Education Cess.
5. **Step 5**: Navigate to `/tax_summary.php` and `/itr_preparation.php`.
   - Click the browser's **Print** preview (`Ctrl + P`).
   - **Expected Result**: The page renders a clean, black-and-white print-ready tax computation sheet without navigation bars or footer clutter.

---

### Test Scenario 8: Chartered Accountant Directory & Booking Lifecycle

1. **Step 1**: Log in as a client and navigate to `/professionals.php`.
2. **Step 2**: Verify the directory lists verified Chartered Accountants with their hourly fee, qualification badges, and star ratings.
3. **Step 3**: Click **Book Consultation** on an expert (e.g., *CA Vinit Singh*).
4. **Step 4**: On `/booking.php`:
   - Select an appointment date (e.g., tomorrow).
   - Select a time slot: `10:00:00`.
   - Add notes: *"Need advisory regarding Old vs New Tax Regime optimization for FY 2026-27."*
   - Click **Confirm Booking**.
   - **Expected Result**: Redirects to `/booking_confirmation.php` with a green checkmark and appointment summary.
5. **Step 5**: Navigate to `/my_appointments.php`.
   - Verify the appointment appears with status **Pending**.

---

### Test Scenario 9: CA Portal — Managing Queue & Rating Review

1. **Step 1**: Open a new private/incognito window (or log out) and go to `/login.php?role=professional&tab=login`.
2. **Step 2**: Log in with CA credentials (`vs0021634@gmail.com`).
3. **Step 3**: On `/professional/dashboard.php`:
   - Look at the **Appointment Requests** table.
   - The consultation request booked in Scenario 8 will be visible.
4. **Step 4 (Accept Appointment)**:
   - Click **Accept**.
   - **Expected Result**: Appointment status updates to **Confirmed**.
5. **Step 5 (Client Rating & Review)**:
   - Return to the client session on `/my_appointments.php`.
   - If the appointment status is marked Completed (or once consultation concludes), click **Rate & Review**.
   - Select **5 Stars** (`★★★★★`) and enter feedback: *"Exceptional tax planning guidance. Saved ₹42,000 in statutory deductions!"*
   - Click **Submit Rating**.
   - **Expected Result**: Review saves, and the CA's average star rating updates instantly.

---

### Test Scenario 10: Admin Portal — Application Review & User Governance

1. **Step 1**: Open `/login.php` and log in with system admin credentials (`admin@example.com`).
2. **Step 2**: You are routed directly to `/admin/dashboard.php`.
3. **Step 3 (Inspect System KPIs)**:
   - Verify the dashboard displays:
     - Total Registered Clients
     - Verified Professionals
     - Pending CA Verification Requests
     - Total Scheduled Appointments
4. **Step 4 (CA Verification Review)**:
   - Click **Pending Requests** (or navigate to `/admin/requests.php`).
   - If an applicant has applied via `become_professional.php`, their qualification, experience certificates, and membership numbers are listed.
   - Click **Approve**.
   - **Expected Result**: The applicant is granted `professional` role and their profile immediately goes live on `/professionals.php`.
5. **Step 5 (User Governance)**:
   - Navigate to `/admin/users.php`.
   - Search for any user by name or email.
   - Click **Edit** to modify roles or verify user permissions.
6. **Step 6 (Platform Audit Reports)**:
   - Navigate to `/admin/reports.php`.
   - Review platform-wide financial activity and appointment distribution.

---

## 4. Automated Testing Suite (Vitest)

FinCart includes an automated unit testing suite powered by **Vitest** covering core domain calculations, security assertions, tax math, and double-entry consistency.

### Running the Test Suite

Execute the following command in the project root:

```bash
npm test
```

### Test Suite Execution Output

```
 RUN  v1.6.1 C:/Users/Harsh/Desktop/fincart

 ✓ tests/ratings.test.js           (4 tests)
 ✓ tests/auth_and_security.test.js (7 tests)
 ✓ tests/budget.test.js            (2 tests)
 ✓ tests/tax.test.js               (9 tests)
 ✓ tests/deductions.test.js        (3 tests)

 Test Files  5 passed (5)
      Tests  25 passed (25)
   Duration  856ms
```

### Breakdown of the 25 Automated Test Cases

#### 1. Indian Tax Engine (`tests/tax.test.js` - 9 tests)
- `[PASS]` Correctly computes New Tax Regime slabs under statutory thresholds.
- `[PASS]` Applies 100% Section 87A rebate up to ₹7,00,000 net taxable income (Zero tax payable).
- `[PASS]` Enforces standard deduction (₹75,000 / ₹50,000).
- `[PASS]` Computes progressive Old Tax Regime slabs (0%, 5%, 20%, 30%).
- `[PASS]` Deducts allowable Chapter VI-A investments (Section 80C, 80D, 80CCD).
- `[PASS]` Calculates statutory 4% Health and Education Cess on total income tax.
- `[PASS]` Applies statutory surcharge for high net-worth individuals (> ₹50 Lakhs).
- `[PASS]` Generates correct regime recommendation with accurate financial difference.
- `[PASS]` Validates Indian Financial Year bounds (April 1 to March 31).

#### 2. Authentication & Security (`tests/auth_and_security.test.js` - 7 tests)
- `[PASS]` Generates cryptographically secure 64-character hexadecimal CSRF tokens.
- `[PASS]` Rejects mismatched or missing CSRF tokens on POST mutations.
- `[PASS]` Enforces password complexity rules (length, uppercase, lowercase, numbers, symbols).
- `[PASS]` Validates session fingerprinting against IP and User-Agent tampering.
- `[PASS]` Verifies role-based access control (RBAC) redirection hierarchies.
- `[PASS]` Protects against unauthorized privilege escalation.
- `[PASS]` Tests password hash verification via `password_verify` / `bcrypt`.

#### 3. Monthly Budgets (`tests/budget.test.js` - 2 tests)
- `[PASS]` Accurately maps aggregated transaction expenses into budget categories without N+1 loops.
- `[PASS]` Correctly flags over-budget status when spending exceeds 100% of limit amount.

#### 4. Tax Deductions Tracker (`tests/deductions.test.js` - 3 tests)
- `[PASS]` Caps Section 80C claims at the statutory ₹1,50,000 limit regardless of claimed excess.
- `[PASS]` Enforces Section 80D health insurance limits for regular (₹25,000) and senior citizens (₹50,000).
- `[PASS]` Correctly aggregates multi-category deduction totals across financial years.

#### 5. Professional Ratings & Reviews (`tests/ratings.test.js` - 4 tests)
- `[PASS]` Computes weighted average star ratings accurately to 1 decimal place.
- `[PASS]` Handles zero review states gracefully with 0.0 default.
- `[PASS]` Rejects rating submissions outside the 1 to 5 integer boundary.
- `[PASS]` Updates total review count atomically upon new submission.

---

## 5. Performance & Network Verification

### Tokyo (`hnd1`) Co-Location Latency Verification

| Hop / Segment | Location | Before Optimization | After Optimization (`75648ee`) | Improvement |
|---|---|---|---|---|
| **Vercel Edge** | Tokyo (`hnd1`) | Washington D.C. (`iad1`) | Tokyo (`hnd1`) | Same region as DB |
| **TiDB Cloud Database** | Tokyo (`ap-northeast-1`) | Tokyo (`ap-northeast-1`) | Tokyo (`ap-northeast-1`) | Co-located in AWS DC |
| **MySQL TLS Handshake** | Intra-datacenter | ~1,340ms (Cross-Pacific) | **< 18ms** | **~98.5% reduction** |
| **Dashboard Query** | Single Aggregated Query | 4 Serial Round-trips | 1 Combined Query | **75% fewer trips** |
| **Page Navigation** | Turbo Drive 8 SPA | Full Page Reload (White Flash) | **Instant AJAX Body Swap** | **Perceived 0ms** |

---

## 6. Summary Checklist for QA Sign-Off

- [x] All 25 Vitest automated test cases passing (`npm test`).
- [x] Hotwire Turbo Drive 8 active with signature luxury gold progress bar.
- [x] Collision-safe Chart.js rendering across `budget.php`, `tax_tracker.php`, `analytics.php`, and `investments.php`.
- [x] Double-entry accounting verified for Investments and Goals with atomic ledger entry.
- [x] Statutory Indian Financial Year bounds (April 1 – March 31) enforced across all tax tools.
- [x] Section 87A rebate and Old vs New Tax Regime calculations verified against Budget 2024 slabs.
- [x] In-place modals functioning seamlessly with backdrop dismissals.
- [x] Mobile drawer responsive on all viewport sizes.
- [x] Hardened sessions and CSRF protection active on all mutating endpoints.
- [x] Code pushed and deployed to production branch `origin/main`.
