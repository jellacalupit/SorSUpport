# Revised Section 5.2

This revision contains 40 figure sets and 76 separate screenshot placeholders. It replaces the earlier screenshot plan. The adviser’s example informs the use of normal screens, errors, email steps, and resulting states; it does not establish SorSUpport features.

## Files and integration

- Paste section-5-2.tex over the current Features and Functionalities subsection in _5_System_Implementation_Development.tex.
- Keep your existing ss shot helper (LaTeX command `\ssshot`). setup.tex contains it if you still need it.
- Your supplied preamble already loads graphicx, caption, and placeins. No additional package is needed. caption provides `\ContinuedFloat`.
- Keep your requested `Figure \ref{...}` format. Every main figure has its own manual `\renewcommand{\thefigure}{5.x}`, numbered 5.1–5.40.
- Put each PNG into fig/ beside the main .tex file. Recompile twice to resolve references.
- Read screenshot-checklist.md for the exact screen, condition, filename, and grouping of every capture.
- section-5-2.md is a readable copy of the discussion.

## Grouping decisions

Student homepage and ticket list share a figure. Student notifications, profile, and password controls share a figure. Complaint details, files/declaration, and submission confirmation form one three-panel set. Ticket Details and Thread are separate screenshots in one figure. Clarification, resolution response, requests, and withdrawal each have their own logical sets.

Recipient coverage now includes homepage, ticket list, notifications, profile, password controls, Details, Thread, acknowledgment, return to SDS, resolution, validation feedback, and informational forwarding with its email. Acknowledgment and resolution include resulting states. Recipient-only screens are not replaced by the Student versions.

Shared account access includes normal/error login, verification screen/email, password setup/error, and reset request/email/new-password form.

SDS pages remain desktop views. Its clarification pair shows both the question and the Student response as viewed by SDS, so both screenshots have the same desktop format. Notifications and profiles for Students and Recipients are no longer omitted or relegated to a generic shared figure.

## Readable mobile layouts

Capture mobile browser screens at a consistent portrait viewport, such as 390 × 844 CSS pixels, and normal zoom. A physical phone or browser device emulation is suitable. Describe emulated captures as mobile browser views, not as photographs or screenshots taken on a physical phone.

Two mobile screenshots are shown side by side, each at up to 43% of the text width. Three-image sets keep panels (a) and (b) together and put panel (c) in a continued figure under the SAME number. This avoids reducing three entire phone screens to tiny columns within your 1.5-inch left margin. The continued panel is not a new numbered figure and does not add a duplicate List of Figures entry.

If all three images are tightly cropped to small forms and remain legible at final printed size, a single-row layout can be considered after seeing the captures. Do not decide that merely from the placeholder boxes. A lone continued panel is intentional: it belongs to the previous figure set.

Capture successive form portions at the same width. If the complaint form needs more than two viewport captures, send the extra portion as a reserve capture; the final panel count can be adjusted without hiding fields. Do not shrink a long scrolling screenshot to force it into a phone-sized box. Leave the Details/Thread controls visible where practical.

For desktop views, use a consistent viewport such as 1440 × 900, at 100% zoom. Single images use the available text width; two images are stacked. Capture email bodies and relevant subjects rather than an entire inbox. Use mobile email/webmail views for the shared access and Recipient forwarding examples; use desktop email views for the final administrative notification examples.

## Capture actual behavior

Use fictional complaints and demonstration accounts. Show the actual application or browser validation feedback. Do not edit an error into a screenshot or bypass a control just to obtain a planned validation state. If a button is disabled or browser validation prevents submission, capture that real state and identify it when you send the image; the prose can be aligned with it.

Use one sample ticket across panels that represent a sequence. Use separate sample tickets for alternative branches: a ticket returned to SDS cannot simultaneously remain the Recipient's active ticket, and a further-action request is not followed immediately by a closure rating. The draft explicitly identifies the resolution response branches.

The checklist specifies representative normal, error, and outcome states; it is not a claim that every condition has been tested. Do not perform these captures on real complaints to manufacture examples.

## Optional reserve screenshots

Only capture these if easy or if the planned image cannot show the relevant detail. Send separately; they are not required figures in the current draft.

- Additional scroll portions of long forms, profile information, and ticket descriptions.
- A selected attachment or attachment validation message that is not readable in the main form capture.
- An actual email-sent confirmation page after requesting a reset link.
- A visible request-submitted notice after update/escalation/withdrawal requests.
- Empty search results, notification read/unread selections, or password-change validation.
- Extra admin account-create/edit panels and upload error details if not visible in the account-management images.

## Suggested batches for review

1. Account access: normal/error login, verification and its email, password setup/error, reset sequence.
2. Student: homepage/list, notifications/profile, complaint form/confirmation/validation.
3. Student ticket workflow: Details/Thread, clarification, resolution/rating, requests, withdrawal.
4. Recipient: all six figure sets in the checklist.
5. SDS desktop screens and final email examples.

Send the individual images with the listed short filenames. They do not need to be assembled into collages first. After seeing their actual content and readability, the groups can be retained, tightened, or split. You do not need to screenshot every possible screen before this review.

## Verification and limits

The writing is based on the provided draft and repository interfaces/controllers, including the separate mobile Details and Thread tabs, Student and Recipient profile and notification pages, the ticket workflow, and informational-forwarding email generation. No runtime screenshots have been inspected for this revision. No LaTeX compiler is available here, so final pagination, continued-caption appearance, and readability must be checked in your thesis editor after adding the images.
