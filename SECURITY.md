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

- TowX **password** and **authentication key** are stored in Gravity Forms **form meta** (same database as your WordPress site). They are **not** encrypted at rest by this plugin. Restrict **form administrator** access, use **HTTPS** for wp-admin, and follow your host’s database security practices.
- Gravity Forms may embed the full form object (including add-on settings) in **admin JavaScript** on the form settings screen. This is core Gravity Forms behavior; mitigate with strict admin access and auditing.

## Out of scope

- Issues in **Gravity Forms** core or other third-party plugins (report to the respective vendor)
- TowX / TowXchange API availability or certificate configuration on their infrastructure
- Brute-force attacks mitigated by WordPress authentication and server-level protections
