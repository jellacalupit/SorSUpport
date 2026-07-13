# SorSUpport Development Plan
### A Step-by-Step Build Guide for the Web-Based Student Complaint and Ticketing System

This plan translates your finalized design (Chapter 4: DFDs, Use Case Diagram, Class Diagram, Sequence Diagrams) into an actual build order. It follows the same module sequence as your Gantt chart, and treats the three interfaces — **Student**, **SDS Administrator**, and **Recipient** — as separate concerns from day one, so they never get tangled together in your codebase.

---

## 0. Guiding Principles Before You Start

1. **One Laravel app, three "faces."** You are not building three separate apps. You're building one Laravel project with three role-scoped route groups, three Blade layouts, and RBAC middleware that decides who sees what. This matches your Class Diagram, where `Student`, `SDSAdministrator`, and `Recipient` all inherit from a single `User` class.
2. **Build in the order your Gantt chart already specifies.** Auth/Accounts → Complaint Submission → Ticket Review/Classification → Ticket Assignment/Deadlines → In-Ticket Communication → Escalation → Email Notifications → Analytics/Reports → Audit Trail (audit logging actually gets wired in *throughout*, not last — see note in Module 9).
3. **Every module should be testable in isolation before you connect it to the next one.** This matches your own Alpha Testing plan (unit → integration → system).

---

## 1. Software to Download and Install

Install these **in this order** on Windows 11 (64-bit), matching your Software Specification table:

| # | Tool | Purpose | Download |
|---|------|---------|----------|
| 1 | **Git** | Version control, connects to GitHub | https://git-scm.com/download/win |
| 2 | **XAMPP 8.2.12** (includes Apache, PHP 8.2, MariaDB 10.4.32, phpMyAdmin) | Local server + database | https://www.apachefriends.org |
| 3 | **Composer** | PHP dependency manager (required for Laravel) | https://getcomposer.org/download |
| 4 | **Node.js (LTS) + npm** | Needed to compile TailwindCSS and frontend assets via Vite | https://nodejs.org |
| 5 | **Visual Studio Code 1.114** | Code editor | https://code.visualstudio.com |
| 6 | **Google Chrome** | Primary testing browser | https://www.google.com/chrome |
| 7 | **GitHub account + repository** | Central code storage for the team | https://github.com |

### VS Code Extensions to install (Extensions tab, Ctrl+Shift+X)
- PHP Intelephense
- Laravel Blade Snippets
- Laravel Extra Intellisense
- Tailwind CSS IntelliSense
- GitLens
- DotENV (for reading `.env` files safely)

### Verify installs (open a terminal / Command Prompt and run):
```bash
git --version
php --version
composer --version
node --version
npm --version
```
If any command isn't recognized, it usually means PHP/Composer wasn't added to your system PATH — XAMPP's `php.exe` lives in `C:\xampp\php`, so add that folder to your Windows Environment Variables PATH.

---

## 2. Environment Setup

1. **Start XAMPP.** Open the XAMPP Control Panel → start **Apache** and **MySQL** (MariaDB uses the MySQL service name inside XAMPP).
2. **Create the database.** Go to `http://localhost/phpmyadmin` → New → name it `sorsupport_db` (utf8mb4_unicode_ci collation).
3. **Clone or create the GitHub repo.**
   ```bash
   git clone https://github.com/<your-org>/sorsupport.git
   cd sorsupport
   ```
4. **Set up branch strategy** so four people don't overwrite each other:
   - `main` → always deployable
   - `dev` → integration branch
   - `feature/<module-name>` → e.g. `feature/complaint-submission`, `feature/ticket-escalation`
   - Merge into `dev` via Pull Request, only merge `dev` → `main` after a module passes testing.

---

## 3. Initialize the Laravel Project

```bash
composer create-project laravel/laravel sorsupport "13.*"
cd sorsupport
```

### Install TailwindCSS 4.2.2 (via Vite, Laravel's default bundler)
```bash
npm install
npm install tailwindcss @tailwindcss/vite
```
Configure `vite.config.js` to include the Tailwind plugin, and import Tailwind in `resources/css/app.css` with `@import "tailwindcss";`

### Install PHPMailer (or use Laravel's built-in Mail with SMTP — recommended)
Laravel already has a mail system built on Symfony Mailer, which works perfectly with Gmail SMTP without needing the raw PHPMailer library. In `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_project_gmail@gmail.com
MAIL_PASSWORD=your_16_char_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_project_gmail@gmail.com
MAIL_FROM_NAME="SorSUpport"
```
**Important:** Gmail requires an **App Password** (not your normal Gmail password) once 2-Step Verification is on. Generate one at Google Account → Security → App Passwords. If your paper commits specifically to the `PHPMailer` package/library rather than Laravel's native Mail, install it via `composer require phpmailer/phpmailer` instead and wrap it in a custom `MailService` class — either approach produces the same Gmail SMTP behavior described in your Class Diagram's `EmailNotification` class.

### Configure the database connection in `.env`
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sorsupport_db
DB_USERNAME=root
DB_PASSWORD=
```

### Confirm it runs
```bash
php artisan serve
npm run dev
```
Visit `http://localhost:8000` — you should see the default Laravel welcome page.

---

## 4. Database Design — Translate the Class Diagram into Migrations

Build these migrations in this order (foreign keys need parent tables to exist first):

1. `users` — id, name, email, password, role (enum: student/sds_admin/recipient), is_active, email_verified_at, timestamps. *(Maps to your `User` superclass.)*
2. `students` — user_id (FK), student_id, department, course, year_level, block
3. `recipients` — user_id (FK), staff_id, department, designation
4. `complaint_categories` — id, name, description, resolution_deadline_days
5. `escalation_hierarchies` — id, category_id (FK), level, recipient_id (FK)
6. `complaints` — id, subject_title, category_id (FK), personnel_involved, description, file_attachment, is_anonymous, submitted_by (nullable FK to users), submitted_at
7. `tickets` — id, complaint_id (FK), status (Pending/Assigned/In Progress/Resolved/Escalated/Closed/Invalid), classification (Needs Resolution/Informational, nullable until classified), assigned_to (nullable FK to users), deadline_date, created_at, closed_at
8. `ticket_threads` — id, ticket_id (FK), is_active, created_at, closed_at
9. `thread_messages` — id, thread_id (FK), sender_id (FK), content, file_attachment, sent_at
10. `audit_logs` — id, ticket_id (FK), action, performed_by (FK), performed_at, details
11. `email_notifications` — id, ticket_id (nullable FK), recipient_email, type, sent_at, status
12. `analytics_reports` — id, generated_by (FK), date_range, format, data_json, generated_at

**Tip:** Use `php artisan make:model Ticket -m` for each entity — this generates the model and the migration file together, matching the method signatures already defined in your Class Diagram (e.g., `Ticket::assign()`, `Ticket::escalate()`, `Ticket::close()` become model methods).

### Seed essential data
Create a `DatabaseSeeder` that inserts:
- One default SDS Administrator account (so you have someone to log in as)
- 2–3 sample complaint categories with deadlines, for testing ticket classification/escalation early

---

## 5. Build Order (matches your Gantt Chart's Implementation phase)

### Module 1 — Authentication, Roles, and Account Management (Admin interface)
- Build the **initial-login / email-verification / password-setup flow** exactly as shown in Fig. 4.7: user logs in with ID as both username and default password → email verification link → new password → redirect to role interface.
- Build RBAC middleware (`role:sds_admin`, `role:student`, `role:recipient`) applied to route groups.
- Build the **bulk upload feature** (CSV/Excel via `maatwebsite/excel` package: `composer require maatwebsite/excel`) that creates/updates/deactivates Student and Recipient accounts, matching Fig. 4.6.
- Build manual account CRUD (create/update/deactivate/reactivate) for the SDS Administrator.
- **Test before moving on:** Can all three roles log in, verify, and land on distinct dashboards?

### Module 2 — Complaint Submission (Student interface)
- Build the structured complaint form: subject title, category dropdown (from `complaint_categories`), personnel involved, description, optional file upload, anonymous checkbox.
- Wire the branch logic from Fig. 4.8: identified submissions → create Pending ticket + notify SDS Admin; anonymous submissions → auto-store as Informational, no ticket, no notification.
- **Test:** Submit both anonymous and identified complaints, confirm correct DB records and correct absence/presence of notifications.

### Module 3 — Ticket Review and Classification (Admin interface)
- Build the Pending tickets queue for the SDS Administrator.
- Build the two-step review UI from Fig. 4.9 and 4.10: Validity (valid/invalid + written reason) → Classification (Needs Resolution/Informational) → Jurisdiction (SDS handles directly / route to Recipient).
- Wire audit logging on every decision at this stage (see Module 9 note).
- **Test:** Walk a ticket through invalid-closure, valid+Informational, and valid+Needs Resolution paths.

### Module 4 — Ticket Routing, Assignment, and Deadline Configuration (Admin interface)
- Build the System Settings screens from Fig. 4.5: category creation, resolution deadline per category, escalation hierarchy per category.
- Build ticket assignment (Fig. 4.11): assigning to SDS Admin directly vs. to a Recipient, applying the category's configured deadline, activating the In-Ticket Communication thread for Needs Resolution tickets.
- **Test:** Confirm the deadline auto-calculates correctly from the category configuration at the moment of assignment.

### Module 5 — In-Ticket Communication (Shared: Student, Admin, Recipient)
- Build the thread UI (messages + file attachments) shown to whichever parties have access, per Fig. 4.12–4.13.
- Enforce: thread only exists for Needs Resolution tickets, becomes read-only once the ticket is Closed.
- **Test:** Confirm a Recipient only sees threads for tickets assigned to them, never other tickets.

### Module 6 — Ticket Acknowledgment, Resolution, and Closure
- Build Acknowledge Ticket (Assigned → In Progress), Mark as Resolved, and Close Ticket (Admin-only), per Fig. 4.12–4.13.
- **Test:** Confirm only the SDS Administrator's account can close a ticket, even if logged in as the assigned Recipient.

### Module 7 — Escalation (Automatic + Manual)
- Build a **scheduled task** (Laravel's Task Scheduling, `app/Console/Kernel.php` or `routes/console.php` in Laravel 11+/13 style) that runs daily, checks for tickets past deadline, and escalates per Fig. 4.14.
- Build the manual "Escalate Ticket" action for the SDS Administrator (Fig. 4.15).
- On a local Windows dev machine, simulate this with:
  ```bash
  php artisan schedule:work
  ```
  which keeps the scheduler running in the terminal while you develop.
- **Test:** Manually set a ticket's deadline to yesterday's date in the database and confirm the scheduled command escalates it correctly.

### Module 8 — Email Notifications and Daily Reminders
- Build a dedicated `NotificationService` (or use Laravel Notifications: `php artisan make:notification TicketAssigned`) that gets triggered by events: submission, assignment, status change, escalation, closure, and the daily deadline reminder.
- Use **Laravel Queues** so emails send in the background instead of blocking the user's request (`QUEUE_CONNECTION=database` in `.env`, then `php artisan queue:work`).
- **Test:** Use https://mailtrap.io or a real Gmail test account to confirm every notification type actually arrives with correct content.

### Module 9 — Audit Trail (built incrementally, not as a separate final step)
- Rather than bolting this on at the end, add an `AuditLog::record()` call at every state-changing action across Modules 3–7 as you build them (submission, validity decision, classification, assignment, reassignment, escalation, message sent, resolution, closure).
- Build the Admin-facing audit trail viewer last, once all logging calls exist.

### Module 10 — Analytics Dashboard and Report Generation (Admin interface)
- Build dashboard queries: complaint volume, category distribution, resolution rates, average resolution time, escalation frequency, active ticket statuses (Fig. 4.16).
- Consider `laravel-excel` (already installed in Module 1) for Excel export, and `barryvdh/laravel-dompdf` for PDF export.
- **Test:** Cross-check dashboard numbers against manually counted database records for a small test dataset.

---

## 6. Keeping the Three Interfaces Cleanly Separated

**Routes** (`routes/web.php`), grouped by role and middleware:
```php
Route::middleware(['auth', 'role:student'])->prefix('student')->group(function () {
    // student routes
});

Route::middleware(['auth', 'role:sds_admin'])->prefix('admin')->group(function () {
    // admin routes
});

Route::middleware(['auth', 'role:recipient'])->prefix('recipient')->group(function () {
    // recipient routes
});
```

**Views**, organized as:
```
resources/views/
├── layouts/
│   ├── student.blade.php
│   ├── admin.blade.php
│   └── recipient.blade.php
├── student/
├── admin/
└── recipient/
```

**Controllers**, namespaced similarly:
```
app/Http/Controllers/
├── Student/
├── Admin/
└── Recipient/
```

This mirrors your Use Case Diagram directly — shared use cases (Log In, In-Ticket Communication, File Attachment, View Ticket Status, Receive Email Notifications) become shared components/partials reused across all three layout folders, while role-specific use cases stay in their own controller namespace.

---

## 7. Testing Plan (matches your Alpha/Beta Testing sections)

- **Unit testing:** Use Pest or PHPUnit (`php artisan test`) for individual model methods — e.g., does `Ticket::escalate()` correctly pick the next hierarchy level?
- **Integration testing:** Test that controllers + models + notifications work together — e.g., does submitting a complaint actually create a ticket AND queue an email?
- **System (black-box) testing:** Write test cases per functional requirement in Table 4.1, with Input / Expected Output / Actual Output / Pass-Fail columns, exactly as you described in Section 4.10.
- **Beta testing:** Only after deployment, with real Students, the SDS Admin, and Recipients from campus.

---

## 8. Deployment

1. **Push to GitHub**, connect the repo to **Render** for the Laravel backend + MariaDB database (Render supports PHP via Docker or its native PHP runtime — you'll likely use a `Dockerfile` or Render's Laravel guide).
2. Set all `.env` values (DB credentials, mail credentials, `APP_KEY`, `APP_URL`) as environment variables in Render's dashboard — never commit `.env` to GitHub.
3. Run `php artisan migrate --force` on first deploy to build the production database schema.
4. **On the Vercel/Blade question:** Your paper states Vercel serves the frontend (HTML/CSS/JS/Tailwind) while Render serves Laravel + MariaDB. Since your View layer is Laravel Blade (server-rendered by Laravel itself, not static files), the practical setup is that the **entire Laravel app — including Blade views — deploys as one unit on Render**. Vercel would only come into play if you later split into a separate API (Laravel on Render) plus a decoupled frontend (React/Vue on Vercel). Worth a quick team conversation before deployment week so your actual setup matches what you defend.
5. Set up `php artisan schedule:work` or Render's Cron Job feature to keep escalation/reminder checks running in production.
6. Set up `php artisan queue:work` as a persistent background worker (Render supports this as a separate "Worker" service type) so emails don't block requests.

---

## 9. Suggested Personal Checklist Order

- [ ] Install all tools (Section 1) and verify with terminal commands
- [ ] XAMPP running, database created
- [ ] Laravel project created, Tailwind + Vite working, `.env` configured
- [ ] All migrations written and run successfully (`php artisan migrate`)
- [ ] Seeder creates a working Admin login
- [ ] Module 1: Auth + RBAC + Bulk Upload
- [ ] Module 2: Complaint Submission
- [ ] Module 3: Ticket Review/Classification
- [ ] Module 4: Assignment + Deadlines
- [ ] Module 5: In-Ticket Communication
- [ ] Module 6: Acknowledgment/Resolution/Closure
- [ ] Module 7: Escalation (auto + manual)
- [ ] Module 8: Email Notifications (queued)
- [ ] Module 9: Audit Trail viewer
- [ ] Module 10: Analytics Dashboard + Reports
- [ ] Full system test pass (Table 4.1 functional requirements)
- [ ] Deploy to Render, confirm scheduler + queue worker running
- [ ] Beta test with real users
