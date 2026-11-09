# SorSUpport System Documentation

This is the implementation reference for the SorSUpport application in this repository. It describes behavior wired into the current routes, controllers, models, services, and configuration. It is not a policy substitute: committee hearings and sanctions are outside the application.

## 1. System summary

SorSUpport is a Laravel web application for complaint intake and ticket tracking at Sorsogon State University – Bulan Campus. The SDS Office (represented by the `sds_admin` role) reviews submissions, decides how they should be classified and routed, and records the result. Students and recipients communicate through the ticket. Escalations are selected by an SDS administrator; the system does not escalate automatically.

The application separates the complaint submitted by the student from its workflow ticket:

1. A student submits a `Complaint`.
2. The system creates one associated `Ticket` in `submitted` status.
3. The SDS Office reviews, classifies, assigns, handles, refers, resolves, or closes the ticket.
4. The complaint mirrors the ticket's status. The ticket also owns the conversation, audit events, escalation data, notification records, and feedback.

No self-registration route is defined. Accounts are provisioned by an administrator or imported in bulk.

### Technology

The application uses Laravel 12 and PHP 8.2, Blade views, Tailwind CSS, Alpine.js, and Vite. Its configured deployment target uses MySQL and Docker; outbound email can be sent through Brevo. PDF reports use DomPDF and spreadsheet import/export uses Laravel Excel. The existing [README](../README.md) contains local setup, environment, seed, and deployment instructions.

## 2. Roles and permissions

All role pages require authentication, an active account, completion of required password setup, and the relevant role. Middleware aliases are configured in `bootstrap/app.php`.

| Role | Main capabilities |
|---|---|
| `student` | Create and track own complaints; view own ticket and conversation; reply and attach files while the conversation is open; answer clarification requests; request an update while Submitted or Needs Clarification; ask SDS to consider escalation while Assigned, In Progress, or Escalated; withdraw an unresolved ticket before referral or request withdrawal from SDS after referral; accept a resolution or request further action; rate eligible closed tickets; manage notifications and profile photo. |
| `recipient` | View tickets assigned to the recipient account and informational tickets forwarded to that recipient; acknowledge assigned work; exchange messages on assigned tickets; mark assigned tickets resolved; manage notifications and profile photo. Recipients cannot assign, escalate, refer, close, or reject tickets. |
| `sds_admin` | Review all submissions; edit pre-classification category/suggested-recipient details; classify, assign, handle, request clarification, escalate, refer, record referral outcomes, reopen, reject, close, and retain/forward informational tickets; review the read-only Needs attention list; manage accounts, categories, escalation paths, units, reminder settings; view analytics and audit trail. |

### Access checks

- Admin routes use `auth`, `active.user`, `force.password`, and `role:sds_admin`.
- Student and recipient page groups apply the same first three checks with their respective role.
- Ticket actions additionally pass through `TicketWorkflow`'s actor/status rules.
- The Needs attention dashboard/list is display-only and is available only through the SDS-admin route group.
- Complaint and ticket pages check ownership or handler status; recipient access is restricted to their assigned ticket or an informational ticket forwarded to their recipient profile.
- Ticket attachment routes require authentication, an active account, and the same ticket-visibility check. Files are read from private storage and streamed; direct public-file URLs are not used for ticket attachments.
- An inactive account is logged out on protected requests. Verification and logout routes are allowed through the active-account middleware.
- Route-specific `role` checks are not a replacement for record-level ownership/assignment checks.

## 3. Authentication, accounts, and profiles

### Sign-in and password rules

- Sign-in accepts the account `username` (student/staff ID), not an email address, plus a password.
- The sign-in request is rate-limited to five attempts per transliterated username-and-IP key.
- Admin-provisioned student and recipient accounts start inactive, unverified, and marked `must_change_password`. Their initial password is their student/staff ID. An account must be activated before the user can use protected pages.
- Reactivating an account verifies its email and requires its role profile to be complete. First-time users still have to set a password.
- A new password requires confirmation, at least eight characters, and cannot equal the username/ID.
- If an account that should no longer use an initial password is still signed in using its username as its password, the sign-in flow sets `must_change_password` again.
- When a user must change a password, non-admins are sent to the force-password page. SDS administrators are directed to their profile page. Profile/password/logout and verification routes are permitted during setup.
- Email verification and password reset use custom notifications. Verification notification requests are throttled (six per minute). Password reset and email verification are part of the authentication routes in `routes/auth.php`.
- Registration is not exposed.

### Account management

The SDS administrator can search and filter accounts by status, role, college/office, program, year, and block. The page shows student accounts separately from recipient/admin accounts and paginates each list.

Creating an account:

- The account name and email are required; email must be unique.
- Student ID must be eight digits. A student must have a configured college, a program belonging to that college, year 1–4, and an optional positive integer block.
- A recipient must have a staff ID, a configured college or office unit, and a designation.
- Student or staff ID becomes the username and initial password. New accounts start inactive and require a password change.
- Only `student` and `recipient` roles can be created through the account form; the SDS admin account is seeded separately.

Updating an account changes the profile and account details. If the account still requires its initial password, changing its ID also resets that temporary password to the new ID. A password already chosen by the user is not reset by this helper.

Deactivation prevents access and sends an account-update email. Reactivation requires a complete role profile, sets the account active, verifies its email, and sends an activation email. Account create/update/activation/deactivation/import actions are recorded as standalone audit activity.

A recipient account cannot be deactivated while it is the assignee or current handler of an Assigned, In Progress, or Escalated ticket. The account page lists the affected ticket references with links; SDS must reassign them using the existing Assign action. Bulk imports that request deactivation skip the affected row and report the same reason.

SDS can remove student and recipient accounts. An account with a related complaint, ticket, message, or audit record is anonymized instead of hard-deleted: its name becomes “Deleted user”, its username/email are replaced with unique placeholders, its photo is cleared, and it is made inactive. Related ticket history is retained, and account-name references in audit details are redacted. An account without related records can still be hard-deleted. Self-service profile deletion keeps its current-password confirmation and follows the same retention rule. Retention of anonymized records is a decision for SDS and the university's data privacy officer.

### Bulk account import

- Accepted upload types: CSV, XLSX, and XLS; maximum file size 10 MB.
- The selected import type is student or recipient; the default is student.
- The spreadsheet uses a heading row. Blank rows are ignored, and validation failures are reported with spreadsheet row numbers.
- Student imports require the same configured college/program relationship and eight-digit student ID. Year level accepts `1`–`4`, optionally suffixed with `st`, `nd`, `rd`, or `th` and optionally `year`.
- Recipient imports require staff ID; unit and designation can be blank in import rows, but account activation requires a complete profile.
- The importer supports legacy headings: `year`, `position`, `department`, `course`, `college_office`, `office`, and `recipient_department`, as well as `full_name`.
- Status accepts `active`, `inactive`, `1`, or `0`; omitted status defaults to active for an existing verified account. Newly created and unverified accounts remain inactive until activated by an administrator.
- Existing records are matched by the selected account ID, then email, and updated only when the role matches. Duplicate IDs or emails in the same file are rejected. The result reports total/imported/failed/duplicate/created/updated counts and row errors; row errors can be downloaded as CSV.
- An unreadable workbook returns an upload error; it is not reported as a successful import.

### User profiles

- Users can update permitted profile information and profile photo. Profile photos accept JPG/JPEG, PNG, or WebP, up to 2 MB, and are stored on the public avatar disk.
- SDS admin profile edits use separate first/middle/last-name fields, an optional extension, a numeric username, and configured unit/designation values.
- A changed email for non-admin users clears email verification. Profile edits and photo changes are audited; account changes send an account-update email.
- Deleting a profile requires the current password and logs the deletion before removing the user account.

## 4. Complaint submission and privacy

### Submission form and rules

The student selects an active category, enters a subject and description, can name personnel involved, can optionally suggest an active recipient, may add attachments, and can hide their identity only when the category allows it. The required declaration is:

> I declare that the information I am submitting is true and correct to the best of my knowledge, and I understand that a false or malicious complaint may be dealt with under the Student Handbook. I consent to the collection and use of this information by Sorsogon State University to act on my concern, in accordance with the Data Privacy Act of 2012.

Validation and system rules:

- Category must exist and be active.
- Subject is required, maximum 50 characters.
- Personnel involved is optional, maximum 255 characters.
- Description is required, maximum 5,000 characters.
- Up to 10 attachments may be uploaded; each must be PDF, DOCX, JPG/JPEG, PNG, or HEIC and at most 10 MB.
- Hidden identity is a boolean and is rejected if the selected category does not allow it.
- The declaration must be accepted.
- A submitted suggestion must resolve to an active recipient.
- Each student may submit at most the configured number of complaints in a rolling hour. `TICKET_SUBMISSIONS_PER_HOUR` defaults to 5; runtime minimum is 1.
- When a category is configured for recipient jurisdiction but has no recipient, submission is refused with a category error.
- Reference numbers are generated as `SU-<year>-<five-digit-sequence>`, such as `SU-2026-00001`.
- Every accepted complaint gets a ticket in `submitted` status and is queued for SDS review, even if a suggested recipient was selected. Submission does not directly assign the ticket.
- Anonymous submission creates an audit event without identifying the performer and does not send the ordinary submission-acknowledgment email to the student. The SDS review notification is still created.

### Identity and sensitive-content rules

- For a hidden-identity complaint, the student who filed it can see their identity; all other viewers, including SDS administrators, see `Anonymous`.
- For a sensitive category, a dashboard/report/email-safe subject is `Confidential concern`, not the original subject.
- Sensitive categories are always configured to allow hidden identity. The category editor forces `allows_hidden_identity` on whenever `is_sensitive` is on.
- A handler-selection action checks whether the proposed recipient appears by full name, first-and-last name, or (in the personnel-involved field) surname. It records a `conflict_of_interest_flagged` audit event when it detects a match. This is a flag, not an automatic block.
- Student-facing notification details omit staff-only escalation reasons and disclose the referred destination without exposing the admin's referral note. Recipient referral notifications also show only the destination; escalation reasons remain visible to the escalation target.
- Referral notes are visible to SDS only. Notes are free text, so SDS should not copy identifying details from a complaint into them.
- The audit search avoids matching performer names on anonymous complaints. Email audit messages refer to an anonymous submitter as “the student,” not by address.
- The student ticket page shows a timeline using only the actions in the student notification action set and excludes the student's own `message_posted` events. It displays plain-language labels and timestamps, never audit details; email events and staff-only actions are excluded. “Last updated” is the latest timestamp from that same set, rather than the ticket's general `updated_at` value.
- The student ticket page also shows the current office/unit and status guidance: waiting for SDS review, waiting for the student's reply, awaiting office acknowledgment, in handling, escalated, referred, resolved response window, or the closure result. A forwarded informational ticket uses the “Forwarded for information” label and receiving office/date. These are status descriptions, not staff deadlines. Display dates use `config('app.timezone')`.

### Attachments

- Complaint uploads are kept on the private `local` disk. Conversation attachments accept one file per message, with the same extension list and 10 MB limit; a message may contain text, a file, or both, but not neither.
- Ticket messages allow up to 5,000 characters.
- The attachment controller checks access to the associated complaint before serving a file. PDF and supported image files can be previewed; other files are downloaded. Responses are private/no-store, and content sniffing is disabled for previews.
- The reader also checks the `public` disk for older attachments created before private storage was used.
- Profile avatars are separate from complaint/message attachments and are intentionally served from the public disk.

## 5. Ticket classifications and lifecycle

### Classification and jurisdiction

During SDS review, a ticket may be classified as:

- `needs_resolution`: a concern requiring handling and a resolution.
- `informational`: a concern recorded or shared for information.
- `invalid`: used when closing a ticket during review as invalid, duplicate, out of scope, or no response.
- Unclassified: the ticket has not yet been classified.

Jurisdiction is `sds` or `recipient`. Classification does not itself assign a recipient. SDS may handle a ticket directly, assign it to a recipient, retain an informational item, or forward an informational item.

When SDS forwards an informational ticket without closing it, it remains stored as `submitted`, with `classification=informational`, `jurisdiction=recipient`, the receiving recipient ID in `forwarded_to`, and the forwarding time in `forwarded_at`. Students and the receiving recipient see “Forwarded for information,” the receiving office, and the date; on the recipient page the notice says no reply or action is needed. This ticket is not in the SDS awaiting-review queue and does not receive a Submitted reminder. The stored status, analytics status counts, and status filters remain Submitted. SDS retains the decision to close it. Retained and already-closed informational tickets keep their existing Closed display.

Before classification, an administrator can correct the category and suggested recipient. An initial recipient assignment must be configured for that category through its assigned recipient, suggested recipients, escalation hierarchy, or the student's suggested recipient.

Referral destinations are stored on `Ticket.referred_to` as text. Tickets also store an `is_codi_referral` flag. The listed Committee on Decorum and Investigation destination, or an "Other" entry matching its full name or the acronym CODI after case/space/punctuation normalization, is stored under the canonical full name and flagged as CODI. The backfill migration applies the same matching to existing referral names. Other partial or unrelated names do not receive the flag.

For the `Complaint Against a Student` category, the configured CBME and CICT escalation paths are Program Chair followed by College Dean; the Campus Director is not a level in these paths. A complaint about student misconduct starts with the SDS Coordinator or the student's teacher, adviser, or professor. Any referral to the Campus Disciplinary Committee requires SDS to confirm that the College Dean assessed and endorsed the matter, regardless of category. The confirmation is validated server-side and recorded in the referral audit entry. Existing escalated tickets keep their current handler when the paths are updated.

Informal settlement, parent or guardian notification, hearings, and sanctions happen outside SorSUpport. The system records referrals and their outcomes; it does not conduct those procedures.

### Status definitions

| Status | Meaning |
|---|---|
| `submitted` | New complaint waiting for SDS review, or returned to the review queue after the student provides clarification. |
| `needs_clarification` | SDS asked the student for more information. |
| `assigned` | A handler has been chosen but has not acknowledged the assignment. |
| `in_progress` | The SDS handler or recipient has acknowledged the ticket and is working on it. |
| `escalated` | SDS moved the ticket to another configured handler level. |
| `referred` | SDS sent the case to an external committee/board and is waiting to record the outcome. |
| `resolved` | A resolution or committee outcome was recorded; the student may accept it or ask for further action. |
| `closed` | Ticket is finished, withdrawn, informationally retained/forwarded, invalid, or closed for another recorded reason. |

The complaint's `status` is synchronized with its ticket's status. Ticket status changes are also guarded by the model transition map; illegal transitions raise a domain exception.

### Legal state transitions

The following is the model-level transition map. The “actor/action rules” below further restrict who may request those changes.

| Current status | Permitted next status |
|---|---|
| Submitted | Needs Clarification, Assigned, In Progress, Closed |
| Needs Clarification | Submitted, Assigned, In Progress, Closed |
| Assigned | In Progress, Escalated, Referred, Closed |
| In Progress | Assigned, Escalated, Referred, Resolved, Closed |
| Escalated | Assigned, Referred, Resolved, Closed |
| Referred | Resolved, Closed |
| Resolved | In Progress, Closed |
| Closed | None |

### Actor/action rules

`TicketWorkflow` is the authoritative authorization matrix for ticket actions. A controller must authorize the action against the current user, status, and (where relevant) ownership or handler assignment.

| Action | Who may do it | From status | Result |
|---|---|---|---|
| Request clarification | SDS admin | Submitted | Needs Clarification; records request time and posts the question to the thread. |
| Provide clarification | Submitting student | Needs Clarification | Submitted; student's reply is already in the thread and SDS admins are notified. |
| Handle directly | SDS admin | Submitted or Needs Clarification | In Progress; classification becomes needs-resolution, jurisdiction SDS, handler is that admin, and acknowledgment is recorded. |
| Assign/reassign | SDS admin | Submitted, Needs Clarification, Assigned, In Progress, or Escalated | Assigned; sets current handler and clears acknowledgment. Without a recipient, handler is the admin and `assigned_to` is null. |
| Acknowledge | Current handler (SDS admin or assigned recipient) | Assigned | In Progress; sets acknowledgment timestamp and current handler. |
| Return to SDS | Current assigned recipient | Assigned, In Progress, or Escalated | Assigned; current handler becomes an active SDS admin, `assigned_to` is cleared, SDS admins are notified in-app and by email, and the recipient-provided 10–500 character reason is shown to SDS only. No separate student email is sent; the recipient loses ticket access. |
| Escalate | SDS admin | Assigned, In Progress, or Escalated | Escalated; changes handler to selected active recipient and updates escalation time. |
| Refer | SDS admin | Assigned, In Progress, or Escalated | Referred; stores the committee/board text, a CODI flag for the listed CODI destination only, and referral time. Campus Disciplinary Committee referrals additionally require confirmation of College Dean assessment and endorsement; this is recorded in the audit entry. |
| Record referral outcome | SDS admin | Referred | Resolved; records resolution type/time and posts the outcome. |
| Resolve | Current handler | In Progress or Escalated | Resolved; stores resolution type/time and optionally posts a resolution message. |
| Reopen resolved ticket | SDS admin | Resolved | In Progress; clears resolution type/time and closure timestamp. |
| Request further action | Submitting student | Resolved, within the configured response window | In Progress; clears resolution type/time, posts the student's reason, and notifies handler/SDS. |
| Accept resolution | Submitting student | Resolved | Closed with `resolved_accepted` closure and closed time. |
| Close | SDS admin | Any non-closed status in the workflow matrix | Closed with closure type/time. Invalid, duplicate, out-of-scope, and no-response closures require a 10–1,000 character student-facing explanation; resolved and informational closures do not. Review queue actions have additional restrictions described below. |
| Withdraw | Submitting student | Submitted, Needs Clarification, Assigned, In Progress, or Escalated | Closed with `withdrawn` closure and optional reason. Not available after referral or once resolved. |
| Request withdrawal | Submitting student | Referred | Remains Referred; one request per referral period. Requires a reason (maximum 1,000 characters), posts it to the conversation, records `withdrawal_requested`, and notifies SDS. |
| Request update | Submitting student | Submitted or Needs Clarification | Status, handler, and conversation remain unchanged. Optional note (maximum 500 characters); one request per status period. Records `update_requested` and notifies SDS admins. |
| Request escalation | Submitting student | Assigned, In Progress, or Escalated | Status and handler remain unchanged. Requires a 10–1,000 character reason; only one request may be pending. Records `escalation_requested` and notifies SDS admins, not the current recipient handler. |

Additional constraints:

- A handler must be an SDS admin or recipient and match `current_handler_id` or `assigned_to`; the student must own the complaint.
- A student can request further action through the end of the deadline (`resolved_at` plus configured calendar days). An expired request is denied.
- A student cannot withdraw a referred ticket directly. They can request withdrawal with a reason; the ticket remains Referred while SDS considers the request.
- Only one withdrawal request is accepted during a Referred period. Once sent, the request button is hidden and the student sees a confirmation; a duplicate submission is rejected without another message, audit entry, or email. A later referral after the ticket leaves Referred starts a new period and permits another request.
- A student may request one update during each Submitted or Needs Clarification status period. The request does not open or post to the conversation, change status/handler, or reset reminders. A status change ends that period.
- An escalation request remains pending until SDS escalates or reassigns the ticket, the ticket is resolved or closed, or an SDS admin replies in the conversation. While pending, the student sees confirmation and cannot submit another request. The request itself does not change status/handler or reset reminders.
- Referral to the Campus Disciplinary Committee is blocked unless SDS confirms that the College Dean assessed and endorsed the matter. This confirmation is required for every category and is not required for CODI, the University Investigation and Disciplinary Committee, or an "Other" destination.
- For gender-based sexual harassment, withdrawing at any stage does not stop CODI from continuing its investigation (Handbook p. 58). For student discipline, the University may initiate proceedings even if the aggrieved student does not want to file (Handbook p. 46). SDS may record the referral outcome or close the ticket as withdrawn using its existing actions; no automatic closure occurs.
- Withdrawal-request notifications to SDS do not identify the student or include the reason in the notification. The reason is posted in the ticket conversation and uses the existing hidden-identity display rules.
- Update and escalation requests notify active SDS admins by in-app ticket activity and email only. Emails do not include the request note/reason or student identity; sensitive-category email subjects/details use “Confidential concern.” Request details are kept in SDS-only audit activity and the pending-request panel, not the shared conversation, so the assigned handler cannot see them.
- In the admin review queue, `reject` only applies to awaiting-review tickets and classifies them invalid. It accepts invalid, duplicate, out-of-scope, and no-response closure types, each requiring a 10–1,000 character student-facing explanation. Generic `close` is blocked for awaiting-review tickets; they use review actions instead.
- For resolved tickets, admin close records `resolved` (SDS-confirmed), with no explanation required. For other non-review tickets, closure type is one of invalid, duplicate, out-of-scope, no-response, or withdrawn. Invalid, duplicate, out-of-scope, and no-response require a 10–1,000 character explanation; withdrawn is optional. Informational closure also does not require an explanation.
- Recipients can only resolve, never close. The student accepts or SDS closes after resolution.
- Resolution types are corrective action taken, explanation/information provided, settled between parties, committee decision, or no basis found after review.
- An informational ticket may be retained by SDS and closed (`informational` closure), forwarded once to a recipient while remaining submitted, or forwarded and closed. The information-only recipient is told no reply/action is needed.
- A ticket's conversation accepts new messages in Needs Clarification, Assigned, In Progress, Escalated, and Referred. It is locked in Resolved and Closed. A resolved ticket reopened or returned to progress becomes messageable again.

### Escalation

- Escalations are configured per category as one or more named paths, each with ordered levels; multiple recipients can share a level.
- Only active recipients in the category's escalation hierarchy can be selected. The current handler is omitted from available targets.
- The suggested next target is the next higher level along the current handler's path, otherwise the first available option from the first path.
- The administrator must select the target and enter a reason (maximum 1,000 characters). The system does not trigger escalation based on time or reminders.
- Every escalation changes assigned/current handler, preserves the `escalated` status on repeat escalation, updates `escalated_at`, logs the reason, may flag a conflict of interest, and queues emails for the acting admin, target recipient, and student.
- A student may ask SDS to consider escalation while the ticket is Assigned, In Progress, or Escalated, with a 10–1,000 character reason. This request is advisory only: escalation remains a manual SDS administrator decision. It does not change the ticket or handler, is not shown to or emailed to the current handler, and stays pending until SDS escalates/reassigns, resolves/closes, or replies in the conversation. The student's reason is visible to SDS only in the audit and pending-request panel.
- A returned ticket remains marked “Returned by office” in Needs attention until an SDS admin performs a ticket action. Return, reassignment, escalation, and closure are manual decisions; the return does not itself trigger any further action or notify the student.

### Needs attention

- The SDS admin dashboard shows the count and up to ten tickets that may need attention; `GET /admin/tickets/needs-attention` provides the full, searchable, filterable, paginated list. The page is display-only: opening or filtering it does not change tickets, statuses, handlers, or reminders.
- A ticket appears when it has a pending `update_requested` or `escalation_requested` event, or a `returned_to_sds` event not yet followed by an SDS-admin ticket action, according to `TicketWorkflow`'s pending-request helpers; when its current status has reached the existing reminder system's working-day threshold without a non-email action; or when its current handler or assignee is inactive. Resolved and Closed tickets are excluded.
- No-action thresholds use the existing `TicketReminderService` status thresholds, `SystemSetting` values, working-day counter, and `Ticket::lastActionAt()` behavior, including the sensitive Submitted threshold and the configured referral rule. The thresholds are applied even if reminder emails are disabled; no additional thresholds or settings are introduced.
- Rows are ordered with pending student requests first, then oldest tickets first. They show the public-safe subject, filer name as `Anonymous` when identity is hidden, handler/office, reason badges, status, and age. Sensitive subjects use `Confidential concern`. No-action wording is informational, not a deadline or handbook requirement; system-default thresholds remain adjustable under System Settings → Reminders. SDS decides manually what to do.

## 6. Conversation, unread state, and notifications

### Ticket conversation

- A ticket has one `TicketThread` and many `ThreadMessage` records. Messages store sender, content, and optional attachment path/name.
- The thread is created/opened when the ticket enters a messageable state and is synchronized with ticket status.
- Students can reply to their own ticket. Recipients can reply only to an assigned ticket (a recipient who only received an informational forward cannot reply through the handler reply action).
- If a student replies while the ticket is awaiting clarification, the reply is treated as providing clarification and returns the ticket to Submitted.
- A message posted by a student or recipient is audited and queues a message email to the other party/handler.

### In-app notification behavior

In-app ticket notifications are generated from relevant audit-log actions, not from a separate notification table. Student and recipient notification pages:

- Derive events from the user's own complaints or tickets.
- Let the user open a notification, mark selected notifications read, and delete selected notifications from their personal list.
- Track read/deleted IDs and per-ticket last-read timestamps separately for student, recipient, and administrator views.
- Exclude a user's own message from their unread count (except the complaint-submitted event).
- Ticket pages mark the corresponding ticket/events read when viewed. Admin read state is used for ticket badges; admins do not have the same separate notifications page in the route list.
- Role-specific audit action sets determine which events count as changes for each role.

### Email notification behavior

Email intent is stored as `EmailNotification` rows with `pending`, `sent`, or `failed` status. The scheduled sender processes pending rows whose retry time is due, in ID order.

The message composer creates audience-aware content and safe subject/details for:

- Submission acknowledgment and review queue assignment.
- Recipient assignment and informational forwarding.
- Clarification requested/provided.
- Status changes, acknowledgment, resolution, closure, invalid closure.
- Escalation, committee referral, referral outcome.
- Messages, further-action request, accepted resolution, withdrawal.
- Waiting-ticket reminders and legacy daily-reminder type.

Emails include an action URL for the student, recipient, or admin complaint page. Recipient wording varies by audience. For every email type and audience, sensitive tickets use the public subject (“Confidential concern”) everywhere; the real subject, description, and personnel-involved text are never included in an email. Authorized users can still view the full complaint on its ticket page after signing in. Student emails include closure explanations for invalid, duplicate, out-of-scope, and no-response closures; closure reasons are not sent to handlers as student explanations.

Delivery rules:

- A successful send increments attempts, marks sent, clears retry time, and creates an `email_notification_sent` audit entry.
- Failures are logged and retried after 1, 5, 15, and 60 minutes; at five attempts the row becomes failed and a system audit entry instructs SDS to contact the person another way.
- Waiting reminders receive high priority headers.
- A blank recipient email is marked failed without a send attempt.

## 7. Waiting-ticket reminders

`TicketReminderService` checks Submitted, Needs Clarification, Assigned, In Progress, Escalated, Referred, and Resolved tickets. Forwarded informational tickets still stored as Submitted are exempt from the Submitted reminder. Reminders are not sent for closed tickets and never modify status or assignment.

| Ticket state | Reminder recipient | Default threshold | Counted as |
|---|---|---:|---|
| Submitted, not reviewed | Active SDS admin(s) | 2 | Working days |
| Submitted in sensitive category | Active SDS admin(s) | 1 | Working day |
| Needs Clarification, no student reply | Student; active SDS admin(s) once at twice the threshold | 5; SDS notice at 10 | Working days |
| Assigned, not acknowledged | Active current handler/assignee | 2 | Working days |
| In Progress or Escalated with no action | Active handler | 5 | Working days |
| CODI referral, no outcome | Active SDS admin(s) | 10 | Working days |
| Other referral, no outcome | Active SDS admin(s) | Off by default; adjustable system default | Working days |
| Resolved, no student response | Active SDS admin(s) | 15 | Calendar days |

Reminders are operational nudges to the person who must act next. They are not handbook deadlines and never change a ticket. The handbook states no deadline for ordinary complaints; the only timelines it states are for CODI harassment cases and disciplinary appeals.

- The 1-working-day sensitive-category reminder is a system default, derived from the CODI 24-hour notice timeline (Handbook p. 58). It is adjustable and is not a handbook deadline for SDS.
- The CODI referral reminder uses the 10-working-day decision period stated for CODI (Handbook pp. 57 to 58); it is only sent for referrals flagged CODI.
- The generic reminder for other referrals is a system default, not a handbook deadline. It is off by default; SDS can enable it and adjust its 1–30 working-day threshold.
- The 15-day resolved-student-response reminder is a system default, adjustable by SDS.
- Needs Clarification reminders are a system default, not a handbook deadline. SDS can adjust the student reminder interval from 1–30 working days. The reminder repeats at that interval; active SDS admins receive one notice at twice the interval for each wait period, suggesting they may follow up or close the ticket as no response. No ticket is closed automatically.
- All other reminder thresholds are system defaults.

- Thresholds and enable/disable are editable by the SDS admin in settings. Each threshold must be 1–30 days.
- Settings are stored as `SystemSetting` key/value rows and override `config/tickets.php`. Missing values use config defaults; day counts are clamped to at least one.
- Working-day counting excludes Saturday and Sunday. It does not apply a holiday calendar.
- Regular waiting reminders repeat after the same threshold since the last reminder, measured from the latest non-email ticket action. Email-sent/failed audit events do not reset action time.
- Needs Clarification waiting starts from the later of the clarification request and the latest non-email ticket action. The student reminder is normal priority, repeats after each threshold, and stops when the ticket leaves Needs Clarification. At twice the threshold, SDS admins receive one notice per wait period. These messages contain only the ticket reference and public-safe subject; sensitive descriptions and personnel-involved text are not included.
- The resolved-student-response reminder is sent once after the deadline; it tells SDS the student can still accept until that deadline.
- The scheduled check runs hourly on weekdays between 08:00 and 17:00.

## 8. SDS configuration and administration

### Categories and routing data

The SDS administrator can create/update/activate/deactivate/delete complaint categories. Each category has a name, optional description, optional default recipient, active flag, sensitivity flag, hidden-identity flag, suggested recipients, and escalation paths.

- New categories are active.
- Only active categories appear in the student submission form.
- Configured recipients for suggestions/escalation must be active.
- Category updates preserve sensitivity/hidden-identity options when those controls are omitted; sensitive categories always allow hidden identity.
- Each escalation path has a name and ordered recipient levels. A recipient cannot appear more than once in a path. Only active recipients already associated with the category can be added.
- Escalation recipients are automatically included in the category's suggested recipients.

### Colleges, offices, programs, designations

System Settings manages units of type college or office.

- Names are unique; descriptions are optional, maximum 2,000 characters.
- Colleges require at least one program; program names are distinct per submitted set, year level 1–6, optional block 1–10, and optional description up to 2,000 characters.
- Either unit type may have designations, with optional descriptions up to 2,000 characters.
- Unit updates replace the unit's program/designation rows from submitted form data. Deletion behavior follows database foreign-key rules.
- Student account setup references configured colleges and their programs. Recipient accounts reference a configured college or office.

### Audit trail

The admin audit page lists newest events with 15 per page. It supports free-text search across action/details, performer name (excluding anonymous complaint performers), ticket ID, and complaint reference; account-type/system filtering; date-from/date-to filtering; and action list selection.

Audited activity includes ticket lifecycle events, assignments, messages, classification, escalation, conflict flags, closure, student rating, account changes, category/unit/settings changes, profile/password actions, imports, and email delivery/failure. Ticket actions have a ticket ID; standalone account/configuration events may have no ticket. Email delivery audit rows identify the subject and intended recipient, with anonymous student email addresses masked.

## 9. Analytics and exports

Only the SDS admin analytics routes are exposed. Filters include date range, category, classification, status, unit, recipient/handler, period, and year. Date ranges require `end_date` to be on or after `start_date`. Period choices are all time, this month, last month, last three months, this year, or custom range.

Dashboard and report metrics include:

- Complaint and ticket totals, status counts, ticket volume series by day/week/month, and category distribution.
- Resolution rate for `needs_resolution` tickets: resolved or closed count divided by all needs-resolution tickets. Informational and invalid tickets are excluded from that denominator.
- Average resolution time from complaint submission to `resolved_at`, overall and by category.
- Escalation event count, unique tickets escalated, escalation occurrence frequency, count by category, and escalation-level breakdown.
- Recipient/handler workload and performance rows, active/open tickets, oldest open tickets, and unit-level rollups.
- Classification counts for needs-resolution, informational, invalid, and unclassified; identified vs anonymous submissions; college/program origin (anonymous students appear as “Not disclosed”).
- Resolution-type and closure-reason breakdowns and satisfaction-rating count, average, and 1–5 distribution.
- Subject/insight summaries for the selected period.

Complaint date/category filters apply through the complaint relation. Ticket filters additionally apply status, classification, handler user ID, and handler unit. Resolution timing is based on the complaint's creation time through ticket `resolved_at`; it is not business-day timing.

PDF export can include selected sections: summary, category, resolution rate/time, escalation by month, status, college, program, resolution type, closure reason, escalation levels, and satisfaction. At least one section must be selected. A direct export with no `sections` parameter includes all sections. Excel export uses the report data and exports the available report tables. Filenames include the current timestamp.

## 10. Route and page map

The route definitions are in `routes/web.php` and `routes/auth.php`. Named routes are shown in parentheses where useful.

### Public and shared

| Path | Behavior |
|---|---|
| `/` | Redirects to login. |
| `/dashboard` | Authenticated role-based redirect to admin, student, or recipient dashboard. |
| `/attachments/complaints/{complaint}/{index}` | Permission-checked complaint attachment preview/download. |
| `/attachments/messages/{message}` | Permission-checked conversation attachment preview/download. |
| `/force-password` | Show/save initial password change. |
| `/profile`, `/profile/photo` | Shared profile edit/update/photo; `DELETE /profile` deletes the signed-in account after current-password confirmation. |

### Authentication (`routes/auth.php`)

Guest routes: `GET/POST /login`, `GET/POST /forgot-password`, `GET /password-email-sent`, `GET /reset-password/{token}`, and `POST /reset-password`.

Authenticated routes: `GET /verify-email`, throttled `POST /email/verification-notification`, `GET/POST /confirm-password`, `PUT /password`, `POST /password/check-current`, and `GET/POST /logout`. `GET /verify-email/{id}/{hash}` requires a valid signature and throttle.

### SDS administrator (`/admin`, role `sds_admin`)

| Path | Function |
|---|---|
| `GET /admin/dashboard` | Admin overview, including the Needs attention count and up to ten matching tickets. |
| `GET/POST /admin/accounts` | List/search/filter accounts and create account. |
| `GET /admin/accounts/{user}/edit` | Redirects to account list. |
| `PUT /admin/accounts/{user}` | Update account. |
| `PATCH /admin/accounts/{user}/deactivate` and `/reactivate` | Change account access. |
| `GET/POST /admin/accounts/upload` | Show/import bulk accounts. |
| `GET /admin/accounts/upload/errors` | Download the last import's CSV row errors. |
| `POST /admin/categories`, `PUT /admin/categories/{category}` | Create/update category. |
| `PUT /admin/categories/{category}/escalation` | Replace category escalation paths. |
| `PATCH /admin/categories/{category}/toggle-status`, `DELETE /admin/categories/{category}` | Activate/deactivate/delete category. |
| `POST /admin/units`, `PUT /admin/units/{unit}`, `DELETE /admin/units/{unit}` | Configure colleges/offices and related records. |
| `GET /admin/complaints`, `GET /admin/complaints/{complaint}` | Complaint queue/detail. |
| `POST /admin/complaints/{complaint}/reply` | Admin reply route. |
| `GET /admin/tickets/review`, `POST /admin/tickets/{ticket}/read` | Review queue and mark read. |
| `GET /admin/tickets/needs-attention` | Read-only, searchable, filterable and paginated list of tickets that may need SDS attention. |
| `GET /admin/tickets/my` | Admin's own handled tickets. |
| `PATCH /admin/tickets/{ticket}/details` | Update category/suggested recipient before classification. |
| `POST /admin/tickets/{ticket}/reject`, `/classify`, `/forward`, `/retain-informational`, `/forward-informational-close`, `/assign`, `/acknowledge`, `/clarification`, `/refer`, `/outcome`, `/resolve`, `/not-yet-resolved`, `/close`, `/escalate` | Review and lifecycle actions described above. |
| `GET /admin/analytics`, `GET /admin/analytics/export/pdf`, `GET /admin/analytics/export/excel` | Analytics dashboard and report exports. |
| `GET /admin/audit` | Searchable audit trail. |
| `GET /admin/settings`, `PUT /admin/settings/reminders` | Configuration page and reminder settings update. |

### Student (`/student`, role `student`)

| Path | Function |
|---|---|
| `GET /student/dashboard` | Student overview. |
| `GET /student/notifications` | In-app notifications. |
| `POST /student/notifications/read-all`, `/delete-selected`, `/{notificationId}/open` | Read/delete/open selected notifications. |
| `GET /student/profile`, `POST /student/profile/photo` | Student profile view and photo update. |
| `GET /student/complaints`, `GET /student/complaints/create`, `POST /student/complaints` | Own complaints list, submission form, and submit action. List supports status/category/search filters and pagination. |
| `GET /student/complaints/{complaint}/submitted`, `GET /student/complaints/{complaint}` | Submission confirmation and own complaint detail. |
| `POST /student/complaints/{complaint}/reply`, `/accept-resolution`, `/further-action`, `/withdraw`, `/rating` | Conversation and student lifecycle/feedback actions. |

### Recipient (`/recipient`, role `recipient`)

| Path | Function |
|---|---|
| `GET /recipient/dashboard` | Recipient overview. |
| `GET /recipient/notifications` | In-app notifications. |
| `POST /recipient/notifications/read-all`, `/delete-selected`, `/{notificationId}/open` | Read/delete/open notifications. |
| `GET /recipient/profile`, `POST /recipient/profile/photo` | Recipient profile view and photo update. |
| `GET /recipient/tickets`, `GET /recipient/tickets/{complaint}` | Legacy aliases for the recipient complaint list/detail. |
| `GET /recipient/complaints`, `GET /recipient/complaints/{complaint}` | Assigned/forwarded ticket list/detail; list supports search/status/sort and pagination. |
| `POST /recipient/complaints/{complaint}/reply`, `/acknowledge`, `/return-to-sds` | Reply, acknowledge, or return an assigned ticket to SDS with a staff-only reason. |
| `PATCH /recipient/complaints/{complaint}/status` | Resolve an assigned ticket only. |

### Local/testing prototype routes

`/prototype/student`, `/prototype/admin`, and `/prototype/recipient` exist only in `local` and `testing` environments. These routes create or modify prototype records and log in as the corresponding role; they are not production onboarding routes.

## 11. Data model reference

The primary records and their responsibilities:

| Model/table | Responsibility |
|---|---|
| `User` | Credentials, role, activity/verification/password flags, name, avatar, and per-role read/deleted/unread tracking arrays. |
| `Student` | Student ID, college, program, year, block, linked to one user. |
| `Recipient` | Staff ID, unit, designation, linked to one user. |
| `Unit` | Configured college or office. |
| `Program` | Program offered by a college, with year/block and description data. |
| `UnitDesignation` | Staff designation for a unit. |
| `ComplaintCategory` | Category, active/sensitive/hidden-identity settings, primary recipient, suggested recipients, escalation paths. |
| `Complaint` | Student-submitted content, reference, category, suggested recipient, attachment metadata, anonymity, declaration time, mirrored status. |
| `Ticket` | Lifecycle, classification/jurisdiction, handler/assignee, timestamps, closure/resolution/referral data, and student rating. One ticket per complaint. |
| `TicketThread` / `ThreadMessage` | Ticket conversation and text/file messages. |
| `EscalationHierarchy` | Category path number/name, level, and recipient. |
| `AuditLog` | Ticket events and standalone system/account/configuration events. |
| `EmailNotification` | Email recipient, type, delivery status, retry count/timing, sent time. |
| `SystemSetting` | Admin-overridable key/value settings, including reminders. |
| `AnalyticsReport` | Report generator/metadata record. |

Key `Ticket` computed behavior includes status/resolution/closure display labels, status-transition checks, awaiting-review/active/conversation state predicates, whole weekdays open, last non-email action time, further-action deadline, and rating eligibility. `Complaint` provides attachment metadata/URLs, sensitive public subject, anonymous display name, submitter ownership, conflict-name matching, and reference-number generation.

## 12. Service and controller responsibilities

| Component | Responsibility |
|---|---|
| `TicketWorkflow` | Central action constants, actor/status rules, authorization, currently available actions, and transactional status-changing actions. It writes audit events/messages and queues email rows. |
| `TicketAccess` | Determines whether a user may view a complaint/ticket and whether a recipient is assigned or forwarded to. |
| `TicketEscalationService` | Builds active category escalation options/targets, suggests a next handler, performs and audits escalation, and queues notification rows. |
| `TicketReminderService` | Resolves persisted/default settings, saves settings, checks waiting thresholds, finds reminder recipients, and counts working days. |
| `EmailNotificationService` | Sends due pending emails, resolves templates, tracks delivery, retries failed sends, and audits success/final failure. |
| `TicketEmailComposer` | Composes email subject, heading, body, details, audience, and action URL with privacy-aware wording. |
| `TicketUnreadService` | Derives unread counts from role-relevant audit events; tracks last-read/read/deleted notification identifiers and marks ticket events viewed. |
| `AnalyticsService` | Applies date/category/status/classification/unit/recipient filters and computes dashboard/report metrics, breakdowns, timeseries, and report data. |
| `AccountsImport` | Normalizes spreadsheet rows, validates, upserts role profiles, detects duplicates, and reports row-level errors. |
| `AnalyticsReportExport` | Converts report data into a headed Excel worksheet. |
| `AdminTicketReviewController` | Admin review queue, ticket filters and lifecycle actions; delegates state changes to workflow/escalation services. |
| `Student\ComplaintController` | Student submission, own-ticket access, conversation, resolution response, withdrawal, and satisfaction rating. |
| `Recipient\ComplaintController` | Recipient assignment/forward visibility, conversation, acknowledgment, and resolution. |
| `AttachmentController` | Permission-checked attachment streaming and preview. |
| `AccountManagementController`, `ComplaintCategoryController`, `UnitController`, `ReminderSettingsController` | SDS account, routing, organizational, and reminder configuration. |
| `AnalyticsController` | Filter validation, analytics page composition, and PDF/Excel export. |

Supporting controllers include authentication/password/verification, student and recipient dashboards/notification pages, profile management, admin dashboard, and admin complaint queue/detail.

## 13. Scheduled tasks and operations

`routes/console.php` registers:

| Command | Behavior | Schedule |
|---|---|---|
| `php artisan notifications:send` | Sends all pending email notification rows that are due for first send/retry. | Every minute; without overlap. |
| `php artisan tickets:send-reminders` | Creates pending reminder emails for tickets past their configured wait threshold. | Every hour, weekdays, 08:00–17:00; without overlap. |
| `php artisan mail:check` | Checks Brevo API credentials when the mailer is Brevo; otherwise starts/stops the configured mail transport to test connection. | Manual/deployment health check. |

The deployed startup script runs database migrations and starts a queue worker and scheduler. The app's ticket email workflow itself uses persisted `EmailNotification` records and the scheduled sender command. For local reminders/email, run the scheduler (for example `php artisan schedule:work`) and configure mail credentials. See the [README local setup](../README.md#running-it-locally) for required runtime setup and environment variables.

Important configuration:

- `TICKET_SUBMISSIONS_PER_HOUR`: student submission rate limit (default 5).
- `config/tickets.php`: reminder defaults and enable flag.
- Reminder settings saved in `SystemSetting` take precedence over config.
- Laravel mail configuration and `BREVO_API_KEY` determine delivery; `MAIL_MAILER=log` is available for local inspection.
- Database and app deployment details are described in the README and Docker files.

## 14. User-facing pages and shared UI

Blade views under `resources/views` provide:

- Role dashboards; shared app shell/sidebar/tab navigation; status badges and ticket cards.
- Student complaint submission/list/detail/confirmation pages and recipient complaint list/detail pages.
- Admin review, account, category, settings, audit, and analytics pages.
- Shared ticket thread, ticket action/escalation forms, admin ticket view, ticket info panel, and chart components.
- Authentication, email verification, password reset/change, and profile pages.

The frontend is built with Vite. JavaScript entry points are `resources/js/app.js` and `resources/js/bootstrap.js`; styles are in `resources/css/app.css`.

## 15. Automated test coverage map

Feature and unit tests currently cover authentication/password setup/verification/reset, profiles, account administration and bulk import, category/escalation and unit settings, admin review/routing/escalation, confidentiality/visibility, ticket workflow/lifecycle/communication, email content/delivery/reminders, unread badges, audit trail, analytics/exports, submission behavior, paging, and handbook category alignment. Run the suite with `php artisan test`.

## 16. Source of truth and maintenance

For behavior changes, update this document alongside the implementation. Source locations that define key rules:

- Routes: `routes/web.php`, `routes/auth.php`, `routes/console.php`.
- Statuses and transition guard: `app/Models/Ticket.php`.
- Actor/action matrix and state transitions: `app/Services/TicketWorkflow.php`.
- Visibility and file access: `app/Services/TicketAccess.php`, `app/Http/Controllers/AttachmentController.php`.
- Submission validation and identity handling: `app/Http/Controllers/Student/ComplaintController.php`, `app/Models/Complaint.php`.
- Escalation: `app/Services/TicketEscalationService.php` and `app/Http/Controllers/Admin/AdminTicketReviewController.php`.
- Email composition/delivery and reminders: `app/Services/TicketEmailComposer.php`, `app/Services/EmailNotificationService.php`, `app/Services/TicketReminderService.php`.
- Analytics: `app/Services/AnalyticsService.php` and `app/Http/Controllers/Admin/AnalyticsController.php`.
- Default ticket settings: `config/tickets.php`.
- Database structure: `database/migrations`.
