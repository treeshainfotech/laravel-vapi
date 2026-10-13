# Changelog

All notable changes to `treeshainfotech/laravel-vapi` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-10-13

### Added

- `ServerMessageTypeMap::TRANSCRIPT_FINAL` for the `transcript[transcriptType="final"]`
  discriminator. Vapi sends final transcripts under their own `message.type`, so code
  comparing the raw type string against `TRANSCRIPT` alone silently missed them. Both
  discriminators already deserialized to `ServerMessageTranscript` and dispatched
  `TranscriptReceived`, so listeners matching on the event were never affected.
- Tests asserting that every publicly exposed type constant still resolves, and that
  every case of the SDK's `ServerMessageTranscriptType` has a corresponding constant —
  a new transcript discriminator upstream now fails the suite instead of passing silently.
- `homepage` and `support` metadata in `composer.json` for Packagist.

### Changed

- The README events table lists both transcript discriminators, and the upstream
  limitations section links the open SDK issue about redacted credentials
  ([VapiAI/server-sdk-php#4](https://github.com/VapiAI/server-sdk-php/issues/4)).
- `CONTRIBUTING.md` and `bin/` are `export-ignore`d, keeping contributor tooling out
  of the distributed package.

## [0.1.0] - 2026-08-27

Initial release: a Laravel wrapper around the official
[Vapi PHP server SDK](https://github.com/VapiAI/server-sdk-php) (`vapi/vapi ^2.0`).

### Added

- `Vapi` facade and `VapiManager` exposing all 15 SDK resource clients, bound as a
  container singleton with a `vapi` alias.
- Configuration file (`config/vapi.php`) covering the API key, base URL, timeout,
  retries, and custom headers, with a bring-your-own PSR-18 client hook.
- `Vapi::raw()` — typed JSON access to the ~58 Vapi endpoints the SDK does not
  model yet, reusing the configured authentication, base URL, retries, and client.
- Webhook pipeline: `VapiWebhookController`, the `VerifyVapiWebhook` middleware
  (shared secret, bearer token, and HMAC signature verification, failing closed),
  and `WebhookProcessor` for custom controllers.
- `Route::vapiWebhook()` macro and `WebhookRoute::register()` for placing the
  webhook route anywhere, with a configurable path and route name.
- `ServerMessageTypeMap`, which deserializes webhooks by their `message.type`
  discriminator into the correct SDK class, covering all 27 server message types.
- Events: `WebhookReceived` for every message, plus `EndOfCallReportReceived`,
  `StatusUpdateReceived`, `TranscriptReceived`, and `ToolCallsReceived`.
- `RespondsToVapiWebhooks` contract for returning a `ServerMessageResponse`.
- 40 generated fluent request builders (`composer build:builders`), with enum-typed
  setters and required fields exposed as `make()` arguments.
- `Vapi::fake()` testing helper with request recording and assertions.

[Unreleased]: https://github.com/treeshainfotech/laravel-vapi/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/treeshainfotech/laravel-vapi/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/treeshainfotech/laravel-vapi/releases/tag/v0.1.0
