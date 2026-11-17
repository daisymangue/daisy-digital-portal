# Daisy Digital Agency Client Portal

Laravel 13, MySQL, Blade, Tailwind CSS 4 and Alpine.js. Built to run on Hostinger Business hosting under a subdomain such as `portal.daisydigitalagency.com`.

## What milestone 1 includes

**Clients**
- Register, sign in with a password or a one-tap email link (works once, expires after 20 minutes).
- Dashboard with open requests, items waiting on them and recent updates.
- Start a request: choose a package, tick individual services from 15 categories (442 services with guide prices), answer a few questions for the categories they picked, describe the need in their own words and upload files.
- See each request and its status. Email and in-portal notification when the status changes.

**Admin**
- Dashboard with counts, requests needing attention and recent activity.
- Requests list with search and status filter.
- Request review: services, answers, client's own words, files, price guide, status change, internal notes that clients never see.
- Clients list and client page.
- Services: edit categories, services, prices and the questions asked per category.
- Packages: edit names, prices and what is included.

**Under the hood**
- Two roles, admin and client. Registration can only create clients. Admins are created on the server with a command.
- Every client query goes through the signed-in client, so one client cannot open another's requests or files. Covered by automated tests.
- Uploaded files are stored outside the public folder and served through a permission check.
- Requests keep their own copy of service names and prices, so later price changes never alter an old request.
- Audit log of sign-ins, submissions, status changes, notes, downloads and catalogue edits.
- Money is stored as whole cents.

## What milestone 2 adds: quotes

**Admin**
- Create a quote from a request with one click. The requested services become line items at your catalogue prices.
- Quote builder: edit lines, add services from the catalogue or blank lines, quantity and unit, discount, VAT, deposit percentage, scope, timeline, terms and a valid-until date. Totals update as you type.
- Send to the client. A sent quote is locked. "Revise" makes a new version (Q-0001 v2) and the old one is kept as the record.
- Settings screen: business details, VAT number and rate, quote validity (30 days), default deposit, default terms and bank details.
- PDF download of any quote.

**Clients**
- Email and dashboard notice when a quote is ready.
- View the quote, accept it by typing their name and ticking a confirmation, ask for changes with a message, or decline.
- PDF download.

**Rules built in**
- Every amount is calculated on the server from the line items. The browser totals are a preview only.
- Acceptance stores who accepted, when, from which IP address, and a frozen copy of the whole quote.
- An accepted quote cannot be edited or revised. Expired and replaced quotes cannot be accepted.
- Quotes expire automatically after their valid-until date.
- Sending a quote marks the request as Quoted. Accepting marks it Approved.

**VAT:** quotes add VAT at the rate in Settings (15% by default). Only charge VAT if the business is VAT registered, and enter the VAT number in Settings so it prints on documents. If not registered, set the rate to 0.

## What milestone 3 adds: invoices and payments

**Admin**
- From an accepted quote: create a deposit invoice, then a balance invoice, or invoice the full amount. Deposit plus balance always equals the quote total to the cent.
- Manual invoices for work that never had a quote, such as a monthly care plan.
- Draft, then issue. The invoice number (INV-0001) is assigned when you issue, so issued numbers run in order with no gaps. Issued invoices are locked.
- Record a payment you have seen in the bank (EFT, card, cash or other).
- Review a client's proof of payment: confirm it, correct the amount, or mark it not received with a reason.
- Cancel an issued invoice with a reason, as long as no payment is recorded against it.
- Invoice list with outstanding total, overdue filter and search. Dashboard shows outstanding, overdue, money received this month and proofs waiting to be checked.
- PDF download. It is headed "Tax invoice" and shows your VAT number when VAT is charged and a VAT number is set.

**Clients**
- Email and dashboard notice when an invoice is issued.
- Invoice page with your bank details, the reference to use, and payment history.
- "I have paid": enter the amount and date and upload proof of payment.
- PDF download.

**Rules built in**
- An invoice is only marked paid by payments an admin has confirmed. Uploading proof never marks it paid.
- Payments cannot exceed the balance. A second proof for the same money is refused while the first is being checked.
- Partial payments are tracked: Issued, Partially paid, Paid, and Overdue once the due date passes.
- One client cannot see or pay another client's invoice, or open their proof of payment.

**Not included yet**
- Card payments through a gateway. This needs a PayFast or Paystack merchant account first. The payments table already has a "card" method, so the gateway plugs into it.
- Credit notes and refunds. For now a wrong invoice is cancelled and reissued.
- Automatic recurring invoices and overdue reminder emails. These come with care plans in milestone 5.

## What milestone 4 adds: projects

- A project opens by itself when a deposit invoice, or a full invoice, for an accepted quote is fully paid (recorded by you, or proof of payment you confirm). It gets a number like P-0001 and the client is emailed.
- You can start one early from the accepted quote, or with no quote at all from the Projects page. Both need a reason, which is kept on the project.
- Each project has: chat with file attachments (new messages appear within 10 seconds), progress updates, steps with optional client approval, a "what we need from you" list, and a shared file list.
- Steps marked "Client must approve" go to the client when you set them to Done. Only the client can approve, by typing their name, or they can ask for changes with feedback.
- Progress follows the steps. With no steps you set it by hand.
- Unread messages show as a count next to Projects in the menu, on both sides. Only the first unread message sends an email, so a burst of messages is one email.

## What milestone 5 adds: after-care

- **Change requests**: clients ask for extra work from the project's Progress tab. You quote it (a draft quote is made for you, and paying it never opens a second project), include it free, or decline with a reason.
- **Care plans** (menu: Care plans): a price per month, quarter or year. Invoices are created at 06:05 on the due date and either emailed automatically or left as drafts for you. Pause or end a plan any time. It never bills the same period twice.
- **Overdue reminders**: emailed 1, 7 and 14 days after the due date, then they stop. Switch off in Settings. You can also send one by hand from any unpaid invoice (once a day).
- **Handover**: on the project's Handover tab, write notes and attach guides. Save as a draft, then send. The client confirms they have everything by typing their name.
- **Testimonials**: after confirming the handover, the client is asked for a 1 to 5 star rating and a few words, and whether you may publish it. 4 and 5 star clients are offered your Google review link (set it in Settings). See them all under Testimonials.
- The email box on the sign-in page says "Your email is your username".

## What milestone 6 adds: ready for launch

- **Needs your attention** at the top of the admin dashboard: payments to check, new requests, unread messages, change requests, client uploads, care plan drafts and overdue invoices. Each one links straight to the work.
- **Archive a client** (client page > Records). They cannot sign in any more, even if they were signed in at the time, and their care plans pause. Nothing is deleted. Restore any time. Archived clients are hidden from lists and dropdowns.
- **Download all records** for a client as a zip (data.json plus every file). Use it for a POPIA access request, or when a client leaves.
- **Automatic clean-up** every night at 03:20 (`php artisan portal:prune`): old read notifications, sign-in links, sessions older than 30 days, activity log over 3 years, and temporary files. Quotes, invoices and payments are never removed, because SARS expects five years of records.
- **Security:** browser protection headers on every page (no framing, no content sniffing, strict referrer, HSTS on https), signed-in pages are never cached, the logo upload refuses SVG, and every client page and file is checked against the signed-in client. 81 automated tests cover access between clients, roles and archived accounts.
- **Logo upload** in Settings.
- **Launch check:** `php artisan portal:check`.

## Working for clients who do not log in

Not every client will use the portal. The admin can run the whole flow for them:

- **Add a client** (Clients > Add a client) with contact person, email, phone, address and internal notes. Portal access is optional.
- **New request for a client** (Requests > New request for a client, or from the client's page). Record how it came in: phone, email, WhatsApp, in person. Required questions are optional here, since you may not have every answer yet.
- **Quotes and invoices** work the same way. When the client has no login, nothing is emailed and the portal tells you to download the PDF and send it yourself.
- **Record the client's answer** on a sent quote: accepted or declined, how they told you (email, WhatsApp, phone, in person, signed copy) and a note. The acceptance record shows it was recorded by your team.
- **Record payments** on the invoice as before.
- **Give portal access later** from the client's page. They get an email with a sign-in link that works for 7 days, and then see everything created for them so far. They can set a password under Account.

PDFs are addressed to the portal user, or to the contact person and address you entered.

Not built yet: projects and chat. See the roadmap at the end.

## Run it on your computer

Needs PHP 8.3+, Composer and Node 20+.

```bash
composer install
cp .env.example .env
# For a quick local run, change these in .env:
#   APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8000
#   DB_CONNECTION=sqlite (and remove the other DB_ lines)
#   MAIL_MAILER=log, SESSION_SECURE_COOKIE=false
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan portal:make-admin "Your Name" you@example.com
npm install && npm run build
php artisan serve
```

With `MAIL_MAILER=log`, emails (including sign-in links) are written to `storage/logs/laravel.log`.

Run the tests with `php artisan test`.

## Deploy to Hostinger (Business plan)

1. **Subdomain.** In hPanel create `portal.daisydigitalagency.com`. Note its folder, usually `public_html/portal`. Turn on SSL for it.
2. **Database.** Create a new MySQL database and user for the portal only. Note the name, user and password.
3. **Mailbox.** Create `portal@daisydigitalagency.com` (or similar) for outgoing email.
4. **Upload.** Upload the project into the subdomain folder, by Git or the file manager. `public/build` is already compiled, so Node is not needed on the server.
5. **Document root.** Best: point the subdomain at the project's `public` folder. If hPanel will not let you, leave it. The `.htaccess` in the project root forwards requests into `public` and blocks everything else.
6. **Install.** Over SSH, inside the project folder:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
7. **Configure.** Edit `.env`: database name, user and password, mailbox password, and check `APP_URL`.
8. **Set up the database and catalogue.**
   ```bash
   php artisan migrate --seed --force
   php artisan portal:make-admin "Thabang" your-email@daisydigitalagency.com
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
9. **Cron job.** In hPanel add one cron job, every minute. It runs scheduled clean-ups and the queue:
   ```
   * * * * * cd /home/USERNAME/domains/daisydigitalagency.com/public_html/portal && php artisan schedule:run >> /dev/null 2>&1
   ```
   Use the real path shown in hPanel.
10. **Run the launch check.** It lists anything still missing (debug mode, email, https, cron, bank details) and how to fix it:
    ```bash
    php artisan portal:check
    ```
    Wait 5 minutes after adding the cron job before trusting the scheduler line.
11. **Test it end to end.** Sign in as admin, register a test client in a private window, submit a request, quote it, accept it, issue the deposit invoice, record payment, and check the project opens and every email arrives. Then archive the test client.

After changing `.env` or uploading new code, run `php artisan config:cache && php artisan route:cache && php artisan view:cache` again. After changing anything in `resources/`, run `npm run build` on your computer and upload `public/build`.

## Things to set before real clients use it

- **Terms and privacy policy.** The registration form links to `/terms` and `/privacy` on the main website. Those pages need to exist, or change the links in `.env`.
- **Logo.** Upload it under Admin > Settings > Logo (PNG, JPG or WebP). It shows in the menu, on the sign-in page and on PDFs.
- **Settings.** Business email, phone, address, VAT number (only if registered), bank details and your Google review link.
- **Brand colours.** All in one place at the top of `resources/css/app.css`.
- **Questions.** The questions per category are a first draft. Edit them under Admin > Services.
- **Backups.** Turn on Hostinger backups and also export the database on a schedule to somewhere off the server.

## Where things are

| What | Where |
|---|---|
| Routes | `routes/web.php` |
| Database tables | `database/migrations` |
| Service list and packages (from the spreadsheet) | `database/seeders/data/` |
| Controllers | `app/Http/Controllers`, admin ones in `Admin/` |
| Screens | `resources/views` |
| Emails | `app/Notifications` |
| Quote and invoice PDF layouts | `resources/views/pdf` |
| Tests | `tests/Feature` |

## Roadmap

4. Projects (done).
5. After-care (done).
6. Launch readiness (done).
