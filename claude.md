# SWS Members Club: Ticketing & Membership Management App

## Build Prompt for Claude Cowork / Claude Code

---

## Project Summary

Build a WordPress plugin that manages membership billing and event ticketing for a premium private members club. The plugin replaces a broken Tickera setup that has caused persistent complaints around cancellations, refunds, calendar invites, and overall UX quality.

The system must feel premium. Members are paying customers who expect a polished, intuitive experience. Every interaction (booking, cancelling, receiving an email) should be clear and professional.

---

## Technical Stack

- **Platform:** WordPress plugin (PHP + JavaScript)
- **Payments:** Stripe API (subscriptions for membership, one-off charges for events)
- **Database:** WordPress custom tables (wp_ prefix, using $wpdb)
- **Frontend:** Shortcodes output semantic HTML with CSS classes. The plugin ships with minimal functional CSS only (layout, not branding). All visual design is handled by the theme developer.
- **REST API:** The plugin registers WordPress REST API endpoints under `/wp-json/sws/v1/` for all member-facing actions (book, cancel, join waitlist, fetch events, fetch tickets). This allows the web developer to build fully custom frontend experiences using AJAX or any JavaScript framework if preferred, rather than relying on shortcode output.
- **Emails:** WordPress wp_mail with HTML templates
- **Calendar:** Generate .ics files and Google Calendar links
- **Admin:** Custom admin pages under a dedicated menu in wp-admin
- **Authentication:** WordPress user system (members = WordPress users with a custom role)

---

## Frontend Architecture: Separation of Concerns

This plugin is a backend system. The web developer designs and builds the member-facing frontend in the WordPress theme. The plugin provides two integration paths:

### Path 1: Shortcodes with Overridable Templates

The plugin registers shortcodes (`[sws_events]`, `[sws_my_tickets]`, `[sws_booking]`, `[sws_waitlist_claim]`) that render default templates from the plugin's `templates/frontend/` directory.

The theme developer can override any template by copying it to `wp-content/themes/active-theme/sws-members-club/` and modifying the markup. This follows the same pattern as WooCommerce template overrides. The plugin's template loader checks the theme directory first, falling back to the plugin default.

Default templates output clean, semantic HTML with BEM-style CSS classes (e.g. `sws-event-card`, `sws-event-card__title`, `sws-event-card__price`). No inline styles. No opinionated design. The web developer styles these classes in the theme stylesheet.

### Path 2: REST API Endpoints

For developers who prefer full control or are using a page builder with custom modules, the plugin exposes REST API endpoints:

- `GET /wp-json/sws/v1/events` — list upcoming events (with capacity, price, waitlist status)
- `GET /wp-json/sws/v1/events/{id}` — single event details
- `POST /wp-json/sws/v1/bookings` — create a booking (handles Stripe payment server-side)
- `DELETE /wp-json/sws/v1/bookings/{id}` — cancel a booking (handles refund server-side)
- `GET /wp-json/sws/v1/my-tickets` — current member's bookings (upcoming and past)
- `POST /wp-json/sws/v1/waitlist/{event_id}` — join waitlist
- `POST /wp-json/sws/v1/waitlist/claim/{token}` — claim a waitlist spot
- `GET /wp-json/sws/v1/calendar/{booking_id}.ics` — download calendar invite
- `GET /wp-json/sws/v1/member/status` — current member's tier, status, strikes

All endpoints require authentication (WordPress nonce or application password). Responses are JSON. The developer can build any frontend they want against these endpoints.

### What the Plugin Handles Regardless of Path

Whether the developer uses shortcodes or the REST API, the plugin always handles:
- Stripe payment processing (server-side only, never in frontend JS beyond Stripe Elements)
- Stripe refund triggering
- Booking validation (capacity, membership status, cancellation cutoff)
- Email sending (confirmation, reminders, waitlist, penalties)
- Calendar invite generation
- Waitlist promotion logic
- Penalty tracking
- Cron jobs (reminders, Stripe sync)

The web developer never needs to write business logic. They only need to build the UI and call the right endpoints or use the shortcodes.

---

## Module 1: Member Management

### Data Model

Each member is a WordPress user with a custom role (`sws_member`). Additional data stored in a custom table `wp_sws_members`:

- `user_id` (FK to wp_users)
- `membership_tier_id` (FK to wp_sws_membership_tiers)
- `membership_status` (active, lapsed, suspended, waitlist_only)
- `stripe_customer_id`
- `stripe_subscription_id`
- `billing_cycle` (monthly, quarterly, annual)
- `membership_start_date`
- `membership_end_date`
- `penalty_strikes` (integer, default 0)
- `created_at`, `updated_at`

### Membership Tiers

Custom table `wp_sws_membership_tiers`:

- `id`
- `name` (e.g. 'Standard', 'Premium', 'VIP')
- `slug` (e.g. 'standard', 'premium', 'vip')
- `events_included` (boolean, default false — when true, member attends all events free of charge)
- `monthly_price` (decimal)
- `quarterly_price` (decimal)
- `annual_price` (decimal)
- `sort_order` (integer, for display ordering)
- `created_at`, `updated_at`

Admin can create, edit, and reorder tiers from a settings sub-page. At minimum, the system should ship with two default tiers:

1. **Standard** — pays per event (events_included = false)
2. **Premium** — all events included in membership fee (events_included = true)

The tier structure is flexible. The club may add further tiers over time, so the system should not hard-code tier names or behaviour beyond the `events_included` flag.

### Features

- **Import:** Admin can bulk-import existing members via CSV upload (columns: name, email, Stripe customer ID, billing cycle, membership tier, start date). The importer creates WordPress users, assigns the `sws_member` role, and populates the custom table.
- **Stripe sync:** On activation, and on a daily cron, sync subscription status from Stripe. If a subscription lapses or is cancelled in Stripe, update `membership_status` to `lapsed`. Only `active` members can book events.
- **Admin list:** Searchable, filterable member list in wp-admin showing name, email, tier, status, billing cycle, strikes, join date. Filterable by tier.
- **Manual override:** Admin can manually change membership status, tier, and reset penalty strikes.
- **Tier upgrade/downgrade:** Admin can change a member's tier. If the member's Stripe subscription needs updating, the admin handles that in Stripe directly (the plugin syncs the tier locally).

---

## Module 2: Event Management (Admin)

### Data Model

Custom table `wp_sws_events`:

- `id`
- `title`
- `description` (rich text)
- `venue_name`
- `venue_address`
- `event_date` (date)
- `event_time_start` (time)
- `event_time_end` (time)
- `capacity` (integer)
- `ticket_price` (decimal, 0.00 for free events)
- `currency` (default GBP)
- `cancellation_cutoff_hours` (integer, default 48)
- `waitlist_enabled` (boolean, default true)
- `status` (draft, published, cancelled, completed)
- `created_at`, `updated_at`

### Admin Interface

- **Create/edit events** with all fields above.
- **Event dashboard** showing: total bookings, available spots, waitlist count, revenue collected, cancellation count.
- **Cancel event action:** When admin cancels an event, all ticket holders are notified by email, all payments are refunded via Stripe automatically, and no further reminders are sent for that event.
- **Mark event as completed:** Triggers no-show detection (see Module 8).
- **Duplicate event:** Quick-create a new event based on an existing one.

---

## Module 3: Booking Flow (Member-Facing)

### Frontend Display

A shortcode `[sws_events]` renders an events listing page showing upcoming published events. Each event card shows: title, date, time, venue, price (or 'Included with your membership' for members whose tier has `events_included = true`), spots remaining.

Clicking an event opens a booking view with:

1. Event details (title, date, time, venue, description)
2. Ticket selection (quantity: 1 or 2 if +1 guest is allowed)
3. If +1 selected: guest name and guest email fields
4. If member's tier has `events_included = false`: Stripe payment form (using Stripe Elements for PCI compliance). If `events_included = true`: no payment form shown; display "Included with your membership" instead.
5. Checkbox: 'I agree to the cancellation and refund policy' with link to policy page
6. Book button

### Booking Logic

- Check member status is `active`. If not, show a message explaining they need an active membership.
- Check member status is not `waitlist_only`. If it is, show the waitlist join option instead of direct booking.
- Check available capacity. If full and waitlist is enabled, offer to join the waitlist instead.
- **If member's tier has `events_included = true`:** Skip Stripe payment entirely. Create booking record with `amount_paid = 0.00` and `stripe_payment_intent_id = NULL`. Send confirmation email and calendar invite as normal.
- **If member's tier has `events_included = false` and event price > 0:** Process Stripe charge, then create booking record, send confirmation email, generate calendar invite.
- **If event price is 0.00 (free event for everyone):** Skip Stripe payment regardless of tier.
- Each ticket is stored as an individual record (not grouped by order). This is critical. If a member books two tickets, those are two separate rows in the bookings table. This prevents the bug where cancelling one ticket cancels both.

### Data Model

Custom table `wp_sws_bookings`:

- `id`
- `event_id` (FK)
- `member_user_id` (FK)
- `guest_name` (nullable)
- `guest_email` (nullable)
- `is_guest_ticket` (boolean — true if this is the +1 ticket)
- `stripe_payment_intent_id`
- `amount_paid` (decimal)
- `status` (confirmed, cancelled, refunded, no_show, waitlisted)
- `waitlist_position` (nullable integer)
- `waitlist_offered_at` (nullable datetime)
- `booked_at`
- `cancelled_at` (nullable)
- `refunded_at` (nullable)

---

## Module 4: My Tickets (Member Portal)

### Frontend

A shortcode `[sws_my_tickets]` renders the member's ticket dashboard. Two tabs:

**Upcoming Events:**
- Each ticket shown as an individual card (not grouped by order)
- Card shows: event title, date, time, venue, guest name (if +1), amount paid (or 'Included with membership' if amount is 0 and member tier has events_included)
- "Add to Calendar" dropdown with options: Google Calendar, Outlook, Apple Calendar (download .ics)
- "Cancel Ticket" button (only visible if within cancellation cutoff window)
- If past cutoff: show text "Cancellation window has closed" instead of button

**Past Events:**
- List of attended events with date and venue
- No action buttons needed

### Design Principles (for default templates)

The plugin ships with basic, functional default templates. These are designed to be overridden by the theme developer. The defaults should:

- Use clean semantic HTML with BEM CSS classes for easy styling
- Keep each ticket clearly distinguishable as a separate card element (the current system makes it hard to tell tickets apart)
- Be mobile responsive at a structural level (flexbox/grid layout, no fixed widths)
- Apply no brand colours or fonts (the theme handles that)
- Include all necessary data attributes and class hooks the developer needs for styling and JS interaction

---

## Module 5: Cancellations & Refunds

### Cancellation Flow

1. Member clicks "Cancel Ticket" on their My Tickets page.
2. Confirmation modal: "Are you sure you want to cancel your ticket for [Event Name] on [Date]?" If a payment was made, add: "You will receive a full refund."
3. On confirm:
   a. Update booking status to `cancelled`, set `cancelled_at`
   b. If `stripe_payment_intent_id` exists and `amount_paid > 0`: trigger Stripe refund automatically. Update booking status to `refunded`, set `refunded_at`. This is a hard requirement. The current system does not do this and it causes major admin overhead.
   c. If no payment was made (premium tier member or free event): skip refund step, status stays as `cancelled`.
   d. Send cancellation confirmation email to member
   e. If waitlist exists for the event, trigger waitlist promotion (Module 7)
   f. Suppress all future reminders for this booking

### Cancellation Rules

- Cancellations only allowed up to X hours before event (configurable per event, default 48h).
- After the cutoff, the cancel button is hidden and replaced with explanatory text.
- Late cancellations (if admin processes manually) count as a strike (Module 8).

### Admin Refund Override

- Admin can manually refund any booking from the admin panel, with option to mark as 'no penalty' or 'with penalty strike'.

---

## Module 6: Email Notifications & Reminders

### Email Templates (HTML, branded)

All emails must include the club logo and be mobile-friendly. Configurable via admin settings.

**1. Booking Confirmation**
- Subject: "Your ticket for [Event Name] is confirmed"
- Body: Event name, date, time, venue, amount paid, guest name (if applicable)
- Attached: .ics calendar file with correct date, start time, end time, venue name, and venue address
- Inline links: "Add to Google Calendar" | "Add to Outlook" | "Download .ics"

**2. Cancellation Confirmation**
- Subject: "Your ticket for [Event Name] has been cancelled"
- Body: Event name, date, refund amount, expected refund processing time

**3. Event Reminders**
- Sent at configurable intervals (default: 48 hours, 24 hours, and 2 hours before event)
- Subject: "Reminder: [Event Name] is coming up"
- Body: Event name, date, time, venue, with "Add to Calendar" links
- Only sent for bookings with status `confirmed`. Never sent for `cancelled`, `refunded`, or `waitlisted` bookings. Never sent for cancelled events.

**4. Waitlist Notification**
- Subject: "A spot has opened up for [Event Name]"
- Body: Event name, date, time, venue, claim link with expiry time
- See Module 7 for logic

**5. Waitlist Expiry**
- Subject: "Your waitlist offer for [Event Name] has expired"
- Body: Explains the spot has been offered to the next person

### Calendar Invite Requirements

The .ics file must:
- Include correct DTSTART and DTEND with timezone
- Include SUMMARY (event title)
- Include LOCATION (venue name and address)
- Include DESCRIPTION (brief event details)
- Work correctly whether one ticket or multiple tickets are booked (current system fails on multiple bookings)
- Each ticket generates its own calendar invite (not one invite for multiple tickets)

### Reminder Scheduling

- Use WordPress cron (wp_schedule_event) to check for upcoming events and queue reminders.
- Track which reminders have been sent per booking in a custom table `wp_sws_reminders_sent` to prevent duplicates.
- Before sending, verify booking status is still `confirmed` and event status is still `published`.

---

## Module 7: Waitlist System

### Logic

1. When an event reaches full capacity, the booking form changes to "Join Waitlist" (no payment taken yet).
2. Waitlist entries are stored as bookings with status `waitlisted` and a `waitlist_position` (auto-incrementing per event).
3. When a confirmed booking is cancelled and a spot opens:
   a. Find the first waitlisted booking by position for that event.
   b. Send the "spot available" email with a unique claim link.
   c. Set `waitlist_offered_at` to current time.
   d. The member has 12 hours (configurable) to claim the spot.
4. If they click the claim link within the window:
   a. If member's tier has `events_included = false` and event price > 0: process Stripe payment.
   b. If member's tier has `events_included = true` or event is free: skip payment, set `amount_paid = 0.00`.
   c. Update booking to `confirmed`.
   d. Send booking confirmation email with calendar invite.
5. If the 12-hour window passes without a claim:
   a. Send the waitlist expiry email.
   b. Move to the next person in the queue.
   c. Repeat until the spot is filled or the waitlist is exhausted.

### Admin View

- Admin can see the waitlist for each event (ordered by position).
- Admin can manually promote or remove waitlist entries.

---

## Module 8: Penalty System (3 Strikes)

### Triggers

A strike is added when:
- A member does not attend an event they had a confirmed booking for (no-show). Admin marks event as completed and flags no-shows.
- A member cancels after the cancellation cutoff (late cancellation, processed by admin).

### Consequences

- **Strike 1 and 2:** Member receives an email notification: "You have received a penalty strike. You currently have [X] of 3 strikes."
- **Strike 3:** Member's status changes to `waitlist_only`. They can still browse events but can only join waitlists, not book directly. Email notification sent explaining the restriction.

### Admin Controls

- View strike history per member (date, reason, event).
- Reset strikes (with optional note for why).
- Override `waitlist_only` status back to `active`.

### Data Model

Custom table `wp_sws_penalties`:

- `id`
- `member_user_id` (FK)
- `event_id` (FK)
- `reason` (no_show, late_cancellation)
- `admin_note` (nullable)
- `created_at`

---

## Module 9: Reporting Dashboard

### Admin Dashboard Page

A dedicated reporting page in wp-admin with:

- **Overview cards:** Total active members (broken down by tier), total events (this month), total revenue (this month), average attendance rate
- **Attendance report:** Per-event breakdown showing capacity, bookings, cancellations, no-shows, attendance rate. Filterable by membership tier.
- **Cancellation report:** Cancellation volume over time, reasons (self-service vs admin), refund amounts
- **Waitlist report:** Waitlist conversion rate (offered → claimed), average wait time
- **Revenue report:** Revenue by event, by month, membership vs event ticket revenue split. Event ticket revenue broken down by tier (to show how much revenue comes from standard members paying per event vs premium members attending free).
- **Member engagement:** Most active members, members with strikes, lapsed members. Filterable by tier.
- **Export:** CSV export for any report view

---

## Admin Settings Page

A settings page under the plugin menu:

- **Stripe API keys:** Live and test mode keys (stored encrypted in wp_options)
- **Membership tiers:** Create, edit, reorder, and deactivate tiers. Each tier has: name, slug, monthly/quarterly/annual price, and the `events_included` toggle. Deactivating a tier prevents new members being assigned to it but does not affect existing members on that tier.
- **Brand settings:** Club logo upload (used in email templates and admin), primary colour, secondary colour (applied to email templates and admin UI only; the theme developer handles frontend branding independently)
- **Default cancellation cutoff:** Hours before event (default 48)
- **Waitlist claim window:** Hours to claim a waitlist spot (default 12)
- **Reminder intervals:** Configurable times before event (default: 48h, 24h, 2h)
- **Policy page:** URL to the cancellation/refund policy page
- **Email sender name and address**
- **Penalty thresholds:** Number of strikes before restriction (default 3)

---

## Non-Functional Requirements

- **Security:** All Stripe operations server-side. Never expose secret keys in frontend JS. Use nonces for all AJAX calls and REST API requests. Sanitise all inputs. Escape all outputs.
- **Performance:** Efficient database queries. Index foreign keys. Paginate admin lists.
- **Compatibility:** WordPress 6.0+, PHP 8.0+. Frontend templates use no page-builder-specific markup, so they work with any theme. REST API endpoints are theme-agnostic.
- **Coding standards:** Follow WordPress coding standards. Use WordPress hooks and filters where possible so the plugin can be extended. Fire actions at key moments (e.g. `do_action('sws_booking_created', $booking_id)`, `do_action('sws_booking_cancelled', $booking_id)`) so the theme developer or other plugins can hook in.
- **Internationalisation:** Use __() and _e() for all strings. Default language: English.

---

## Installation & Activation

On plugin activation:
1. Create all custom database tables (including membership tiers table).
2. Seed default membership tiers: 'Standard' (events_included = false) and 'Premium' (events_included = true). Do not overwrite if tiers already exist.
3. Register the `sws_member` user role.
4. Register shortcodes.
5. Schedule cron jobs (daily Stripe sync, reminder checks every 15 minutes).

On plugin deactivation:
1. Unschedule cron jobs.
2. Do NOT delete database tables (preserve data).

---

## File Structure

```
sws-members-club/
├── sws-members-club.php          (main plugin file, hooks, activation)
├── includes/
│   ├── class-sws-database.php    (table creation, queries)
│   ├── class-sws-tiers.php       (tier CRUD, tier logic checks)
│   ├── class-sws-members.php     (member CRUD, import, Stripe sync)
│   ├── class-sws-events.php      (event CRUD)
│   ├── class-sws-bookings.php    (booking logic, cancellation, refund)
│   ├── class-sws-waitlist.php    (waitlist promotion logic)
│   ├── class-sws-penalties.php   (strike tracking, status changes)
│   ├── class-sws-stripe.php      (all Stripe API interactions)
│   ├── class-sws-emails.php      (email templates, sending)
│   ├── class-sws-calendar.php    (ics generation, Google Calendar links)
│   ├── class-sws-reminders.php   (cron-based reminder scheduling)
│   ├── class-sws-reports.php     (data aggregation for dashboard)
│   ├── class-sws-rest-api.php    (REST API endpoint registration and handlers)
│   ├── class-sws-template-loader.php  (checks theme for overrides, falls back to plugin)
│   └── class-sws-admin.php       (admin pages, settings, UI)
├── public/
│   ├── class-sws-shortcodes.php  (frontend shortcode rendering, delegates to template loader)
│   ├── css/
│   │   └── sws-frontend.css      (minimal structural CSS only — layout, no branding)
│   └── js/
│       └── sws-frontend.js       (Stripe Elements init, AJAX/REST handlers, cancel modal)
├── admin/
│   ├── css/
│   │   └── sws-admin.css
│   └── js/
│       └── sws-admin.js
├── templates/
│   ├── emails/                   (NOT overridable from theme — admin controls via settings)
│   │   ├── booking-confirmation.php
│   │   ├── cancellation-confirmation.php
│   │   ├── reminder.php
│   │   ├── waitlist-offer.php
│   │   ├── waitlist-expired.php
│   │   └── penalty-notice.php
│   ├── frontend/                 (OVERRIDABLE — theme can copy to sws-members-club/ in theme dir)
│   │   ├── events-listing.php
│   │   ├── event-single.php
│   │   ├── booking-form.php
│   │   ├── my-tickets.php
│   │   └── waitlist-claim.php
│   └── admin/
│       ├── events-list.php
│       ├── event-edit.php
│       ├── members-list.php
│       ├── member-detail.php
│       ├── bookings-list.php
│       ├── reports-dashboard.php
│       └── settings.php
└── readme.txt
```

**Theme override example:** To customise the events listing, the web developer copies `sws-members-club/templates/frontend/events-listing.php` to `wp-content/themes/your-theme/sws-members-club/events-listing.php` and edits the markup freely. The plugin's template loader picks it up automatically.

---

## Key Constraints (From Current System Failures)

These are the specific problems the current Tickera system has that this build must solve:

1. **Individual ticket cancellation:** Each ticket is a separate record. Cancelling one must never affect another, even if booked in the same transaction.
2. **Automatic Stripe refunds:** When a ticket is cancelled, the Stripe refund fires automatically. No manual admin step.
3. **Accurate calendar invites:** Every invite includes event title, date, correct start/end time (not all-day), and full venue details. Multiple bookings each generate their own invite.
4. **Complete confirmation emails:** Must include event name, date, time, and venue. Not just ticket type.
5. **Reminder suppression:** Cancelled bookings and cancelled events must never trigger reminder emails.
6. **Premium UX:** The frontend must be clean, intuitive, and mobile-friendly. Members should be able to identify, manage, and cancel individual tickets without confusion.
