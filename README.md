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
| User | Voter eligibility, private-roster import, directory, and selection APIs | Election summaries/availability/admin overview; Candidate participants/metrics; Voting status |
| Election | `GET /api/v1/elections/available`, `GET /api/v1/elections/{id}/availability`, admin approval | Payment verification at `PAYMENT_API_URL` |
| Candidate | Candidate payment information and participant administration APIs | Payment `GET /api/v1/payments/{paymentId}/status` |
| Voting | `GET /api/v1/votes/status`, `GET /api/v1/elections/{id}/tally` | User eligibility and Election availability/ballot APIs |
| Payment | Expected to provide election/candidate payment status | Candidate/organisation information endpoint above |

The Payment service is an external teammate boundary until the Payment module is added. Candidate and Election payment-sensitive operations deliberately report unavailable rather than trusting local payment-table state when that service is absent.

## Requirements

- PHP 8.2+
- PHP Zip extension (required for XLSX voter-list imports)
- Composer 2
- Node.js and npm
- MySQL 8+ for the local application (tests use in-memory SQLite)
- Apache/Nginx or another concurrent web server for module-to-module HTTP calls

## Setup

Before setup, enable PHP's `zip` extension, start MySQL, and create an empty database named `votetrust`. Laragon's default `root` user with no password already matches `.env.example`; if your database name or credentials differ, copy `.env.example` to `.env` and update the `DB_*` values first. Laravel migrations create tables, but they do not create the MySQL database itself.

With Laragon, enable Zip at `Menu > PHP > Extensions > zip`, restart the terminal, start MySQL, and create `votetrust` from Laragon's database tool or phpMyAdmin.

Once that one-time prerequisite is ready, a fresh clone can be prepared with:

```powershell
composer setup
```

This installs dependencies, creates `.env` when needed, generates the application key, migrates and seeds the database, and builds the frontend.

After an ordinary Git pull, use the conventional update path:

```powershell
composer install
php artisan config:clear
php artisan migrate --seed
npm install
npm.cmd run build
```

The equivalent fresh-clone workflow, when you prefer to run each step yourself, is:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
npm install
npm.cmd run build
```

The example file includes matching local-only keys for the User, Election, Voting, and Candidate services so the integrated application can call its own module APIs. Existing local `.env` files with blank internal keys receive the same local-only defaults from configuration after `php artisan config:clear`. Replace those example keys in any shared or deployed environment. Payment credentials remain blank because Payment is an external teammate service.

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

`php artisan db:seed` creates the integrated demo data without depending on fixed IDs. Sign in with the email address, not the display name.

| Role / purpose | Email | Password |
|---|---|---|
| Primary organiser - owns scenarios 01-03 and 05-09 | `lowwenkaiorganizer@votetrust.test` | `VoteTrust!2026` |
| Secondary organiser - owns scenario 04 for the administrator handoff | `organizeradmin@votetrust.test` | `VoteTrust!2026` |
| System administrator - election approvals, participant review, and member access | `systemadmin@votetrust.test` | `VoteTrustAdmin!2026` |
| Candidate - ready to apply in scenario 05 and appears on seeded ballots | `candidateaisha@votetrust.test` | `Candidate!2026` |
| Candidate - pending application for organizer review in scenario 05 | `candidatedaniel@votetrust.test` | `Candidate!2026` |
| Voter - approved and ready to cast in active elections | `voterjohn@votetrust.test` | `VoteTrustVoter!2026` |
| Voter - demonstrates the already-voted state | `voteramina@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / participant fixture | `voterbenjamin@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / private-roster fixture | `voterchen@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / private-roster fixture | `voterdevi@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / historical election fixture | `voterfarah@votetrust.test` | `VoteTrustVoter!2026` |
| Unverified member - tests the profile-verification gate and local eKYC | `unverifiedmember@votetrust.test` | `VoteTrustMember!2026` |
| Disabled member - tests administrator restoration; cannot sign in until restored | `disabledmember@votetrust.test` | `VoteTrustMember!2026` |

### Demo scenario gallery

The scenario dates are calculated relative to the time `php artisan db:seed` runs, so nomination and voting actions remain usable instead of expiring on fixed calendar dates.

| Seeded election | Immediate test |
|---|---|
| `[Demo] 01 - Editable Public Draft` | Edit a public draft, continue to hosting payment, or cancel it. |
| `[Demo] 02 - Private Roster Draft` | Search and assign private voters, import a roster, download the template, or continue to payment. |
| `[Demo] 03 - Awaiting Hosting Payment` | Pay the hosting fee, submit it for automatic election-ID matching, or cancel the request. |
| `[Demo] 04 - Awaiting Administrator Approval` | As the administrator, approve, return, or reject the request; as its organiser, withdraw it. |
| `[Demo] 05 - Candidate Review` | Apply as CandidateAisha, or review CandidateDaniel's pending application as the primary organiser. |
| `[Demo] 06 - Ready to Open Voting` | Open voting after the completed nomination and candidate-review gates. |
| `[Demo] 07 - Public Voting Open` | Cast a public-election vote as VoterJohn or inspect VoterAmina's already-voted state. |
| `[Demo] 08 - Private Voting Open` | Exercise approved-roster-only ballot access and voting. |
| `[Demo] 09 - Ready to Close` | Close an election whose configured voting period has ended. |
| `VoteTrust Student Council Election 2026` | Inspect a closed election with stable historical candidacies, enrolments, and votes. |

Organizer and administrator lifecycle forms require the current password shown above. Re-running `php artisan db:seed` refreshes the relative dates and resets the reserved demo personas and scenarios, so manual changes to those fixtures are not permanent.

Candidate payment status and application cancellation still require the external Payment API. Local payment rows alone deliberately do not enable those payment-dependent actions.

## Main pages

- `/` — integrated public landing page
- `/dashboard` — member dashboard
- `/elections` — organiser election workspace
- `/candidate/applications` — candidate application status and management
- `/candidate/elections/{election}/applications/create` — election-scoped candidate application form
- `/voting` — secure voter portal
- `/admin` — administrator dashboard
- `/api/v1/...` — module REST providers

## Verification

```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd run build
```

All three commands should complete successfully. Test and route counts change as the project evolves, so rely on the command output rather than a fixed count in this document. With Laragon running, also confirm that `http://votetrust.test/` returns the application home page.

See [docs/legacy-migration.md](docs/legacy-migration.md) for the detailed migration/cleanup map, [docs/web-service-ifa.md](docs/web-service-ifa.md) for the provider/consumer interface agreements, and [docs/didit-ekyc-setup.md](docs/didit-ekyc-setup.md) to configure the hosted document/selfie eKYC flow.

For the Voting module's real concurrent double-submission exercise, use [docs/voting-race-simulation.md](docs/voting-race-simulation.md).
