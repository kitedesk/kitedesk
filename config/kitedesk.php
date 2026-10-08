<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | The languages people can pick for themselves in their profile. Without a
    | choice, the installation language (APP_LOCALE) is used. Each one needs a
    | translation file in lang/.
    |
    */

    'locales' => [
        'en' => 'English',
        'pt_BR' => 'Português (Brasil)',
    ],

    /*
    |--------------------------------------------------------------------------
    | First Administrator
    |--------------------------------------------------------------------------
    |
    | Until the installation has staff, visitors are sent to a setup screen
    | that names the helpdesk and creates the first administrator, guarded
    | by the code `php artisan kitedesk:setup` prints. "setup_screen" turns
    | it off for installations that create their administrators elsewhere.
    |
    | `php artisan kitedesk:setup` (run by the Docker image on start) can
    | instead create "first_admin" from the environment, skipping the screen.
    |
    */

    'setup_screen' => true,

    'first_admin' => [
        'name' => env('KITEDESK_ADMIN_NAME') ?: 'Administrator',
        'email' => env('KITEDESK_ADMIN_EMAIL'),
        'password' => env('KITEDESK_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Requests
    |--------------------------------------------------------------------------
    |
    | Allow people without an account to submit requests and follow them
    | through emailed links.
    |
    */

    'guest_tickets' => (bool) env('KITEDESK_GUEST_TICKETS', true),

    /*
    |--------------------------------------------------------------------------
    | Email Channel
    |--------------------------------------------------------------------------
    |
    | "banlist": senders whose mail is never turned into tickets (exact
    | addresses or "@domain" entries).
    |
    | "authserv_ids": the servers whose Authentication-Results header is
    | trusted to vouch for the sender (e.g. "mx.google.com"). Leave empty to
    | trust the topmost one, which your mail provider adds on arrival.
    |
    | "max_copied_people": at most this many To/Cc addresses of a new email
    | become people copied on the ticket.
    |
    | "message_id_host" / "message_id_key": the host and signing key of the
    | Message-IDs we send, which lead replies back to their ticket. They
    | default to APP_URL's host and APP_KEY.
    |
    */

    'mail' => [
        'banlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('KITEDESK_MAIL_BANLIST', ''))))),
        'authserv_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('KITEDESK_MAIL_AUTHSERV_IDS', ''))))),
        'max_copied_people' => (int) env('KITEDESK_MAIL_MAX_COPIED_PEOPLE', 10),
        'message_id_host' => env('KITEDESK_MAIL_MESSAGE_ID_HOST'),
        'message_id_key' => env('KITEDESK_MAIL_MESSAGE_ID_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound Connections
    |--------------------------------------------------------------------------
    |
    | Webhook URLs and IMAP hosts must point to public internet addresses, so
    | they can't be used to reach internal services. Self-hosted installs that
    | deliver to intranet hosts can allow private addresses here.
    |
    */

    'webhooks' => [
        'allow_private_targets' => (bool) env('KITEDESK_WEBHOOKS_ALLOW_PRIVATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secrets
    |--------------------------------------------------------------------------
    |
    | Passwords and other secrets exchanged with customers. They are encrypted
    | with "key" (base64: prefixed, 32 bytes) before being stored; when it is
    | empty, a key derived from APP_KEY is used. Agents pick the expiry and
    | how many times a secret may be viewed, within these limits.
    | "max_length" is in characters.
    |
    */

    'secrets' => [
        'key' => env('KITEDESK_SECRETS_KEY'),
        'max_length' => (int) env('KITEDESK_SECRETS_MAX_LENGTH', 10000),
        'max_expiry_days' => (int) env('KITEDESK_SECRETS_MAX_EXPIRY_DAYS', 30),
        'max_views' => (int) env('KITEDESK_SECRETS_MAX_VIEWS', 10),
    ],

];
