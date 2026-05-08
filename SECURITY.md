# Security policy

## Supported versions

Security fixes are applied to the latest release on the default branch. Use the newest tagged version where possible.

## Reporting a vulnerability

**Please do not open a public GitHub issue for undisclosed security problems.**

Instead, contact the maintainers privately:

1. Use [GitHub Security Advisories](https://github.com/duppins-technology/gravity-forms-tops/security/advisories/new) if enabled for this repository, **or**
2. Email the repository owner with a clear subject line (for example: `Security: gravity-forms-tops`).

Include:

- A short description of the issue and its impact
- Steps to reproduce (proof-of-concept if safe to share)
- Affected versions or commit, if known

We aim to acknowledge reports within a few business days.

## Sensitive data in this plugin

- TowX **password** (`tops_password`) and **authentication key** (`tops_auth_key`) are stored in Gravity Forms **form meta** (same database as your WordPress site). Current releases encrypt **only these two fields** **at rest** using Gravity Forms’ built-in crypto (**AES-256-CTR** with **HMAC-SHA-512**, keyed from WordPress salts via `GFCommon::openssl_encrypt`). **TowX User ID** (`tops_user_id`) and **Session ID** (`tops_session_id`) live in the same form meta but are **not encrypted** by this plugin—they remain readable if someone can read the database.
- Anyone who can read both your **database** and **`wp-config.php`** (or equivalent secrets) can still decrypt the encrypted credential fields—this is the usual WordPress trade-off for reversible secrets. Restrict **form administrator** access, use **HTTPS** for wp-admin, and follow your host’s database security practices.
- Older installs may still hold **plaintext** password/key values until each form’s TOPS settings are saved again (migration is automatic on save).
- Gravity Forms may embed the full form object (including add-on settings) in **admin JavaScript** on the form settings screen. This is core Gravity Forms behavior; mitigate with strict admin access and auditing.

## Out of scope

- Issues in **Gravity Forms** core or other third-party plugins (report to the respective vendor)
- TowX / TowXchange API availability or certificate configuration on their infrastructure
- Brute-force attacks mitigated by WordPress authentication and server-level protections
