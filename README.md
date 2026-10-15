# SorSUpport

SorSUpport is a web-based complaint ticketing system for **Sorsogon State University – Bulan Campus**. Students submit concerns online. The Student Development Services (SDS) Office reviews them and sends each one to the college or office that can act on it. Every step is tracked until the concern is resolved and closed.

The system follows the procedures of the **SorSU Student Handbook (Revised 2024)** within its scope: taking in complaints, routing them, communicating about them, tracking them and reporting on them. Hearings and sanctions happen outside the system; SorSUpport records only the referral and its outcome.

## Who uses it

| Role | What they do |
|---|---|
| **Student** | Submits a ticket, with attachments, optionally hiding their identity. Follows its progress, talks with the handler, accepts the resolution or asks for further action, and rates how the concern was handled. |
| **Recipient** (college or office staff) | Acknowledges tickets assigned to them, talks with the student and records the resolution. |
| **SDS Administrator** | Reviews new tickets, classifies, assigns, escalates or refers them, closes them, manages accounts and settings, and reads the analytics and audit trail. |

## Features

- **Ticket lifecycle**: Submitted → Needs Clarification → Assigned → In Progress → Escalated / Referred → Resolved → Closed. A single set of rules decides who may take each action from each status.
- **Routing**: categories set by the admin, each with suggested recipients and escalation paths.
- **Confidentiality**: a student can hide their identity where the category allows it. Their name is then hidden from everyone in the system, including the SDS admin. Sensitive tickets are visible only to the SDS Office and the assigned handler. The system flags when a ticket goes to someone named in the complaint.
- **Conversation**: each ticket has a message thread with file attachments, kept in private storage.
- **Notifications**: in-app notifications and unread badges, plus an email at every step for each person involved.
- **Reminders for waiting tickets**: a daily high-priority email when a ticket has waited too long (see below).
- **Accounts**: created one by one or in bulk from an Excel file. New users must set their own password on first login.
- **Analytics**: ticket volume, resolution rate and time, escalations, performance per office, and satisfaction ratings, exportable to PDF and Excel.
- **Audit trail**: every action and every email sent, searchable by person, ticket and date.

### Reminders for waiting tickets

Each morning the system emails whoever has to act next on a ticket that has waited too long with nothing recorded on it:

| Ticket is… | Who is reminded | After |
|---|---|---|
| Submitted, not yet classified or assigned | SDS Office | 2 days |
| Assigned, not yet acknowledged | Assigned handler | 2 days |
| In progress or escalated, no action recorded | Assigned handler | 5 days |

The reminder repeats every that many days until someone acts. The waiting periods can be changed with the settings in [config/tickets.php](config/tickets.php).

### Email delivery

Ticket emails are queued and sent every minute. If sending fails, the email is tried again after 1, 5, 15 and 60 minutes. After five failed tries it is marked failed and recorded in the audit trail, so the SDS Office can reach the person another way.

## Technology

- Laravel 12 (PHP 8.2), Blade, Tailwind CSS 4, Alpine.js, Vite
- MySQL
- Brevo for email, DomPDF and Laravel Excel for reports
- Docker image deployed on Railway

## Running it locally

Requirements: PHP 8.2+, Composer, Node.js 22+, and MySQL (for example through XAMPP).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database named `sorsupport_db`, set the `DB_*` values in `.env`, then:

```bash
php artisan migrate --seed   # creates the tables and the default SDS admin account
npm run build                # or `npm run dev` while working on the interface
php artisan serve            # http://localhost:8000
```

**MySQL must be running before you start the app.** Logins are stored in the database, so login fails while MySQL is off.

The seeder creates one SDS admin account with the Staff ID `12345` (see [database/seeders/SdsAdminSeeder.php](database/seeders/SdsAdminSeeder.php)). You will be asked to set a new password the first time you sign in. Colleges, offices, categories and other accounts are then set up from the admin pages.

Sample data for demonstrations:

```bash
php artisan db:seed --class=DemoAccountSeeder
php artisan db:seed --class=AnalyticsSeeder
```

To send emails and reminders locally, run the scheduler in a second terminal:

```bash
php artisan schedule:work
```

## Configuration

Besides the usual Laravel settings, `.env` takes:

| Setting | Purpose |
|---|---|
| `MAIL_MAILER=brevo`, `BREVO_API_KEY` | Sends email through Brevo. Use `MAIL_MAILER=log` to write emails to the log instead. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender shown on every email. |
| `REMIND_UNREVIEWED_AFTER_DAYS` | Days before the SDS Office is reminded of an unreviewed ticket (default 2). |
| `REMIND_UNACKNOWLEDGED_AFTER_DAYS` | Days before a handler is reminded to acknowledge a ticket (default 2). |
| `REMIND_IDLE_AFTER_DAYS` | Days without action before a handler is reminded (default 5). |
| `REMINDERS_SEND_AT` | Time the daily reminders go out (default `08:00`, Philippine time). |
| `TICKET_SUBMISSIONS_PER_HOUR` | Tickets one student may submit in an hour (default 5). |

Useful commands:

```bash
php artisan notifications:send        # send queued ticket emails now
php artisan tickets:send-reminders    # queue reminders for waiting tickets now
php artisan mail:check                # check that the mail service accepts the configured key
php artisan test                      # run the automated tests
```

## Deployment

The app is deployed on Railway from the `main` branch using the [Dockerfile](Dockerfile). On every start, [docker/start.sh](docker/start.sh):

1. links the private attachment folder inside the persistent uploads volume,
2. runs the database migrations,
3. starts the queue worker and the scheduler that send emails and reminders,
4. checks that the mail service can be reached and reports the result in the deployment log.

Seeders do not run on deploy.

## Project structure

| Path | Contents |
|---|---|
| [app/Services/TicketWorkflow.php](app/Services/TicketWorkflow.php) | The ticket lifecycle: every action, who may take it, and what it changes |
| [app/Services/TicketAccess.php](app/Services/TicketAccess.php) | Who may open a ticket |
| [app/Services/TicketEscalationService.php](app/Services/TicketEscalationService.php) | Escalation paths per category |
| [app/Services/TicketReminderService.php](app/Services/TicketReminderService.php) | Reminders for waiting tickets |
| [app/Services/EmailNotificationService.php](app/Services/EmailNotificationService.php), [TicketEmailComposer.php](app/Services/TicketEmailComposer.php) | Sending emails and writing them for each reader |
| [app/Services/AnalyticsService.php](app/Services/AnalyticsService.php) | Analytics figures and reports |
| `app/Http/Controllers/{Admin,Student,Recipient}` | Pages for each role |
| `resources/views` | Blade templates |
| `tests/Feature` | Automated tests, grouped by module |

## Credits

Developed as a capstone project for Sorsogon State University – Bulan Campus.
