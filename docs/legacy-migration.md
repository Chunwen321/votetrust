# Legacy-to-Laravel migration map

This document records how the source in `votetrust-voting-system` was consolidated into the Laravel application. The source repository was kept intact; cleanup applies to what was admitted into the new runtime.

## Shared decisions

- The Laravel convention `users` with `password` is canonical. Legacy `user.password_hash` behavior was translated to Laravel hashing rather than creating a second user table.
- The two incompatible legacy meanings of `voter` were separated. `users` holds identities, while `voter_enrollments` records approval for a particular election.
- Shared-primary-key organizer and candidate roles became `organizers.user_id` and `candidates.user_id`.
- Legacy singular table names were normalized to plural Laravel names.
- Candidate status is a string that supports `Pending`, `Approved`, `Rejected`, and the previously missing `Cancelled` value.
- Database triggers and MySQL-only setup scripts became application services, transactions, migrations, constraints, and seeders.
- Native PHP sessions, custom CSRF, raw PDO, hand-written routers, and local autoloaders were replaced by Laravel facilities.

## Chew Zhexuan — user, voter, and administrator module

| Legacy source | Laravel destination |
|---|---|
| `app/Controllers/*` | `app/Http/Controllers`, `app/Http/Controllers/Admin`, and `app/Http/Controllers/Api` |
| `app/Models/*Repository.php` | Eloquent models plus `app/Services/UserService.php`, `VoterService.php`, and `AdminManagementService.php` |
| Authentication/session strategies | Explicit Laravel-bound login-protection and full-session-termination Strategy implementations under `app/Security` |
| `app/Support/Identity.php` | `app/Support/Identity.php` |
| PHP templates | Blade views under `resources/views/auth`, `profile`, `voter`, and `admin` |
| `routes/web.php`, `routes/api.php` | `routes/user.php`, `routes/api-user.php` |
| `database/schema.sql` | user extension and `voter_enrollments` migrations |
| `public/assets/app.css` | scoped `resources/css/modules/user.css` |

The unused self-service voter application view and duplicate `/elections` voter route were not retained. The User module provides eligibility and consumes Voting participation status through REST rather than reading Voting-owned records.

## Low Wen Kai — election management module

| Legacy source | Laravel destination |
|---|---|
| Controllers | `ElectionController`, `AdminElectionController`, and election API controllers |
| Custom ORM models | Shared Eloquent models in `app/Models` |
| `Services`, `States`, `ValueObjects` | Corresponding Laravel `app` namespaces; the State pattern is retained |
| Raw cURL payment adapter | Laravel HTTP-client `HttpPaymentGateway` |
| Clock abstractions | `app/Contracts/Clock.php` and `app/Services/Clock/SystemClock.php` |
| PHP templates | Blade views under `resources/views/elections` and `admin/elections` |
| Legacy routes | `routes/election.php` and `routes/api-election.php` |
| Schema/setup scripts | shared migrations and `VoteTrustDemoSeeder` |
| CSS/JavaScript | scoped election CSS and `resources/js/election-form.js` |

The custom framework, ORM, payment stub, generated logs/sessions, standalone test harness, and 12.7 MB assignment report were excluded from runtime code. Election now provides availability/ballot REST contracts and consumes the separate Payment service with a fail-closed HTTP gateway.

## Wong Shun Bin — candidate registration module

| Legacy source | Laravel destination |
|---|---|
| Registration contract/proxy/service | `app/Contracts/RegistrationService.php` and `app/Services/CandidateRegistration` |
| Procedural public entry points | Candidate controllers and Blade views |
| File validators and upload logic | Laravel Form Requests, validation rules, and private Storage service |
| Candidate schema extension | candidate/candidacy application migration |
| Routes | `routes/candidate.php` |
| CSS | scoped `resources/css/modules/candidate.css` |

The empty `testing.php`, built-in server router, duplicate unreachable profile branch, hard-coded candidacy IDs, public photo leakage, and orphan-producing upload behavior were removed or corrected. Candidate provides minimal candidate/organisation information to Payment and consumes remote payment status through a strict HTTP client.

## Goh Chun Wen — voting process module

| Legacy source | Laravel destination |
|---|---|
| `Controller/VotingController.php` | Laravel `VotingController` and `Api/VotingApiController` |
| `Model/Vote.php`, `VoteORM.php` | Eloquent `Vote` model, migration, and transactional core vote service |
| Decorated vote service | Explicit `AuthorizationDecorator` and `DuplicateVoteDecorator` around `BasicVoteService` |
| External API adapter | `VotingIntegration` contract and Laravel HTTP consumer for User and Election REST providers |
| PHP templates | Blade views under `resources/views/voting` |
| Legacy routes/front controller | `routes/voting.php` and `routes/api-voting.php` |
| SQL and test runner | shared migrations/seeder and PHPUnit tests |
| CSS | scoped `resources/css/modules/voting.css` |

The custom autoloader, raw view renderer, PDO configuration, front controllers, built-in server router, and duplicate SQL scripts were not copied.

The current security and design-pattern evidence for this module is recorded in `docs/voting-process-compliance.md`.

## Excluded root/runtime artifacts

- Legacy `.git`, `.tmp`, and per-module `.env` files
- Root `db.php` with hard-coded database access
- Custom `autoload.php`, `public/index.php`, `router.php`, `.htaccess`, and `bin/setup.php` files
- Generated session files, security/test logs, cached views, and payment-stub JSON
- Duplicate integrated SQL dumps after their schema/data were represented by migrations and seeders
- Empty placeholders and obsolete structural test harnesses

## PIC convention

Migrated PHP, Blade, CSS, JavaScript, configuration, route, migration, seeder, and test files contain a `PIC:` comment. Shared files use scoped comments for the contributor-specific block instead of assigning the whole file to one person.
