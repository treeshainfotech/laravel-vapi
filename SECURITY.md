# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | ✅        |

This package is pre-1.0. Security fixes land on the latest `0.1.x` release.

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report privately through GitHub's
[security advisory form](https://github.com/treeshainfotech/laravel-vapi/security/advisories/new).
Private vulnerability reporting is enabled on this repository, so the report stays
between you and the maintainers until a fix ships.

Please include the package version, your PHP and Laravel versions, and the steps to
reproduce. You can expect an acknowledgement within a few days, and we will keep you
updated as we work on a fix. If the report is valid we will credit you in the advisory
unless you would rather stay anonymous.

## Scope

This package is a wrapper around the official
[Vapi PHP server SDK](https://github.com/VapiAI/server-sdk-php). Vulnerabilities in the
SDK itself, or in the Vapi API, belong upstream — report those to Vapi directly.

Issues in **this** package's own code are in scope. The areas most worth scrutiny:

- **Webhook verification** (`src/Http/Middleware/VerifyVapiWebhook.php`) — shared-secret
  and HMAC signature checking. The middleware fails closed when nothing is configured.
- **Webhook processing** (`src/Webhooks/`) — payload decoding and type dispatch.
- **Credential handling** (`src/VapiServiceProvider.php`, `src/RawVapiClient.php`) — how
  the API key reaches outbound requests.

## Hardening notes for users

- Keep the webhook route out of the `web` middleware group, as the package does by
  default, so session and CSRF handling never apply to it.
- Configure `VAPI_WEBHOOK_SECRET` or `VAPI_WEBHOOK_HMAC_SECRET`. With neither set, the
  middleware rejects every request with a 500 rather than accepting unverified ones.
- Never commit `VAPI_API_KEY`. It belongs in your environment, and the published config
  reads it from there.
