# KiteDesk

**An open-source, self-hosted helpdesk.** KiteDesk gives your support team a fast shared inbox for email and web requests, gives your customers a portal and a help center, and keeps everyone on time with SLAs, automation and AI assistance. You run it on your own server and keep your own data.

Built with Laravel 13, Inertia v3, React 19, Tailwind CSS 4 and shadcn/ui. The interface is available in English and Portuguese (Brazil).

- [Features](#features)
- [Getting started](#getting-started)
- [Running in production](#running-in-production)
- [API, webhooks and MCP](#api-webhooks-and-mcp)
- [How it's built](#how-its-built)
- [Contributing](#contributing)
- [License](#license)

## Features

### For agents

- **Shared inbox:**
    - Views with live counts: yours, unassigned, your groups, SLA breaching, pending, solved.
    - Saved views, filters, bulk actions and a ⌘K command palette.
- **Ticket page:**
    - Public replies and internal notes, attachments, tags and custom fields.
    - "Submit as Open / Pending / On-hold / Solved".
    - The requester's details and history, and the full audit trail.
    - Live notices when a teammate is viewing or replying ("Ana is also here").
- **Kanban board:** show any queue as a board grouped by status, priority, assignee or group. Drag cards between lanes to update the ticket.
- **Faster replies:**
    - Canned responses with placeholders, and a personal signature.
    - In the editor, `@` mentions a teammate, `#` references a ticket and `/` inserts a canned response.
- **Teamwork:**
    - CC and collaborators, and forwarding to an agent or group with a handover note.
    - Ticket merge and related tickets.
    - Round-robin or least-busy auto-assignment that respects an "Away" status.
- **Secrets:** request or share a password or key through a card sent with a reply.
    - Secrets never go into emails or the ticket thread.
    - They are encrypted with their own key, and are wiped after a view limit or an expiry.

### For customers

- **Customer portal:** submit requests, follow the conversation, reply, and mark requests as solved. While a customer types the subject, matching help articles are suggested.
- **Guest requests:** people without an account submit requests and follow them through links sent by email. Cloudflare Turnstile CAPTCHA is optional.
- **Help center:** categories, sections and articles, with instant search and "Was this helpful?" feedback.
- **Satisfaction surveys (CSAT):** a one-click 1–5 star email after a ticket is solved, with an optional comment. Results appear in reports by agent, group, category and channel.

### Channels

- **Email:**
    - Connect any number of mailboxes, through IMAP or Postmark/Mailgun inbound webhooks.
    - Emails become tickets or join existing ones, and replies go out from the same mailbox with correct threading.
    - Email templates and auto-responses are editable.
    - Sender authentication (DMARC, DKIM, SPF) is checked before an email joins an existing ticket.
- **Web:** the customer portal, guest requests and the help center.
- **API:** create and update tickets from your own systems.

### Workflow and automation

- **SLAs:**
    - First response, next reply and resolution targets per priority, measured in business hours with holidays and timezones.
    - The clock pauses while a ticket is Pending or On-hold.
    - Breach alerts go to the assignee, the group or the admins.
- **Categories, forms and routing:**
    - A request's category decides which ticket form (set of fields) it shows.
    - Routing rules set the group, priority and tags from the category, channel, organization, requester or any field.
- **Workflows:** a visual builder for automation.
    - **Triggers:** a ticket is created or updated, a customer or agent replies, a note is added, an SLA is breached, a ticket is idle ("no reply for 3 days"), or an agent runs it by hand.
    - **Steps:** conditions, branches, loops, waits, emails, notifications, HTTP requests and ticket updates.
- **Custom statuses:** add your own statuses, such as "Waiting on vendor", inside fixed categories. The category controls SLA pauses, reopening and auto-close.
- **Ticket numbers:** choose your own format and reset period.

### AI assistance (optional, bring your own model)

- **Connection:** any OpenAI-compatible endpoint, configured in the admin center. The API key is encrypted at rest.
- **For agents:** ticket summaries, reply drafts, and "improve my draft".
- **In workflows:** classify tickets, write internal notes, or run your own prompts.
- **MCP server:** let Claude, ChatGPT and other MCP clients search, read, update and reply to tickets, and search help articles. Clients sign in with OAuth or an API token.

### Administration

- **People:**
    - Staff and customers, groups, and organizations matched by email domain.
    - A page for each person with their tickets, security status and activity.
    - Deactivate someone without losing their history.
- **Roles and permissions:**
    - Choose what each staff role may do, and which tickets its members see: all, their groups', or only their own.
    - Built-in Administrator, Agent and Light agent roles.
- **Account security:** two-factor authentication, passkeys, a list of browser sessions, and sign-out from other sessions.
- **Personal settings:** each person chooses their own language, timezone, photo and signature.
- **Branding:** set the name, logo (with a dark-mode version), favicon, brand color, email footer and help center text, with no rebuild.
- **Reports:** volume, response and resolution times, SLA and satisfaction, with CSV export.
- **Notifications:** email and in-app alerts for assignments, replies, mentions and SLA breaches.

## Getting started

### Run with Docker

To try KiteDesk, run a single container that keeps everything, including an SQLite database, in one volume:

```bash
docker run -d --name kitedesk -p 8080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8080 \
  -e DB_DATABASE=/app/storage/database.sqlite \
  -e QUEUE_CONNECTION=sync \
  -v kitedesk-storage:/app/storage \
  ghcr.io/kitedesk/kitedesk:latest

docker logs kitedesk    # shows the link to the setup screen
```

Open the link to name your helpdesk and create the first administrator. `QUEUE_CONNECTION=sync` sends emails and runs automations during the request, since this container runs no queue worker or scheduler.

For a real installation, use [`docker-compose.yml`](docker-compose.yml). It runs the web server, a queue worker and the scheduler, with PostgreSQL and Redis:

```bash
curl -O https://raw.githubusercontent.com/kitedesk/kitedesk/main/docker-compose.yml
echo "APP_KEY=base64:$(openssl rand -base64 32)" > .env
docker compose up -d
docker compose logs web    # shows the link to the setup screen
```

Add your mail server and any other setting from [`.env.example`](.env.example) to `.env`, and set `APP_URL` to the address people will use. Keep `APP_KEY` safe and never change it: it encrypts sessions and stored secrets. To update, run `docker compose pull && docker compose up -d`; migrations run when the web container starts.

### Run from source

You need:

- PHP 8.3 or newer, and Composer
- Node.js 22 or newer
- A database. SQLite works out of the box; use PostgreSQL or MySQL in production.

#### Try it with demo data

```bash
git clone <repository-url> kitedesk && cd kitedesk
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed    # creates the database and loads demo data
npm install
composer run dev              # app, queue worker, Vite, Reverb and logs
```

Open http://localhost:8000 and sign in with any demo account. They all use the password `password`.

| Email                | Role                              |
| -------------------- | --------------------------------- |
| admin@example.com    | Administrator                     |
| agent@example.com    | Agent                             |
| light@example.com    | Light agent (internal notes only) |
| customer@example.com | Customer                          |

#### Start a real installation

Run `php artisan kitedesk:setup` instead of `migrate --seed`. It migrates the database and prints a link to the setup screen, where you name your helpdesk and create the first administrator. The link carries a setup code, so only someone with access to the server can finish the setup. To skip the screen, set `KITEDESK_ADMIN_EMAIL` and `KITEDESK_ADMIN_PASSWORD` before running it, or run `php artisan kitedesk:create-admin`.

Then sign in. Use the **Admin center** to add mailboxes, invite your team, and set up SLAs and branding.

## Running in production

- **Environment:** set `APP_ENV=production`, `APP_DEBUG=false`, an `https` `APP_URL`, `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.
- **Cache, sessions and queue:** use Redis or another fast store for `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION`. The database drivers work, but then sidebar counts, sessions and the queue all hit the database.
- **Queue worker:** run at least one. Emails, notifications, webhooks, workflows and mailbox polling all run on the queue.
- **Scheduler:** run `php artisan schedule:run` every minute. It checks SLA breaches, polls mailboxes, sends surveys, resumes workflows, closes solved tickets and cleans up old data. With several servers, use a shared cache so each task runs once.
- **Realtime:** configure Laravel Reverb (`BROADCAST_CONNECTION=reverb`) for live updates.
- **Secrets key:** set `KITEDESK_SECRETS_KEY` so customer secrets don't depend on `APP_KEY`. See `.env.example` for how to generate one.
- **MCP:** `php artisan kitedesk:setup` creates the OAuth keys in `storage/`. With several servers that don't share `storage/`, set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` instead.
- **Proxies:** behind a load balancer or reverse proxy, set `TRUSTED_PROXIES`, so rate limits and HTTPS detection see the real client.
- **Spam:** if guest requests are open, set the `TURNSTILE_*` keys.
- **On every deploy:** run `php artisan kitedesk:setup` (migrations, plus the OAuth keys and the `KITEDESK_ADMIN_*` administrator on a new installation) and `php artisan optimize`.
- **Docker:** the image needs only environment variables, at least `APP_KEY` (generate one with `echo "base64:$(openssl rand -base64 32)"`). The web container runs `kitedesk:setup` and `optimize` on every start, and logs the setup screen's link until there is an administrator. Mount a volume on `/app/storage`. [`docker-compose.yml`](docker-compose.yml) is a working example.

Every setting in `.env.example` has a comment explaining it.

## API, webhooks and MCP

**REST API:** create a token in **Settings → API tokens**, then call the API with it:

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     "http://localhost:8000/api/v1/tickets?filter[status]=open"
```

- **What it covers:** tickets (replies, notes, attachments, CCs, tags, links, merge, forward, delete), ticket setup, users, organizations, groups, the help center, webhooks, satisfaction ratings and report numbers.
- **Tokens:** a token acts as the person who owns it and can never do more than their role allows. Admins can also create integration tokens in **Admin center → API tokens**, but only for people who can't do more than they can.
- **Abilities:** each token gets one or more of `tickets:read`, `tickets:write`, `tickets:merge`, `tickets:delete`, `users:read`, `users:write`, `setup:write`, `kb:read`, `kb:write`, `webhooks:manage` and `reports:read`.
- **Conventions:** lists are paginated and accept `filter[updated_since]` for incremental syncs. Timestamps are UTC. Send an `Idempotency-Key` header to retry a `POST` safely.

The full OpenAPI reference is at `/docs/api`.

**Webhooks:** each delivery is a `POST` with a JSON body of `{id, event, occurred_at, data, changes?}`. `data` holds the same objects the API returns. Deliveries are retried, logged and can be sent again, and webhooks can be managed through the API too. They carry these headers:

- `X-Support-Event`
- `X-Support-Delivery`
- `X-Support-Timestamp`
- `X-Support-Signature: sha256=HMAC_SHA256(secret, "{timestamp}.{raw body}")`

Verify the signature, and reject timestamps older than a few minutes.

**MCP:** turn on the MCP server in **Admin center → AI**, then add `https://your-domain/mcp` to your MCP client.

## How it's built

- **Domain modules:** business logic lives in `app/Domain` (`Tickets`, `Sla`, `Mail`, `Workflows`, `Ai`, `Accounts`, `KnowledgeBase`, `Webhooks`, `Secrets` and more). Controllers stay thin, and the agent UI, the portal, the API and the MCP server all call the same actions.
- **Frontend:** React pages live in `resources/js/pages`. Routes reach the frontend as typed functions generated by Laravel Wayfinder.
- **Translations:**
    - English text is the translation key, and Portuguese lives in `lang/pt_BR.json` and `lang/pt_BR/*.php`.
    - A test fails when a string in the interface has no translation.
    - To add a language, create its `lang/` files and add it to `kitedesk.locales` in `config/kitedesk.php`.
- **Security:**
    - Rich text is sanitized before it's stored.
    - Realtime broadcasts carry only ids, so internal notes never travel over the socket.
    - Outbound webhooks and mailboxes refuse private network addresses by default.
- **Editions:** this repository is the complete self-hosted edition, with every feature and no limits, for one help desk per installation. A hosted edition adds workspaces and billing through a separate package. It uses a few extension points in the core (`App\Domain\Support\Extensions`, `App\Domain\Entitlements`), and the core never depends on it.

## Contributing

Contributions are welcome: bug reports, fixes, translations, documentation and features.

### Before you start

- **Bugs:** open an issue with the steps to reproduce it, what you expected, and what happened. Include your PHP, database and browser versions.
- **Features and larger changes:** open an issue first to discuss the idea, before you write the code. This saves you from building something that doesn't fit the project.
- **Security issues:** don't open a public issue. Report them privately through GitHub's "Report a vulnerability" button on the Security tab.

### Development setup

Follow [Try it with demo data](#try-it-with-demo-data), then use these checks:

```bash
composer test           # Pint (code style), PHPStan and the Pest test suite
npm run check           # frontend lint and formatting
npm run types:check     # TypeScript
composer ci:check       # everything CI runs
```

`composer run lint` and `npm run check:fix` fix formatting automatically.

### Guidelines

- **Follow the existing code.** Look at sibling files before adding something new, and reuse existing components and actions.
- **Put business logic in `app/Domain`,** not in controllers, so the UI, API and MCP server share it.
- **Add tests** for any change in behavior, with Pest feature tests. Run them with `php artisan test --compact`, or pass a file path to run only the tests you need.
- **Translate every new interface string** into Portuguese (Brazil) in `lang/pt_BR.json`. The test suite checks this. If you don't speak Portuguese, say so in the pull request and a maintainer will help.
- **Ask before adding dependencies.** New Composer or npm packages need a reason in the pull request.
- **Keep pull requests focused.** One fix or feature per pull request, with a description of what changed and why, and screenshots for interface changes.
- **Make sure CI passes.** Run `composer ci:check` before you push.

By submitting a contribution, you agree that it is licensed under the same license as the project.

## License

KiteDesk is free software, licensed under the [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-only).

You may use, study, modify and share it. If you modify KiteDesk and let people use it over a network, you must also offer those people the source code of your modified version, under the same license.
