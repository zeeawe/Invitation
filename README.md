# Women's Health Summit 2026 VIP Invitation

Standalone invitation and attendance confirmation site. It does not integrate with or modify the WHS website. The invitee form collects a full name, professional title / position, phone number, and email address, with an optional note of up to 500 characters.

## Hosting Requirements

- PHP 8.1 or later with `PDO_SQLite` and `mbstring` enabled.
- An HTTPS-enabled subdomain whose document root can be set to this project's `public/` directory. Preserve `hero/WHS-Egypt-2026-VIP-Invitation.jpeg` beside `public/` for the hero image and `public/assets/invitation.jpeg` for downloads.
- PHP write permission for this project's `var/` directory (the app creates it with private permissions if it does not exist).
- Personalized invitation rendering uses browser Canvas and does not require PHP GD or Imagick.
- A configured outbound PHP mail transport and an authorized sender address for RSVP notifications to `contact@whs-egy.com`.
- Outbound access to Google Fonts for the selected typefaces. The page remains usable with local fallback fonts if fonts are unavailable.

The hosting provider, DNS, subdomain document root, SSL certificate, and available PHP extensions are not represented in this repository and have not been verified. Confirm these requirements with the host before deployment. If the host does not support PDO SQLite, this build is not compatible without an explicitly scoped database adaptation; do not point the public document root at the project root.

## Setup

1. Create `invitation.whs-egy.com` with the hosting provider, point its DNS to the hosting account, enable SSL, and set the subdomain's document root to this project's `public/` directory. The DNS record and certificate are separate hosting steps; uploading these files does not create them.
2. Copy `config.example.php` to `config.php` in the project root, outside the public document root.
3. Generate a strong, unique admin password hash using PHP CLI:

	```sh
	php -r 'echo password_hash("REPLACE_WITH_A_LONG_UNIQUE_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
	```

	Replace `REPLACE_WITH_A_PASSWORD_HASH` in `config.php` with the generated hash. Do not use the example string as a real password. Keep `config.php` private and do not commit it.
4. Set `mail_from` in `config.php` to a sender address authorized by the hosting provider. Configure its PHP mail transport (or the hosting account's outbound mail service); the app sends a notification to `notification_email` after each successfully saved RSVP. A successful on-page confirmation means the RSVP was saved; actual email delivery depends on the host's mail transport and sender-domain configuration.
5. Ensure the PHP process can write to the project's `var/` directory. The SQLite database is created automatically on the first request and is outside the public document root.
6. Upload the project while preserving the directory structure and supplied invitation artwork, including `hero/WHS-Egypt-2026-VIP-Invitation.jpeg` beside `public/` and `public/assets/invitation.jpeg`. Set the runtime PHP version to 8.1 or later and verify that `pdo_sqlite` and `mbstring` are enabled. Check that `https://your-subdomain/invitation-image.php` returns the hero image and that `https://your-subdomain/invitation-image.php?download=1` downloads the invitation from `public/assets/invitation.jpeg`.
7. Visit the subdomain over HTTPS, submit a test confirmation, then visit `/admin.php` and sign in with the password used to create the hash. Use **EXPORT CSV** to download an Excel-compatible attendee file. Remove any test record directly from the private database before launch.

The administrator password hash is the only required secret. Attendee records and SQLite files are never served from the configured public document root. Back up the private database securely and restrict access to deployment files.

## Local Development

After creating `config.php` as above, run:

```sh
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080`. The local PHP environment must have the same `PDO_SQLite` and `mbstring` extensions.

## Included Routes

- `/` — public invitation and attendance confirmation.
- `/personalized-invitation.php` — session-bound personalized invitation and download after a successful confirmation.
- `/admin.php` — password-protected attendee review and CSV export.

The app rejects invalid or oversized input, uses prepared statements and CSRF tokens, escapes displayed data, and enforces a unique normalized name/title pair to prevent accidental duplicate confirmations. Phone numbers and email addresses are validated before saving. Existing attendee databases are migrated additively to store optional notes, phone numbers, and email addresses without deleting prior records. Names, titles, contact details, optional notes, and the confirmation timestamp are available only in the authenticated admin view and CSV export.