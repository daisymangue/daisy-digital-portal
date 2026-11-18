# Putting the portal live on Hostinger

One-time setup, about 30 minutes. Afterwards every update is step 7 only.

## 1. In hPanel, prepare three things

1. **Subdomain**: Domains → Subdomains → create `portal` on daisydigitalagency.com.
2. **Database**: Databases → MySQL → create a database and user (for example
   `uXXXXXXX_portal` / `uXXXXXXX_portal`). Note the name, user and password.
3. **Mailbox**: Emails → create `portal@daisydigitalagency.com`. Note the password.
   Also set the PHP version to 8.3 under Websites → PHP configuration.

## 2. Connect with SSH

hPanel → Advanced → SSH access shows the host, port and user.

    ssh -p PORT uXXXXXXX@HOST

## 3. Get the code onto the server

    cd ~/domains/daisydigitalagency.com
    git clone https://github.com/daisymangue/daisy-digital-portal.git portal-app
    cd portal-app
    composer install --no-dev --optimize-autoloader

GitHub asks for your username and a personal access token (same as on the Mac).
Run `git config credential.helper store` first so it only asks once.
The built CSS and JavaScript are committed, so the server never needs npm.

## 4. Point the subdomain at the app's public folder

    cd ~/domains/daisydigitalagency.com
    rm -rf public_html/portal
    ln -s ~/domains/daisydigitalagency.com/portal-app/public public_html/portal

(If the subdomain was created with its own folder elsewhere, replace that
folder with the symlink in the same way.)

## 5. Configure

    cd ~/domains/daisydigitalagency.com/portal-app
    cp .env.example .env
    nano .env

Set at least:

    APP_NAME="Daisy Digital Agency"
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://portal.daisydigitalagency.com

    DB_CONNECTION=mysql
    DB_HOST=localhost
    DB_DATABASE=uXXXXXXX_portal
    DB_USERNAME=uXXXXXXX_portal
    DB_PASSWORD=...

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.hostinger.com
    MAIL_PORT=465
    MAIL_ENCRYPTION=ssl
    MAIL_USERNAME=portal@daisydigitalagency.com
    MAIL_PASSWORD=...
    MAIL_FROM_ADDRESS=portal@daisydigitalagency.com
    MAIL_FROM_NAME="Daisy Digital Agency"

Then:

    php artisan key:generate
    php artisan migrate --force
    php artisan db:seed --force          # loads your services, packages and step defaults
    php artisan portal:make-admin "Your name" daisydigitalagency@gmail.com
    php artisan optimize

## 6. The scheduler (reminders, care plan billing, emails)

hPanel → Advanced → Cron jobs → add one job, every minute:

    cd ~/domains/daisydigitalagency.com/portal-app && php artisan schedule:run >> /dev/null 2>&1

## 7. Every update after that

    cd ~/domains/daisydigitalagency.com/portal-app
    git pull
    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan optimize

## Check it worked

    php artisan portal:check

walks through everything (database, mail, admin exists, scheduler ran) and
says what to fix. Then open https://portal.daisydigitalagency.com, sign in,
and send yourself a password reset email to confirm mail works.

## Worth knowing

- Set your bank details and VAT rate in the portal under Settings.
- Client files are stored in `storage/app`, outside the web root, so only
  signed-in users can download them.
- Hostinger mailboxes can send about 100 emails a day. Fine for the portal;
  don't use the same mailbox for newsletters.
- Never edit `.env` on the Mac and the server to be the same file; each has
  its own. `.env` is never in git.
