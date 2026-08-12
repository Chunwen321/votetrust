# VoteTrust

VoteTrust is the Laravel 12 consolidation of the original PHP voting-system modules. The legacy source remains unchanged at `C:\Users\gohch\source\repos\votetrust-voting-system`; the working application is this repository.

Open the integrated application at **http://votetrust.test**.

## Module ownership and assignment scope

| PIC | Module | Unique pattern | Main security controls |
|---|---|---|---|
| Chew Zhexuan | User, voter, and administration | Strategy | Complete session termination; failed-login account disabling |
| Low Wen Kai | Election management | State | Critical-operation reauthentication; payment/lifecycle business-rule enforcement |
| Wong Shun Bin | Candidate registration | Proxy | Private validated uploads; Blade output encoding and owner checks |
| Goh Chun Wen | Voting process | Decorator | API-backed authorization/IDOR prevention; synchronized one-vote enforcement |
| Puah Kaho Risky | Payment | Facade | Separate teammate module; its source was not present in the legacy folder |

Every migrated module uses Laravel MVC, Eloquent ORM, Form Requests/input validation, PIC comments, and both provides and consumes a tracked REST web service.

## REST integration map

All requests carry `requestID` and an ISO-8601 `timestamp`. All responses carry `status`, a correlated `requestID`, and a response-generation `timestamp`. Consumers validate the complete envelope, correlation, timestamp freshness, HTTP result, and module-specific data; invalid or unavailable services fail closed.

| Module | Provides | Consumes |
|---|---|---|
| User | `GET /api/v1/voters/eligibility` | Voting `GET /api/v1/votes/status` |
| Election | `GET /api/v1/elections/available`, `GET /api/v1/elections/{id}/availability`, admin approval | Payment verification at `PAYMENT_API_URL` |
| Candidate | `GET /api/v1/candidates/{id}/payment-information` | Payment `GET /api/v1/payments/{paymentId}/status` |
| Voting | `GET /api/v1/votes/status`, `GET /api/v1/elections/{id}/tally` | User eligibility and Election availability/ballot APIs |
| Payment | Expected to provide election/candidate payment status | Candidate/organisation information endpoint above |

The Payment service is an external teammate boundary until the Payment module is added. Candidate and Election payment-sensitive operations deliberately report unavailable rather than trusting local payment-table state when that service is absent.

## Requirements

- PHP 8.2+
- Composer 2
- Node.js and npm
- MySQL 8+ for the local application (tests use in-memory SQLite)
- Apache/Nginx or another concurrent web server for module-to-module HTTP calls

## Setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm.cmd run build
```

Set MySQL credentials and unique API keys in `.env`. Matching provider/consumer keys are required, for example `USER_API_KEY` with `USER_SERVICE_API_KEY`, and `VOTING_API_KEY` with `VOTING_SERVICE_API_KEY`.

### Laragon URL

The repository can be cloned anywhere. Link the clone into Laragon once:

```powershell
composer run laragon:link
```

This creates a Windows directory junction at `C:\laragon\www\votetrust` that points to the current clone. Start or reload Laragon, then open **http://votetrust.test**. Future Git pulls are reflected immediately through the same link.

If `C:\laragon\www\votetrust` already contains another project, the command stops without changing it. After checking that folder, preserve it and create the link with:

```powershell
composer run laragon:link -- -BackupExisting
```

For Laragon installed elsewhere, run the script directly with `-LaragonRoot`, or set `VOTETRUST_LARAGON_ROOT` before using the Composer command:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/setup-laragon.ps1 -LaragonRoot 'D:\laragon'
```

Alternatively, clone the repository directly as `C:\laragon\www\votetrust`; no junction is needed. If using `php artisan serve`, use a separate provider process or multiple CLI-server workers so a request does not call back into a single blocked worker.

## Demo accounts

`php artisan db:seed` creates the integrated demo data without depending on fixed IDs.

| Role | Username | Password |
|---|---|---|
| Organiser | `LowWenKaiOrganizer` | `VoteTrust!2026` |
| Administrator | `SystemAdmin` | `VoteTrustAdmin!2026` |
| Candidate | `CandidateAisha` | `Candidate!2026` |
| Candidate | `CandidateDaniel` | `Candidate!2026` |
| Voter | `VoterJohn` | `VoteTrustVoter!2026` |

Additional voters are `VoterAmina`, `VoterBenjamin`, `VoterChen`, `VoterDevi`, and `VoterFarah`, using the same voter password.

## Main pages

- `/` — integrated public landing page
- `/dashboard` — member dashboard
- `/elections` — organiser election workspace
- `/candidate/applications/create` — candidate application
- `/voting` — secure voter portal
- `/admin` — administrator dashboard
- `/api/v1/...` — module REST providers

## Verification

```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd run build
```

Current verified result: **58 tests, 271 assertions**, production assets built, 47 routes registered, and `http://votetrust.test/` returns HTTP 200.

See [docs/legacy-migration.md](docs/legacy-migration.md) for the detailed migration/cleanup map, [docs/web-service-ifa.md](docs/web-service-ifa.md) for the provider/consumer interface agreements, and [docs/didit-ekyc-setup.md](docs/didit-ekyc-setup.md) to configure the hosted document/selfie eKYC flow.
