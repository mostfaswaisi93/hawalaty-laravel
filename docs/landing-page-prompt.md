# Landing Page Prompt — Hawalaty (حوالاتي)

> A complete, self-contained brief for generating a marketing landing page for Hawalaty.
> Everything below is drawn from the actual codebase. Sections marked **NOT SHIPPED** must
> never be presented as an available feature.

---

## 1. Your role and the task

You are a senior product designer and front-end engineer. Build a **complete, production-quality
marketing landing page** for a SaaS product called **Hawalaty (حوالاتي)**.

The page is the product's only public surface. It must do four jobs, in this order:

1. Make a shop owner understand in under ten seconds what the product does for them.
2. Prove it is real software built by people who care (specificity, not adjectives).
3. Answer the objections that actually stop a small business from signing up.
4. Get them to start a free trial.

The primary audience is **not technical**. They own a restaurant, a grocery, a fuel station,
a workshop, a pharmacy. They read Arabic first. They have been burned by a spreadsheet, a
notebook, and possibly one abandoned system already.

---

## 2. What Hawalaty actually is

**One sentence:** Hawalaty is an Arabic-first, multi-tenant SaaS platform where each licensed
business gets a fully isolated workspace — **its own physical database** — to record daily
transactions, manage a catalog, customers, accounts and wallets, expenses, a role-based team,
and reports.

**The elevator version for the page:** every small business runs on the same three things —
what came in, what went out, and who did what. Hawalaty is one place for all three, in Arabic,
that keeps working when the internet does not.

### Positioning

- It is **not** a restaurant POS, even though the default catalog wording is a food menu.
  A fruit shop, a fuel station and a hardware store run the exact same screens. The product
  lets each tenant rename the catalog vocabulary to their own trade.
- It is **not** an accounting package. It does not do double-entry bookkeeping for an
  accountant; it gives an owner live balances and honest reports.
- It is **not** an enterprise ERP. The whole point is that a shop can be running in an
  afternoon.

### The competitive wedge (use these, they are true and specific)

| Claim | Why it is credible |
|---|---|
| Your data is in **its own database**, not a row in a shared table | Each license is provisioned a real MySQL database with a dedicated MySQL user scoped to it |
| **Works with no internet** | Orders written offline are queued in IndexedDB and flushed automatically when the network returns |
| **Arabic first, not Arabic-translated** | RTL is the default layout, not a mirrored afterthought; the translation keys *are* the Arabic strings |
| **Permissions are enforced on the server** | Every action maps to a permission key checked by a policy, not hidden in the UI |
| **Nothing is silently deleted** | Financial records are soft-deleted; an audit log records who did what and when |

---

## 3. Complete feature inventory (what to draw from)

Use this as the source of truth. Do not invent features. You do not have to surface all of it —
choose what earns its place — but nothing on the page may contradict this list.

### 3.1 Daily operations

- **Order builder** — pick items from the catalog, the total computes automatically, change is
  handled, payment method recorded (cash, bank transfer, or e-wallet) in the same movement.
  Optional discount, reference number, and notes on an order.
- **Order status** — completed / pending / failed.
- **Printable PDF receipts**, sized for thermal printers.
- **Searchable transaction history** with filters and pagination (10 / 50 / 100 per page,
  and the choice is remembered).
- **Customers** — identity is the **phone number, not the name**. Two walk-ins called "أحمد"
  are two customers; the same phone is always the same person however the name is spelled
  today. Per-customer order history and total spend.
- **Global search** — `Ctrl` / `⌘` + `K`, keyboard navigable, and it respects permissions:
  a member who may not manage accounts does not see accounts in results.
- **In-app notification centre.**

### 3.2 Money

- **Accounts** — banks, e-wallets, and cash boxes, each with its own live balance that updates
  the moment a movement is recorded. Multi-currency support.
- **Ledger entries** — every balance is the sum of recorded movements, not a number someone
  typed.
- **Transfers between accounts** in one step.
- **Expenses** — tracked per account and per date.
- **Balance page** — money in (completed orders) versus money out (expenses, with salaries
  broken out as a subtotal), per account and in total.
- **Team payroll** — each member carries a daily or monthly salary. Paying one writes an
  expense **and** a salary record in a single transaction, so it lands in Expenses, Balance
  and Reports at once, against the account the money actually left.

### 3.3 Catalog

- Categories and entries with prices, per-entry icons chosen from a trade-grouped set,
  active/inactive state, and **CSV import/export**.
- **The catalog is renameable.** Three labels belong to the tenant — the section name, the word
  for a grouping, and the word for an entry. It ships worded as a food menu because that is the
  common case, not because the app assumes food.

### 3.4 Reports

- Revenue, expenses, net profit, top-selling items, team activity — computed live, not at
  month end.
- Daily / weekly / monthly revenue views.
- A **year-long activity heatmap**.
- **CSV and PDF export** with one click.
- Dashboard: revenue tile, weekly-activity chart, recent transactions.

### 3.5 Team, roles and trust

- **Three system roles** seeded into every workspace — Owner (all permissions), Manager
  (everything except managing the team), Staff (create orders only). Owners can define
  additional custom roles.
- **Seven permission keys**: create orders, manage catalog, manage accounts, manage expenses,
  manage constants, manage devices, manage team. Each is described in plain language in the
  UI, not by its key.
- **Enforced by policies on the server**, shared by both the web app and the REST API — so a
  crafted request cannot do what a hidden button could not.
- **Activity log** — a read-only audit trail of every tenant action, with actor and timestamp.
- **Devices** — registered client devices with a per-package limit and revocation.

### 3.6 Works offline

- Orders created with no connection are saved on the device (IndexedDB, so they survive a tab
  crash or a phone dying mid-shift) and sent automatically when the network returns.
- Pages already visited stay readable offline.
- **Scope is deliberate and worth saying plainly:** creating orders and expenses works offline;
  *editing* and the configuration screens still need the network, because a queued edit needs
  conflict resolution a shop counter gains nothing from.
- **A dead session never looks like a successful send.** If the session expired while offline,
  the flush refuses the response rather than reporting orders delivered that never arrived.
- **Installable to a home screen** as a standalone app (PWA) — which is where the offline
  queue earns its keep, on a tablet at a counter.

### 3.7 WhatsApp order notifications

When an order is paid by bank/wallet transfer, a formatted Arabic "new order" message is sent
to the WhatsApp number stored on that account. A WhatsApp failure can never fail or roll back
an order — the order is already committed before the message is attempted.

> **Honesty constraint:** real delivery requires the merchant to connect a WhatsApp Business
> (Meta Cloud API) account with an approved message template. Present this as a feature that is
> **connected in settings**, not as something that works the second you sign up.

### 3.8 Bilingual, Arabic-first

- Full Arabic and English interface, both writing directions, switchable instantly from any
  page. Each team member picks their own language without affecting anyone else.
- Not a half-finished translation: the codebase has an automated test that **fails the build**
  if any string in the app is missing from either catalog.
- Charts always read left-to-right even in the Arabic UI, because a trend that runs
  right-to-left is not what anyone expects from a chart. Dates, phone numbers and amounts are
  likewise always LTR.

### 3.9 REST API and sync

A versioned JSON REST API (`/api/v1`) with token authentication for mobile and SPA clients,
including an offline push/pull sync channel. Worth one line on the page under an Enterprise
or developer angle — not a headline for a shop owner.

### 3.10 Behind the scenes (trust signals, use sparingly)

- Database-per-tenant isolation, provisioned automatically at signup — database, dedicated DB
  user, migrations, roles and the owner account, in one operation. If any step fails it cleans
  up after itself.
- Financial records are soft-deleted, so historical records and the names they reference
  survive a deletion.
- Money is compared with decimal arithmetic, never floats — an account balance cannot be
  driven negative by two people submitting at the same moment.
- Automated backups.
- **492 tests, 7 503 assertions** — usable as a single credibility stat, phrased for a
  non-technical reader ("every release runs 492 automated checks before it ships").

---

## 4. The twelve business types

Hawalaty is designed around twelve verticals: **restaurants & cafés, bakeries, fuel stations,
produce (fruit & veg), grocery & supermarket, books & stationery, tobacco, clothing,
electronics, pharmacies, auto parts, services**.

**What is shipped today:** the business type is chosen when the license is issued, and it sets
the workspace's catalog vocabulary, its icons, its default unit of measure, and the ready-made
lookup lists that trade actually uses (e.g. a restaurant opens with order types, prep stations,
tables and spice levels; a pharmacy with dosage forms, routes, manufacturers, control schedules
and prescribers).

**NOT SHIPPED — do not advertise:** inventory ledger, purchasing and suppliers, lot/batch and
expiry tracking, serial numbers, size×colour variant matrices, recipes/BOM, order modifiers,
weighed selling, waste tracking, warranty/RMA, vehicle fitment, appointment scheduling, fuel
tank and pump reconciliation, age verification, prescription handling, allergen declarations.
These are designed and on the roadmap; none of them exists as a working screen.

**How to use the twelve on the page:** as proof of range — "built for your trade, not adapted
to it" — through a scrolling row of trade names and icons, and optionally a small selector
where picking a trade shows the vocabulary and lists that workspace would open with. Never
imply a pharmacy gets batch tracking or a clothing shop gets a size matrix today.

---

## 5. Pricing (real numbers — do not invent)

Four cards. Currency is **ILS (₪)**. A monthly/annual toggle where **annual = monthly × 10**,
labelled "two months free".

| Card | Price / month | Devices | Positioning line |
|---|---|---|---|
| **البداية / Starter** | 0 | up to 3 | Try the whole system before you pay |
| **الاحترافية / Pro** — *most popular* | 99 | up to 10 | For a running business with a small team |
| **الأعمال / Business** | 299 | up to 10 | For several branches and a full team |
| **إنتربرايز / Enterprise** | Talk to us | unlimited | For an organisation with specific requirements |

- Each tier lists what it **adds** over the one below, under a line reading "everything in
  \<previous tier\>". Do not repeat the full list on every card.
- Feature names available across the tiers: orders & transfers, catalog, reports, accounts &
  wallets, expenses, team & permissions, device management, constants, backups, WhatsApp API.
- The Enterprise card is a **"talk to us"** card, not a tier — there is nothing to sign up for.
  Its extras: dedicated server if wanted, API integration, an SLA, a dedicated account manager,
  onboarding and training.
- **Trial: 14 days, full features, no credit card, nothing charged automatically at the end.**

---

## 6. Required page structure

In this order. Every section must be genuinely useful — no filler.

1. **Header** — logo, anchor nav (What is it / Features / How it works / Pricing / Security /
   FAQ), language toggle (AR ⇄ EN), theme toggle, Log in, and a primary **Start free** button.
   Sticky, condensing on scroll. Full mobile menu.

2. **Hero** — one headline that names the outcome, not the category. A subhead of at most two
   lines. Primary CTA "Start free", secondary CTA "See how it works". Three chips under the
   CTAs: *14 days free* · *no credit card* · *cancel anytime*. To the side, a **product mock
   built in HTML/CSS** — never a stock photo, never a fake screenshot of a competitor. Show a
   small dashboard: a revenue figure, a seven-bar weekly chart, two recent transactions with
   status pills.

3. **Trades marquee** — a slow, continuous, pausable row of the trades served, each with an
   icon. This is the "it is not just for restaurants" answer, delivered before anyone asks.

4. **Stat strip** — four numbers that count up when scrolled into view. Suggested and true:
   *8 seconds to record a full order*, *2 languages in one interface*, *50+ team members with
   fine-grained permissions*, *100% works without internet*.

5. **What is it / guided tour** — a vertical tab list (Orders · Accounts & wallets · Reports ·
   Team & permissions) where selecting one swaps a panel with a CSS mock and three bullet
   points. Keyboard accessible: arrow keys move between tabs.

6. **Features grid** — nine cards, icon + title + one sentence. Draw from §3. Each sentence
   must contain one concrete detail, not a category name.

7. **Before / after** — two columns, six paired rows. "A notebook and a separate Excel file" →
   "One place that updates itself". "You learn the bank balance at month end" → "Every account
   balance is in front of you now". This section converts better than any feature list; make
   the "before" lines uncomfortable and accurate.

8. **How it works** — three steps: create your account → set up your catalog and accounts →
   start operating. One sentence each.

9. **Security & isolation** — the strongest technical section, written for a non-technical
   reader. Your data is in its own database. Permissions are checked on the server. Every
   action is logged. You can export everything, any time, without asking anyone. Link to a
   fuller security page.

10. **Pricing** — as specified in §5.

11. **FAQ** — accordion, one open at a time, marked up so it is readable without JS. Cover, at
    minimum: *Is it only for restaurants?* · *Is my data isolated from other businesses?* ·
    *What happens when the internet goes down?* · *Do I need a credit card to try it?* ·
    *Can I export my data if I leave?* · *How many devices and team members?* · *Do you support
    Arabic and English?* · *How do I migrate from what I use now?*

12. **Final CTA** — one line, one button, no distractions.

13. **Contact form** — name, email, phone, subject (pricing enquiry / request a demo / support /
    partnership / other), message. Client-side validation with real error messages.

14. **Footer** — product links, legal, support email and phone, language and theme toggles,
    social if any.

---

## 7. Design system (match the product exactly)

The landing page must look like the app it sells. These are the real tokens.

### Colour — declared as `R G B` triples, mapped once, light and dark

```css
:root {
  --c-primary: 0 35 111;              /* deep indigo */
  --c-primary-container: 30 58 138;
  --c-secondary: 144 77 0;            /* amber/bronze accent */
  --c-secondary-container: 254 147 44;
  --c-tertiary: 39 43 45;
  --c-surface: 248 249 255;
  --c-surface-container-lowest: 255 255 255;
  --c-surface-container-low: 239 244 255;
  --c-surface-container: 229 238 255;
  --c-surface-container-high: 220 233 255;
  --c-on-surface: 11 28 48;
  --c-on-surface-variant: 68 70 81;
  --c-outline: 117 118 130;
  --c-outline-variant: 197 197 211;
  --c-error: 186 26 26;
  --c-success: 21 128 61;
}
.dark {
  --c-primary: 182 196 255;
  --c-primary-container: 30 58 138;
  --c-secondary: 255 183 125;
  --c-tertiary: 196 199 201;
  --c-surface: 10 14 23;
  --c-surface-container-lowest: 5 7 13;
  --c-surface-container-low: 18 22 31;
  --c-surface-container: 22 27 36;
  --c-surface-container-high: 33 38 48;
  --c-on-surface: 230 234 242;
  --c-on-surface-variant: 194 199 210;
  --c-outline: 139 144 160;
  --c-outline-variant: 58 63 74;
  --c-error: 255 180 171;
}
```

Rules:
- Reference colours through the mapped token (`text-primary`, `bg-surface-container`), so one
  utility follows the theme with no `dark:` variant at the call site.
- `--c-primary-container` and `--c-secondary-container` are **deliberately fixed** — they do not
  invert, because they are always a dark surface carrying light text.
- Theme is toggled by a `.dark` **class** on the root element, persisted, and read before first
  paint so there is no flash. It is not `prefers-color-scheme` alone — respect the OS as the
  initial default, then let the user override it.

### Typography

- Arabic and English both need to look intentional. Use a display family for headings and a
  clean UI family for body, with an Arabic face that is genuinely designed for Arabic (Cairo,
  IBM Plex Sans Arabic, Noto Kufi Arabic — not a Latin font with fallback glyphs).
- Scale: hero 40 → 64px, section headings 30 → 48px, body 16 → 18px, labels uppercase 12–13px
  with wide tracking.
- Line height 1.1 for display, 1.6–1.75 for body. Arabic needs the looser body leading.

### Icons

Material Symbols Outlined, ligature-based. Because the font maps a **word** to a glyph, the
literal word is visible before the font loads — hide icons until it is ready:

```css
.material-symbols-outlined { visibility: hidden; }
html.icons-ready .material-symbols-outlined { visibility: visible; }
```

### Shape and elevation

Rounded — 12px on controls, 16–24px on cards, full on pills. Elevation is a **tonal surface
step plus a soft coloured shadow**, not a grey box-shadow. Cards sit on
`surface-container-lowest` above a `surface-container-low` section band; alternate the band
between sections so the page has rhythm.

### Motion

- Scroll-reveal: 8–14px rise with fade, staggered ~90ms across a grid.
- Count-up on the stat strip, once, when it enters the viewport.
- Marquee: slow, continuous, pauses on hover and on focus-within.
- Buttons: `active:scale-95`.
- **Every animation must be disabled under `@media (prefers-reduced-motion: reduce)`**, and
  scroll-revealed content must be visible — not stuck at opacity 0 — when motion is reduced.

---

## 8. RTL — the part most implementations get wrong

The default rendering of this page is **Arabic, `dir="rtl"`**. English is the alternate.

- Use logical properties everywhere: `start`/`end`, `ms-`/`me-`, `ps-`/`pe-`. Never `left`/
  `right` for layout.
- **Directional glyphs must be mirrored** in RTL — arrows, send, logout, north-east. Apply an
  `rtl:-scale-x-100` style. A non-directional icon (search, settings) must *not* flip.
- **`dir` on a block element also changes `text-align`.** To keep a phone number, a date or an
  amount reading LTR inside an Arabic cell, put `dir="ltr"` on an inner inline `<span>`, never
  on the block — otherwise the whole cell left-aligns and stops lining up with its heading.
- **Charts are pinned `dir="ltr"` as a whole** — the bars *and* their captions together, inside
  the same element. Pinning only the bars flips the labels relative to the data and silently
  mislabels every column.
- Absolutely positioned elements that use a translate to centre need their translate reversed
  under RTL.
- Test both directions before considering the page finished.

---

## 9. Accessibility (non-negotiable)

- A **skip link** as the first element in the tab order, hidden until focused, targeting a
  `<main>` that carries `tabindex="-1"` — otherwise the browser scrolls but leaves focus in the
  navigation and the next Tab goes straight back to the menu.
- **Every control has an accessible name.** A placeholder is not a name. Icon-only buttons need
  `aria-label`. A `<label>` must be tied to its input by `for`/`id` or by wrapping it.
- Tabs: `role="tablist"` / `role="tab"` / `role="tabpanel"`, `aria-selected`, arrow-key
  navigation, roving tabindex.
- Accordion: real `<button>` triggers with `aria-expanded` and `aria-controls`.
- Mobile menu: `aria-expanded`, `aria-controls`, focus trapped while open, Escape closes.
- Theme and language toggles: `aria-label`, and the theme switch uses `role="switch"` with
  `aria-checked`.
- Contrast ≥ 4.5:1 for body text in **both** themes. Verify status pills specifically — they
  are the usual failure.
- Visible focus rings. Never `outline: none` without a replacement.
- The page must be fully readable and navigable with JavaScript disabled; JS enhances, it does
  not gate content.

---

## 10. SEO and structured data

- `<title>`: "Hawalaty — order and account management for any business" (Arabic equivalent as
  the default).
- Meta description naming: orders, accounts, wallets, expenses, reports, fine-grained
  permissions, works offline, 14-day free trial.
- `<html lang>` and `dir` set correctly per locale; `hreflang` alternates for `ar` and `en`.
- Canonical URL. Open Graph and Twitter card with a real OG image.
- **JSON-LD, three blocks:** `Organization` (with contact points for support and sales, both
  languages), `SoftwareApplication` (category `BusinessApplication`, `inLanguage: [ar, en]`,
  a `featureList`, and an `AggregateOffer` with `lowPrice` 99 / `highPrice` 299 /
  `priceCurrency` ILS), and `FAQPage` mirroring the FAQ section exactly.
- One `<h1>`. A sane heading hierarchy after it.
- Every section that the nav links to needs a stable `id` and `scroll-margin-top` so the sticky
  header does not cover the heading.

---

## 11. Copy guidance

**Language:** Arabic is the primary voice. Write Arabic that sounds like a person, not like a
translated brochure — short sentences, concrete nouns, no marketing abstractions
("حلول متكاملة", "تجربة استثنائية" are banned). The English version is a real translation, not
a transliteration.

**Rules:**
- Every headline names an outcome the owner recognises, never a feature category.
- Every feature sentence contains one specific detail. "Reports" is nothing. "Revenue, expenses
  and net profit computed now, not at month end, exported to PDF or CSV in one click" is
  something.
- Numbers beat adjectives. 14 days. 3 roles. 7 permissions. 8 seconds.
- Address the objection in the objection's own words. The FAQ answer to "is my data isolated"
  should say *its own database, not a column separating rows in a shared table*.
- No fake urgency, no fake social proof, **no invented testimonials, no invented customer
  counts, no invented logos.** If there are no customers to quote yet, the page does not have a
  testimonials section.

---

## 12. Technical constraints for the build

- Single self-contained HTML file (or a Blade view for Laravel 13 + Tailwind CSS v4 + Alpine.js,
  matching the existing stack — state which you are producing).
- **Tailwind v4 is configured in CSS, not JavaScript.** There is no `tailwind.config.js`. Theme
  tokens are declared with `@theme` inside the stylesheet.
- The `dark:` variant must be redefined for class-based theming:
  `@custom-variant dark (&:where(.dark, .dark *));` — without it every `dark:` utility silently
  does nothing.
- No external image or script dependencies beyond the icon and web fonts. All product mocks are
  built from HTML and CSS.
- Alpine.js for interactivity (tabs, accordion, mobile menu, theme, pricing toggle) — kept
  small, with `x-cloak` so nothing flashes before hydration.
- Responsive from 320px up. Test at 320, 768, 1024, 1440.
- Lazy-load anything below the fold. Preload the fonts actually used above the fold.
- No layout shift: reserve space for the hero mock and any image.

---

## 13. Honesty guardrails — read before writing a single line

Do not claim any of the following, because none of it is true today:

- Inventory levels, stock counts, purchase orders, suppliers, or low-stock alerts
- Expiry dates, batch/lot tracking, serial numbers, or product recalls
- Barcode scanning
- Size × colour variants, recipes, or order modifiers (extra cheese, no onion)
- Appointments, bookings, or work orders
- Selling by weight or volume with fractional quantities
- Loyalty points, gift cards, or promotions engines
- Payment processing, card terminals, or online payments
- E-invoicing / ZATCA / tax authority integration
- Native iOS or Android apps in the stores (the product is an installable web app)
- Real-time collaboration or live push updates between users
- Any specific customer name, count, logo, testimonial, rating, or "trusted by" claim
- Uptime percentages, certifications (ISO, SOC 2, PCI), or compliance guarantees
- Automated backup of tenant data on a schedule (on-demand backups exist; the *scheduled* job
  currently covers the platform database only)

When you are unsure whether something is shipped, leave it out. A landing page that under-
promises and is entirely true is worth more than one that converts a visitor into a
disappointed trial.

---

## 14. Deliverable

Produce the complete page, ready to open. Then give me a short list of:

1. Every claim you made that you could not verify from this brief, so I can confirm or cut it.
2. The three headline options you considered and why you chose the one you did.
3. Anything in this brief you think is wrong for a landing page, and what you would do instead.
