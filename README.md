# SorSUpport

SorSUpport is a web-based complaint ticketing system for **Sorsogon State University – Bulan Campus**. Students submit concerns online. The Student Development Services (SDS) Office reviews them and sends each one to the college or office that can act on it. Every step is tracked until the concern is resolved and closed.

The system follows the procedures of the **SorSU Student Handbook (Revised 2024)** within its scope: taking in complaints, routing them, communicating about them, tracking them and reporting on them. Informal settlement, parent or guardian notification, hearings, sanctions and appeals happen outside the system; SorSUpport records only the referral and its outcome.

For the detailed implementation guide—including role permissions, status rules, validation, privacy, routes, scheduled tasks, reports, and the data model—see [System Documentation](docs/system-documentation.md).

## Who uses it

| Role | What they do |
|---|---|
| **Student** | Submits a ticket, with attachments, optionally hiding their identity. Follows its progress, talks with the handler, accepts the resolution or asks for further action, and rates how the concern was handled. Can request an update while Submitted or Needs Clarification, ask SDS to consider escalation while Assigned, In Progress or Escalated, and request withdrawal once per referral period after referral. These requests do not change the ticket or its handler. |
| **Recipient** (college or office staff) | Acknowledges tickets assigned to them, talks with the student, records the resolution, or returns a misrouted ticket to SDS with a staff-only reason. |
| **SDS Administrator** | Reviews new tickets, classifies, assigns, escalates or refers them, closes them, checks the read-only Needs attention list for pending requests, tickets with no recent action under the configured system defaults, and inactive handlers, manages accounts and settings, and reads the analytics and audit trail. |

When SDS closes a ticket as invalid, duplicate, out of scope, or no response, it must provide the student a written explanation of 10–1,000 characters. Explanations are optional for SDS-confirmed resolved, informational, and student-withdrawn closures.

## Features

- **Ticket lifecycle**: Submitted → Needs Clarification → Assigned → In Progress → Escalated / Referred → Resolved → Closed. A single set of rules decides who may take each action from each status.
- **Student status updates**: the ticket page shows the current office, plain-language guidance for what is happening next, a dated timeline of student-visible events excluding the student's own messages, and the date of the latest such event. Timeline dates use the application timezone. Staff action dates are not promised; the only displayed response date is the existing student response window after resolution.
- **Routing**: categories set by the admin, each with suggested recipients and escalation paths. For “Complaint Against a Student,” the CBME and CICT paths are Program Chair → College Dean; SDS must confirm the College Dean's assessment and endorsement before referring any ticket to the Campus Disciplinary Committee.
- **Informational tickets**: an informational ticket forwarded without closing stays stored as Submitted, but students and the receiving office see “Forwarded for information,” the receiving office and forwarding date. Forwarded items are excluded from SDS's awaiting-review queue and Submitted reminders; closing remains an SDS decision.
- CODI referrals use the canonical Committee on Decorum and Investigation name. “Other” entries matching that name or the acronym CODI are recognized after normalization.
- **Confidentiality**: a student can hide their identity where the category allows it. In the interface, only the filer sees their identity; everyone else, including SDS, sees “Anonymous.” The identity remains stored in the database. Analytics exports show aggregate identified/anonymous counts, not a student's name or ID, and contain no ticket subjects, descriptions or personnel-involved text. Sensitive tickets are visible only to the SDS Office and assigned handler; emails use “Confidential concern.” Unmasking for a CODI sworn complaint is a decision for SDS and the data privacy officer.
- Referral notes are visible to SDS only. Notes are free text, so SDS should not copy identifying details from a complaint into them.
- **Conversation**: each ticket has a message thread with file attachments, kept in private storage.
- **Notifications**: in-app notifications and unread badges, plus email requests to SDS when students ask for an update or for SDS to consider escalation. These requests do not notify the assigned handler.
- **Returned tickets**: a recipient may manually return an Assigned, In Progress or Escalated ticket to SDS with a 10–500 character reason. The reason is visible to SDS only; the SDS admins are notified in-app and by email, and the student is not separately emailed about the return.
- **Reminders for waiting tickets**: adjustable system-default email reminders for tickets that have waited (see below).
- **Needs attention**: an SDS-only dashboard and paginated ticket list highlights pending student requests, tickets returned by an office until SDS acts, tickets with no recent action under existing adjustable reminder settings, and inactive handler accounts. It is informational only; SDS makes any ticket decision manually.
- **Accounts**: SDS creates student and recipient accounts inactive and unverified, with the student or staff ID as the initial password. Normal path: at first sign-in, the user is stopped at email verification and must click the emailed link, then set a personal password before reaching any page. SDS activation override: if verification cannot be completed (for example, the email is wrong or unreachable), SDS may activate the account; this marks it active and verified. On the next sign-in, a user still required to change the initial password is sent to set a personal password. Reactivation preserves an already-changed password and does not force another password change. Until the user first signs in, the password is still the initial ID, so SDS should activate only after confirming the person's identity and tell the user to sign in and set a password immediately. Activation is recorded in the audit trail. SDS has no resend control; the user requests a new link on the verification screen. A failed verification email has no dedicated audit entry.
- **Analytics**: a compact, filtered dashboard with Overview, Response and resolution, Staff and offices, and Students and feedback tabs. The six KPIs are total tickets (with open now), resolution rate (recorded resolutions divided by eligible needs-resolution tickets, with excluded closure counts), active handling time (median and average after clarification/referral waits), first staff reply (SDS or recipient, median), escalation rate (needs-resolution tickets escalated at least once divided by needs-resolution tickets), and satisfaction (average and rating count). Resolution rate excludes withdrawn, duplicate, out-of-scope, invalid, and no-response closures; committee outcomes are shown separately for handling time. Staff and office metrics include review and acknowledgment times, further-action rate, reopens, student requests, and workload. Sensitive-category tickets remain in totals and category distribution but are not broken down by student attributes. The dashboard does not display ticket subjects; analytics exports contain no ticket subjects. Escalations are counted by category and level, not per handler. Reports are exportable to PDF and Excel.
- **Audit trail**: every action and every email sent, searchable by person, ticket and date.

The handbook path for a complaint about a student's misconduct starts with the SDS Coordinator or the student's teacher, adviser or professor, then proceeds to the Program Chair and College Dean. A referral to the Campus Disciplinary Committee requires SDS to confirm that the Dean assessed and endorsed the matter. Informal settlement, parent or guardian notification, hearings and sanctions take place outside SorSUpport.

### Reminders for waiting tickets

The system emails whoever has to act next on a ticket that has waited too long with nothing recorded on it. Waiting is counted in **working days** (Monday to Friday), adjusted by the SDS-managed Working Calendar, and reminders are checked every hour of the working day.

No holiday dates are shipped because holidays are set by proclamation. SDS must keep the Working Calendar up to date; changing a past date also changes how past waiting time is counted. Override dates and descriptions are managed under **Working Calendar**.

| Ticket is… | Who is reminded | After | Basis |
|---|---|---|---|
| Submitted, not yet classified or assigned | SDS Office | 2 working days | System default |
| The same, in a sensitive category | SDS Office | 1 working day | Adjustable system default informed by the CODI 24-hour notice rule (Handbook p. 58); not a deadline for SDS. |
| Needs Clarification, student has not replied | Student; SDS Office once at twice the interval | 5 working days | System default, not a handbook deadline; adjustable from 1 to 30 working days |
| Assigned, not yet acknowledged | Assigned handler | 2 working days | System default |
| In progress or escalated, no action recorded | Assigned handler | 5 working days | System default |
| Referred to CODI, no outcome recorded | SDS Office | 10 working days | Adjustable reminder default informed by CODI's decision period (Handbook pp. 57–58); not a deadline for SDS. |
| Referred to another committee, no outcome recorded | SDS Office | Off by default | System default, not a handbook deadline; the SDS admin can enable and adjust it |
| Resolved, student did not respond | SDS Office, once | 15 calendar days | System default |

A reminder repeats every that many working days until someone acts, and any action on the ticket restarts the count. Reminders never change a ticket; escalating, reassigning and closing stay the SDS admin's decision. The SDS admin can change the waiting days, or turn reminders off, under **System Settings → Reminders**. The starting values are in [config/tickets.php](config/tickets.php).

For Needs Clarification, the student reminder repeats at the configured interval after the latest non-email ticket action. At twice that interval, SDS admins receive a one-time notice for that wait period that they may follow up or close the ticket as no response. This system default is not a handbook deadline. Neither email changes the ticket, and the reminder is sent only while reminders are enabled.

Reminders are operational nudges to the person who must act next. The handbook sets no deadline for acknowledging or resolving ordinary complaints. Every reminder is an SDS-adjustable system default, not a handbook deadline, and reminders never change a ticket. The handbook's 15-day appeal periods apply to the respondent student; the CODI timelines apply to CODI matters.

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
| `TICKET_SUBMISSIONS_PER_HOUR` | Tickets one student may submit in an hour (default 5). |

Reminder timing is set by the SDS admin in System Settings, not in `.env`.

SDS cannot deactivate a recipient while that account is assigned to an open ticket; the tickets must first be reassigned with the existing Assign action. Removing an account with ticket-related records anonymizes it and retains the records; accounts without such records may be deleted. Retention of anonymized records is a decision for SDS and the university's data privacy officer.

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
