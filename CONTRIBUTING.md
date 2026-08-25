# Contributing

## Quality gates

Every change must pass the full gate suite:

```sh
composer lint            # Pint (Laravel preset + strict rules), check only
composer fix             # Pint, apply fixes
composer analyse         # PHPStan level max + Larastan + strict rules (zero suppressions)
composer test            # Pest: unit, feature, arch presets (php/strict/security)
composer test:types      # Pest type coverage, 100% required
composer check           # all of the above
```

CI runs the suite on PHP 8.3/8.4/8.5 × Laravel 12/13. The policy is zero `@phpstan-ignore` comments and no baseline — if the analyser complains, fix the code.

## Generated builders

The fluent builders in `src/Builders/` are emitted by `src/Builders/Generator/BuilderGenerator.php` — **never edit them by hand**. The generator auto-discovers its targets from the SDK's client method signatures and regenerates with:

```sh
composer build:builders
```

DTOs that cannot be typed soundly (private constructors, required fields with unclassifiable types) are skipped by design; prefer no setter over a `mixed` one.

## Upgrading the Vapi SDK

After `composer update vapi/vapi`, two drift tests protect the package:

1. **Builder drift** (`tests/BuildersTest.php`) — if DTO shapes changed, the test fails until you run `composer build:builders` and commit the regenerated files.
2. **Webhook type map drift** (`tests/Webhooks/TypeMapTest.php`) — if the SDK learned new server message types, the test fails and names the exact types to add to `ServerMessageTypeMap` (plus a new typed event in `src/Events/`, if the type warrants one).

Nothing regenerates silently; an SDK upgrade is always a visible, reviewed change. When the SDK catches up to API features previously reachable only via `Vapi::raw()`, consider promoting README examples to the typed clients.

## Commit conventions

Conventional commit prefixes (`feat:`, `fix:`, `chore:`), no co-author trailers.
