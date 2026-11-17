# 5.2 Features and Functionalities

This section describes the main features and user interfaces of SorSUpport. The system supports three user roles: the Student, the Recipient, and the SDS Administrator. The figures present related screens, selected validation messages, and resulting states to explain how users submit, review, handle, resolve, escalate, and monitor complaints at Sorsogon State University -- Bulan Campus. Student and Recipient interfaces are presented in mobile browser view, while SDS Administrator interfaces are presented in desktop browser view.

## Account Access

Account access is shared by the three user roles. The following mobile browser views show the normal forms, selected validation states, and email-based steps used to access an account.

Figure 5.1 shows the log-in interface and its validation feedback. Panel (a) presents the ID and password fields, while panel (b) presents the error displayed after an unsuccessful sign-in. Student and Recipient accounts are provisioned by the SDS Administrator rather than created through public registration. A successful sign-in directs the user to the account's role interface after any required verification and password setup.

**Figure 5.1: Log-in Page and Validation**

- (a) Log-in form — `fig/login.png`.
- (b) Invalid credentials — `fig/login-err.png`.

Figure 5.2 presents account email verification. Panel (a) shows the verification screen and resend option, while panel (b) shows the verification email. Opening a valid link verifies and activates the account. An account already activated by the SDS Administrator skips this verification step.

**Figure 5.2: Email Verification**

- (a) Verification screen — `fig/verify.png`.
- (b) Verification email — `fig/verify-mail.png`.

Figure 5.3 shows the initial password setup and its validation feedback. Panel (a) presents the personal-password and confirmation fields, while panel (b) presents a rejected entry. Students and Recipients initially use their ID as the provisioned password and must replace it with a personal password containing at least eight characters and different from the ID. The SDS Administrator completes any required initial password change through the profile interface.

**Figure 5.3: Initial Password Setup and Validation**

- (a) Password setup — `fig/pass.png`.
- (b) Password validation — `fig/pass-err.png`.

Figure 5.4 illustrates password recovery. Panel (a) shows the request form, where the user enters the account ID. Panel (b) shows the email containing the reset link sent to the registered address. Panel (c) shows the new-password form opened through a valid link. After the password is successfully reset, the user returns to the log-in page.

**Figure 5.4: Password Recovery**

- (a) Reset request — `fig/reset-a.png`.
- (b) Reset email — `fig/reset-mail.png`.
- (c) New password — `fig/reset-b.png`.

## Student Interface

SorSUpport adapts its interface to different screen sizes and can be accessed through a mobile browser without installing a separate mobile application. The Student and Recipient figures below use mobile browser views to illustrate this responsive layout. The SDS Administrator figures use desktop browser views.

The Student interface supports complaint submission, progress tracking, communication, and responses to ticket outcomes. Related screens and selected validation or confirmation states are grouped to show how the Student completes each task.

Figure 5.5 presents the Student homepage and ticket list. Panel (a) shows the summary counts and recently updated tickets, while panel (b) shows the list used to locate previously submitted concerns. The Student can open a ticket to review its progress and use the available search and filter controls to find a particular concern.

**Figure 5.5: Student Homepage and Ticket List**

- (a) Homepage — `fig/shome.png`.
- (b) Ticket list — `fig/slist.png`.

Figure 5.6 shows the Student's notifications and profile functions. Panel (a) presents notices associated with the Student's tickets. Opening a notice provides access to the relevant concern. Panel (b) presents the Student's account and academic information, together with the profile-photo control. Panel (c) presents the password-change form. The profile displays institutional account details; it does not provide unrestricted editing of those records.

**Figure 5.6: Student Notifications and Profile**

- (a) Notifications — `fig/snotice.png`.
- (b) Profile — `fig/sprofile.png`.
- (c) Password controls — `fig/spass.png`.

Figure 5.7 presents the complaint submission process. Panel (a) shows the complaint details, while panel (b) shows the supporting files, identity-hiding option where permitted, and declaration. The Student may attach up to ten supported files and must accept the declaration before submitting. Panel (c) shows the confirmation and generated reference number after a successful submission. Every submission first undergoes SDS review; suggesting a Recipient does not automatically assign the ticket.

**Figure 5.7: Complaint Submission and Confirmation**

- (a) Complaint details — `fig/form-a.png`.
- (b) Files and declaration — `fig/form-b.png`.
- (c) Submission confirmation — `fig/sent.png`.

Figure 5.8 shows submission validation and the optional identity-hiding setting. Panel (a) presents feedback for an incomplete submission. The Student corrects the identified input before the system creates a ticket. Panel (b) shows the identity option for a category that permits it. This option masks the account identity in relevant ticket displays, but names or identifying details entered in descriptions, messages, or attachments may still reveal the Student's identity.

**Figure 5.8: Submission Validation and Identity Option**

- (a) Validation feedback — `fig/form-err.png`.
- (b) Identity option — `fig/hide.png`.

Figure 5.9 shows the two mobile views of an active Student ticket. Panel (a) presents the complaint details and current progress, while panel (b) presents the in-ticket communication thread. The Student switches between these views to review the record and exchange messages or supporting files with authorized personnel. Message availability depends on the stage of the ticket.

**Figure 5.9: Student Ticket Details and Communication**

- (a) Ticket details — `fig/sdetail.png`.
- (b) In-ticket communication — `fig/sthread.png`.

Figure 5.10 presents the Student's response to a request for clarification. Panel (a) shows the SDS question while the ticket is displayed as In review. Panel (b) shows the information supplied by the Student. Providing the clarification returns the ticket to the submitted stage for another SDS assessment.

**Figure 5.10: Student Clarification Response**

- (a) Clarification request — `fig/sclar-a.png`.
- (b) Submitted response — `fig/sclar-b.png`.

Figure 5.11 presents the Student's options after a resolution. Panel (a) shows the resolution-response controls. Accepting the resolution closes the ticket. Panel (b) shows the reason supplied when requesting further action within the configured response period; a valid request returns the ticket to In Progress. Panel (c) shows the one-time rating available after an eligible resolution closure, with one to five stars and an optional comment. The rating screen represents the accepted or confirmed closure branch, rather than the immediate result of requesting further action.

**Figure 5.11: Resolution Response and Service Rating**

- (a) Resolution response — `fig/reply-a.png`.
- (b) Further-action request — `fig/reply-more.png`.
- (c) Service rating — `fig/reply-b.png`.

Figure 5.12 shows requests that the Student can send to the SDS Administrator. Panel (a) presents an update request while the ticket awaits review or clarification. Panel (b) presents an escalation request explaining the need for additional intervention. The requests are recorded for SDS attention. An escalation request does not itself transfer the ticket to another handler.

**Figure 5.12: Student Requests to SDS**

- (a) Update request — `fig/sreq-a.png`.
- (b) Escalation request — `fig/sreq-b.png`.

Figure 5.13 distinguishes direct withdrawal from a withdrawal request. Panel (a) presents the action used to close an eligible ticket as withdrawn before referral or resolution. Panel (b) presents the request used after referral. The latter is recorded for SDS review and does not directly close the referred ticket.

**Figure 5.13: Withdrawal and Withdrawal Request**

- (a) Direct withdrawal — `fig/withdraw-a.png`.
- (b) Withdrawal request — `fig/withdraw-b.png`.

## Recipient Interface

The Recipient interface supports personnel who receive tickets from the SDS Administrator. The following mobile browser figures cover the homepage, ticket list, notifications, profile, ticket details, communication, and available handling actions. Informational forwarding is presented separately from assignment for resolution.

Figure 5.14 presents the Recipient homepage and ticket list. Panel (a) summarizes the Recipient's assigned tickets and recent concerns. Panel (b) provides access to assigned tickets and informational tickets forwarded to that Recipient. Search and filtering help locate a concern before its details are opened.

**Figure 5.14: Recipient Homepage and Ticket List**

- (a) Homepage — `fig/rhome.png`.
- (b) Ticket list — `fig/rlist.png`.

Figure 5.15 shows the Recipient's notifications and profile functions. Panel (a) presents notices related to ticket events, from which the Recipient can open the associated concern and manage the read state. Panel (b) presents staff account information and the profile-photo control. Panel (c) presents the password-change form. Institutional account records remain subject to the account-management functions of the SDS Administrator.

**Figure 5.15: Recipient Notifications and Profile**

- (a) Notifications — `fig/rnotice.png`.
- (b) Profile — `fig/rprofile.png`.
- (c) Password controls — `fig/rpass.png`.

Figure 5.16 shows the Recipient's mobile ticket views. Panel (a) presents the complaint details and available handling actions, while panel (b) presents the in-ticket conversation. The current assigned Recipient can use the active thread to exchange messages and supporting files with authorized participants. Access to the ticket and its actions depends on the Recipient's relationship to the record and its current stage.

**Figure 5.16: Recipient Ticket Details and Communication**

- (a) Ticket details — `fig/rdetail.png`.
- (b) In-ticket communication — `fig/rthread.png`.

Figure 5.17 illustrates the Recipient's acknowledgment and return actions. Panel (a) shows an assigned ticket awaiting acknowledgment, while panel (b) shows its In Progress state after acknowledgment. Panel (c) shows the Return to SDS form. When reassessment is required or the concern falls outside the Recipient's authority, a reason is supplied and the ticket is returned to the SDS Office for further handling.

**Figure 5.17: Acknowledgment and Return to SDS**

- (a) Assigned ticket — `fig/handle-a.png`.
- (b) Acknowledged ticket — `fig/handle-ok.png`.
- (c) Return to SDS — `fig/handle-b.png`.

Figure 5.18 presents the resolution process and its validation. Panel (a) shows the form for selecting the resolution type and entering an optional message. Panel (b) shows the feedback when a required entry is missing. Panel (c) shows the ticket after successful resolution, with the conversation closed to new messages. The Student is notified and may subsequently accept the outcome or request further action. Marking a ticket Resolved is distinct from closing it.

**Figure 5.18: Recipient Resolution and Validation**

- (a) Resolution form — `fig/resolve-a.png`.
- (b) Validation feedback — `fig/resolve-err.png`.
- (c) Resolved ticket — `fig/resolve-b.png`.

Figure 5.19 presents an informational ticket forwarded to the Recipient. Panel (a) shows the ticket's informational notice and details, while panel (b) shows the corresponding email notification. Forwarding provides the concern for information and does not appoint the Recipient as a resolution handler or require acknowledgment and resolution.

**Figure 5.19: Informational Forwarding to a Recipient**

- (a) Informational ticket — `fig/info.png`.
- (b) Forwarding email — `fig/info-mail.png`.

## SDS Administrator Interface

The SDS Administrator oversees complaint review, classification, routing, escalation, referral, and monitoring. This role also maintains user accounts and system settings. The interfaces below follow the administrative workflow from initial review to reporting and configuration.

Figure 5.20 shows the SDS Administrator homepage. It summarizes ticket and account information and presents ticket volume, recent tickets, and recent activity. These items give the administrator an overview of the system and access to records requiring further examination. Detailed analysis is available through the separate Analytics page.

**Figure 5.20: SDS Administrator Homepage**

- (a) SDS dashboard with summaries, ticket volume, recent tickets, and activity — `fig/ahome.png`.

Figure 5.21 shows the ticket review queue used by the SDS Administrator to locate submissions for assessment. The administrator opens a ticket to examine the complaint details and supporting information before deciding how it should be handled. This review precedes classification and assignment.

**Figure 5.21: Ticket Review Queue**

- (a) Ticket review queue with representative submissions and filters — `fig/review.png`.

Figure 5.22 presents the validity review of a submission. Panel (a) shows the complaint details and review controls, through which the SDS Administrator can correct routing details, request clarification, or proceed to classification. Panel (b) shows the closure form for a submission that cannot proceed. The administrator selects the applicable reason, such as Invalid, Duplicate, Out of Scope, or No Response, and provides a written explanation.

**Figure 5.22: Validity Review**

- (a) Pending ticket showing review controls — `fig/valid-a.png`.
- (b) Closure panel showing reason and explanation fields — `fig/valid-b.png`.

Figure 5.23 shows the clarification exchange between the SDS Administrator and the Student. Panel (a) presents the request for additional details, while panel (b) presents the Student's response as displayed in the SDS Administrator's desktop ticket view. While clarification is pending, the interface displays the ticket as In review. Providing the requested clarification returns the ticket to the submitted stage for another SDS assessment.

**Figure 5.23: Clarification Request and Response**

- (a) SDS clarification question — `fig/clarify-a.png`.
- (b) Student response to the same clarification request — `fig/clarify-b.png`.

Figure 5.24 presents ticket classification and the handling of informational concerns. Panel (a) shows the choices Informational and Needs Resolution. Panel (b) shows the disposition options for an informational ticket. The SDS Administrator may retain it in SDS records and close it, forward it to an eligible Recipient for information, or forward it and close it. Informational tickets are presented with their applicable outcome labels rather than being treated as unresolved assignments.

**Figure 5.24: Classification and Informational Disposition**

- (a) Classification choices — `fig/class-a.png`.
- (b) Informational disposition controls — `fig/class-b.png`.

Figure 5.25 shows the routing of a ticket classified as Needs Resolution. Panel (a) presents the option for the SDS Administrator to handle the ticket directly, while panel (b) presents assignment to an eligible Recipient. Direct handling moves the ticket to In Progress; assignment places it in Assigned for acknowledgment. Recipient eligibility depends on category configuration, account activity, and exclusion of personnel named in the complaint. The Student's suggestion alone does not establish eligibility. Reassignment also requires the new handler to acknowledge the ticket.

**Figure 5.25: Direct Handling and Assignment**

- (a) Needs Resolution ticket with handling options — `fig/assign-a.png`.
- (b) Assignment panel listing eligible Recipients — `fig/assign-b.png`.

Figure 5.26 shows the manual escalation interface. The SDS Administrator selects an eligible Recipient from the configured escalation paths and provides the reason for transferring the concern to a higher level. When the current Recipient occupies a configured path level, the system offers eligible higher-level Recipients on that path. Escalation changes the ticket status to Escalated. Student requests and reminders support this decision but do not automatically escalate tickets.

**Figure 5.26: Manual Escalation**

- (a) Escalation panel showing eligible Recipients and the reason field — `fig/esc.png`.

Figure 5.27 presents referral to a body outside the system's regular handling workflow. Panel (a) shows the destination and explanation fields, including the Dean endorsement confirmation required for a Campus Disciplinary Committee referral. The referral places the ticket in Referred. Panel (b) shows the form used to record the reported outcome. Committee proceedings occur outside SorSUpport; recording their outcome changes the ticket to Resolved and locks the conversation. The SDS Administrator can subsequently return a resolved ticket to In Progress or confirm its resolution and close it.

**Figure 5.27: Referral and Outcome Recording**

- (a) Referral panel with destination, explanation, and applicable endorsement confirmation — `fig/refer-a.png`.
- (b) Outcome recording panel on a referred ticket — `fig/refer-b.png`.

Figure 5.28 shows the Needs Attention list, which brings together conditions requiring SDS follow-up. These include pending Student requests, tickets returned by a Recipient, and handling delays or inactive handlers. The administrator uses the list to identify the relevant ticket and decide what action is appropriate. Appearance in the list does not by itself change a ticket's status or assignment.

**Figure 5.28: Needs Attention List**

- (a) Needs Attention list with representative pending requests and returned tickets — `fig/attn.png`.

Figure 5.29 shows the Audit Trail page, where the SDS Administrator reviews recorded system activities. Entries identify significant events such as ticket actions, assignments, and account or configuration changes. Search and date filters help locate relevant records. The ticket interface also presents recent activity for the selected concern. Identity-hiding rules mask the Student's identity in the relevant audit displays.

**Figure 5.29: Audit Trail**

- (a) Audit Trail page with filters and sample ticket events — `fig/audit.png`.

Figure 5.30 shows the Overview tab of the Analytics page. Filters allow the SDS Administrator to examine the applicable period and ticket groups. The charts and summary measures describe ticket volume, category distribution, classification, status, and resolution-related results. This interface supports examination of complaint patterns beyond the summaries on the homepage.

**Figure 5.30: Analytics Overview**

- (a) Analytics Overview tab with active filters and visible charts — `fig/stats.png`.

Figure 5.31 shows the Recipient performance tab of Analytics. The interface presents handling measures associated with personnel, including assigned and resolved ticket counts and relevant response or handling times. The SDS Administrator can use the displayed measures to identify differences in workload and follow-up needs within the selected filters.

**Figure 5.31: Recipient Performance**

- (a) Recipient performance tab with metrics and staff table — `fig/perf.png`.

Figure 5.32 shows the Students and feedback tab. It presents Student-group breakdowns and satisfaction results derived from submitted service ratings. Tickets in sensitive categories are excluded from Student-attribute breakdowns while remaining included in applicable overall totals. The displayed feedback reflects ratings actually submitted by eligible Students.

**Figure 5.32: Students and Feedback**

- (a) Students and feedback tab with group summaries and satisfaction results — `fig/feedback.png`.

Figure 5.33 presents report generation from Analytics. Panel (a) shows the Generate Report dialog, where the SDS Administrator selects the report sections. Panel (b) shows the resulting PDF report. The report applies the selected filters and presents aggregate results and permitted performance information for documentation. Complaint narratives and the names of personnel involved in complaints are excluded from the report.

**Figure 5.33: Report Generation**

- (a) Generate Report dialog with selected sections — `fig/report-a.png`.
- (b) Readable upper portion of the generated PDF report — `fig/report-b.png`.

Figure 5.34 shows the Account Management page for Student and Recipient accounts. The SDS Administrator can create or update records and activate, reactivate, or deactivate accounts. Deactivation preserves existing account and ticket records. The system blocks deactivation of a Recipient with Assigned, In Progress, or Escalated tickets until those tickets are reassigned.

**Figure 5.34: Account Management**

- (a) Account Management page with Student and Recipient records — `fig/users.png`.

Figure 5.35 presents bulk account management. Panel (a) shows the form for uploading a CSV or Excel file for the selected account type, while panel (b) shows the import summary and rejected-row report option. A Student upload is treated as an enrollment roster: eligible existing accounts are updated or reactivated, and missing active Students are deactivated only after a nonempty import without rejected rows. Recipient uploads update or create the supplied records without deactivating Recipients omitted from the file. Rejected rows are identified so that their information can be corrected.

**Figure 5.35: Bulk Account Upload**

- (a) Account upload form with account type and selected file — `fig/upload-a.png`.
- (b) Upload summary showing counts and rejected-row report option — `fig/upload-b.png`.

Figure 5.36 shows the organizational records maintained by the SDS Administrator. Colleges, offices, programs, and designations provide the reference information used in account management. Maintaining their relationships supports consistent account details and validation of uploaded records.

**Figure 5.36: Organizational Settings**

- (a) Settings area for colleges, offices, programs, and designations — `fig/org.png`.

Figure 5.37 presents complaint category management. Panel (a) shows the configured categories, while panel (b) shows the form used to maintain a category's details, activity status, routing Recipients, and identity-hiding settings. These settings determine the choices available during submission and help establish eligible personnel for routing. Categories marked sensitive allow the Student to hide their identity.

**Figure 5.37: Complaint Category Settings**

- (a) Complaint category list — `fig/cat-a.png`.
- (b) Category form showing routing and identity settings — `fig/cat-b.png`.

Figure 5.38 shows the escalation path configured for a complaint category. The SDS Administrator arranges eligible Recipients into ordered levels, with more than one Recipient permitted at a level. These settings determine the higher-level personnel offered during manual escalation. Configuring a path does not automatically transfer tickets between its levels.

**Figure 5.38: Escalation Path Configuration**

- (a) One category escalation path with ordered levels and Recipients — `fig/path.png`.

Figure 5.39 presents the settings used to manage follow-up periods. Panel (a) shows the reminder controls and the Student's response period after resolution. Panel (b) shows the Working Calendar, where the administrator records non-working dates and special working dates. Reminder intervals use this calendar to count working days. Reminders identify pending responsibilities, including review, clarification, acknowledgment, inactivity, and applicable referral follow-up. They do not automatically change a ticket's status or handler.

**Figure 5.39: Reminder Settings and Working Calendar**

- (a) Reminder settings including the Student response period — `fig/remind-a.png`.
- (b) Working Calendar with sample date overrides — `fig/remind-b.png`.

## Email Notifications

Email messages complement the in-app notices presented with the Student and Recipient profiles. The following desktop examples illustrate administrative alerts and follow-up notifications.

Figure 5.40 shows examples of ticket email notifications. Panel (a) presents a new-ticket alert for the SDS Administrator, while panel (b) presents a status update for the Student. The system also sends relevant assignment, communication, and reminder messages. The associated ticket interface provides the current record and the authorized actions.

**Figure 5.40: Ticket Email Notifications**

- (a) New-ticket alert — `fig/mail-a.png`.
- (b) Status update — `fig/mail-b.png`.
