# FinCart - Personal Finance & Indian Tax Management Platform

[![FinCart CI Pipeline](https://github.com/harshaair/fincart/actions/workflows/test.yml/badge.svg)](https://github.com/harshaair/fincart/actions/workflows/test.yml)
[![Tests](https://img.shields.io/badge/Vitest-20%2F20%20passed-27ae60)](https://vitest.dev/)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%20InnoDB-00758f)](https://www.mysql.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> **FinCart** is a full-stack personal finance and Indian tax management web platform with role-based access control (User, Verified Professional, Admin), FY 2024–25 dual-regime tax optimization engine, transaction analytics, budget tracking, appointment scheduling, and client review systems.

---

## 📊 Codebase Metrics (KLOC)

FinCart contains over **21,200 lines of code and documentation** (~21.2 KLOC) across 89 files:

| Layer / Language | Files | Lines of Code | KLOC | Description |
|---|---|---|---|---|
| **PHP (Backend)** | 57 | 10,534 | **10.53 KLOC** | Core MVC endpoints, Auth, Tax Engine, Admin Portal |
| **HTML5 (Views)** | 4 | 3,283 | **3.28 KLOC** | Landing page, Services, About Us, Auth forms |
| **Markdown (Docs)** | 11 | 4,910 | **4.91 KLOC** | Technical specifications, Application maps, API guides |
| **MySQL (Database)**| 6 | 614 | **0.61 KLOC** | 14 normalized InnoDB tables, foreign keys, cascades |
| **CSS3 (Styles)** | 2 | 539 | **0.54 KLOC** | Design tokens, responsive grids, admin dashboard UI |
| **JavaScript / Tests**| 6 | 480 | **0.48 KLOC** | Vitest unit/integration test suites & Chart.js charts |
| **Total** | **89** | **21,238** | **21.24 KLOC** | **Production-grade web application** |

---

## 🔑 Recruiter Quick Demo Credentials

For quick evaluation without having to register new accounts, use these pre-seeded roles:

| Access Role | Demo Email | Demo Password | Core Capabilities to Inspect |
|---|---|---|---|
| **Regular User** | `harshaair750@gmail.com` | `password123` | Income/Expense CRUD, Paginated Transactions, Budgets, Goal tracking, Dual-Regime Tax Comparison, Expert Booking |
| **Verified Pro (CA)**| `vs0021634@gmail.com` | `password123` | Professional Dashboard, Accept/Reject Appointments, View Client Star Ratings and Reviews |
| **System Admin** | `admin@example.com` | `Admin@123` | Operations Console, User Management, Approve/Reject Professional Applications, Transaction Audits |

---

## ⚡ Core Engineering Highlights

### 1. Dual-Regime Indian Tax Engine (FY 2024–25)
* **Section 87A Rebate**: Full tax rebate up to ₹25,000 for taxable income $\le$ ₹7,00,000 under the New Regime (net tax = ₹0) and up to ₹12,500 for taxable income $\le$ ₹5,00,000 under the Old Regime.
* **Standard Deduction**: Automatically accounts for ₹50,000 salaried deduction.
* **Statutory Deduction Caps**: Automatically clamps claims to Indian Income Tax limits (Section 80C at ₹1,50,000; Section 80CCD(1B) NPS at ₹50,000; Section 80D at ₹50,000; Section 80TTA at ₹10,000).

### 2. High-Performance Budget Engine
* Replaced traditional $N+1$ query loops with single-query aggregate mapping (`GROUP BY category`), cutting database queries from $O(N)$ to $O(1)$.

### 3. Role-Based Access Control (RBAC)
* Independent session guards (`auth_admin.php`, `auth_professional.php`) guarding administrative and expert endpoints.

### 4. Automated Testing
* **Vitest Test Suite**: 20 unit & integration tests covering tax slab logic, rebate edge cases, deduction ceilings, budget calculations, and authentication rules.
* **Continuous Integration**: GitHub Actions automated pipeline executing syntax linting and test runs on every pull request and commit.

---

## 🏗️ System Architecture

```
                       Browser Client (Responsive HTML5 / CSS3 / JS)
                                     │
                 ┌───────────────────┼───────────────────┐
                 ▼                   ▼                   ▼
           [Public Site]       [User Portal]       [Admin Console]
          index / about /      dashboard.php       admin/dashboard.php
          service / login     tax_planning.php     admin/requests.php
                 │            my_appointments.php  admin/users.php
                 │                   │                   │
                 └───────────────────┼───────────────────┘
                                     │
                            [includes/ Layer]
                     ├── includes/header.php
                     ├── includes/navbar.php
                     ├── includes/tax_helper.php
                     └── includes/mailer.php
                                     │
                                     ▼
                    MySQL 8.0 (14 Normalized Tables)
          users, transactions, budgets, goals, investments,
          appointments, professionals, tax_deductions,
          professional_applications, professional_ratings,
          system_logs, password_resets
```

---

## 🚀 Deployment Guide (Vercel + Cloud Database)

FinCart includes pre-configured [`vercel.json`](vercel.json) and environment-aware [`db.php`](db.php).

### Step 1: Provision a Cloud MySQL Database (Free)
Because Vercel is a serverless application platform, your database needs to reside on a cloud host:
1. Create a free MySQL database on **[Aiven](https://aiven.io/)**, **[TiDB Cloud](https://tidbcloud.com/)**, or **[Railway](https://railway.app/)**.
2. Run the SQL script from [`fincart.sql`](fincart.sql) to create all 14 tables and initial demo seeds.

### Step 2: Deploy to Vercel
1. Import the GitHub repository `harshaair/fincart` into your **[Vercel Dashboard](https://vercel.com/)**.
2. In the **Environment Variables** section, add your database credentials:
   * `DB_HOST`: Your cloud database host (e.g. `gateway.tidbcloud.com` or `mysql-xxx.aivencloud.com`)
   * `DB_USER`: Your cloud database username
   * `DB_PASS`: Your cloud database password
   * `DB_NAME`: `fincart`
   * `DB_PORT`: `3306` (or provided port)
3. Click **Deploy**. Vercel will build and serve the application globally with free automated SSL.

---

## 💻 Local Development Setup

```bash
# 1. Clone repository
git clone https://github.com/harshaair/fincart.git
cd fincart

# 2. Start MySQL via XAMPP or WAMP and import schema
mysql -u root -p -e "CREATE DATABASE fincart;"
mysql -u root -p fincart < fincart.sql

# 3. Install test dependencies and run tests
npm install
npm test

# 4. Launch with PHP built-in server (or place in htdocs / www)
php -S localhost:8000
```

---

## 📄 License
This project is open-source under the [MIT License](LICENSE).

