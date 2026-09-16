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

`php artisan db:seed` creates deterministic simulation data without depending on fixed IDs. The names and identity details are synthetic, all email addresses use the reserved `.test` domain, and fixed credentials are blocked in production. Sign in with the email address, not the display name.

| Role / purpose | Email | Password |
|---|---|---|
| Primary organiser (Nadia Iskandar) - owns scenarios 01-03 and 05-09 | `nadia.iskandar@votetrust.test` | `VoteTrust!2026` |
| Secondary organiser (Harith Zulkifli) - owns scenario 04 | `harith.zulkifli@votetrust.test` | `VoteTrust!2026` |
| System administrator - election approvals, participant review, and member access | `systemadmin@votetrust.test` | `VoteTrustAdmin!2026` |
| Candidate (Aisha Rahman) - ready to apply in scenario 05 and appears on seeded ballots | `aisha.rahman@votetrust.test` | `Candidate!2026` |
| Candidate (Daniel Tan) - pending application for organiser review in scenario 05 | `daniel.tan@votetrust.test` | `Candidate!2026` |
| Voter (Jason Lim) - approved and ready to cast in active elections | `jason.lim@votetrust.test` | `VoteTrustVoter!2026` |
| Voter (Amina Yusuf) - demonstrates the already-voted state | `amina.yusuf@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / participant fixture (Benjamin Lee) | `benjamin.lee@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / private-roster fixture (Wei Chen) | `wei.chen@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / private-roster fixture (Devi Nair) | `devi.nair@votetrust.test` | `VoteTrustVoter!2026` |
| Voter / historical election fixture (Farah Aziz) | `farah.aziz@votetrust.test` | `VoteTrustVoter!2026` |
| Unverified member (Siti Hajar) - tests the profile-verification gate and local eKYC | `siti.hajar@votetrust.test` | `VoteTrustMember!2026` |
| Disabled member (Arjun Kumar) - tests administrator restoration | `arjun.kumar@votetrust.test` | `VoteTrustMember!2026` |

### Demo scenario gallery

The scenario dates are calculated relative to the time `php artisan db:seed` runs, so nomination and voting actions remain usable instead of expiring on fixed calendar dates.

| Seeded election | Immediate test |
|---|---|
| `[Simulation] Student Representative Council 2026` | Edit a public draft, continue to hosting payment, or cancel it. |
| `[Simulation] Seri Harmoni Residents Committee 2026` | Search and assign private voters, import a roster, download the template, or continue to payment. |
| `[Simulation] Faculty of Computing Delegate 2026` | Pay the hosting fee, submit it for automatic election-ID matching, or cancel the request. |
| `[Simulation] Taman Murni Community Programme Lead` | As the administrator, approve, return, or reject the request; as its organiser, withdraw it. |
| `[Simulation] Student Welfare Officer 2026` | Apply as Aisha, or review Daniel's pending application as the primary organiser. |
| `[Simulation] Academic Affairs Representative 2026` | Run the scheduler to open voting after the completed nomination and candidate-review gates. |
| `[Simulation] Campus Sustainability Lead 2026` | Cast a vote as Jason or inspect Amina's already-voted state. |
| `[Simulation] Graduate Association Treasurer 2026` | Exercise approved-roster-only ballot access and voting. |
| `[Simulation] Alumni Network Coordinator 2026` | Run the scheduler to close an election whose configured voting period has ended. |
| `[Simulation] Student Council Election 2025` | Inspect a closed election with stable historical candidacies, enrolments, and votes. |

The organizer enters their current password once to start nominations at the configured time. Candidate reviews, emergency cancellation, and manual administrator decisions remain password protected. Normal organizer payment steps and private voter or candidate imports, assignments, and removals use the authenticated session without another password prompt. Later scheduled transitions are performed by the system. Re-running `php artisan db:seed` refreshes the relative dates and resets the reserved demo personas and scenarios, so manual changes to those fixtures are not permanent.

Candidate payment status and application cancellation still require the external Payment API. Local payment rows alone deliberately do not enable those payment-dependent actions.

## Complete election lifecycle

1. The organizer creates a `DRAFT` and sets the nomination start/end and voting start/end dates.
2. The organizer continues to `PENDING_PAYMENT`, completes the hosting payment, and submits the election for administrator review.
3. An administrator verifies the payment and approves the election. Approval moves it to `SCHEDULED`; it does not open nominations early.
4. When the nomination start time arrives, the organizer enters their current password once and selects **Start nominations**. The state becomes `NOMINATION_OPEN`.
5. At the nomination end time, the scheduler automatically changes the state to `NOMINATION_CLOSED`.
6. At the voting start time, the scheduler automatically changes the state to `VOTING_OPEN` when at least one candidate is approved and no candidate decision is pending. If the ballot is incomplete, the scheduler keeps retrying while the organizer resolves the candidate decisions.
7. At the voting end time, the scheduler automatically changes the state to `CLOSED` and results become available.

`composer dev` starts the scheduler alongside the web server, queue, logs, and Vite. In production, configure the standard Laravel scheduler cron to run every minute:

```cron
* * * * * cd /path/to/votetrust-voting-system && php artisan schedule:run >> /dev/null 2>&1
```

The underlying command can also be run directly with `php artisan elections:advance-schedule`.

## Main pages

- `/` — integrated public landing page
- `/dashboard` — member dashboard
- `/elections` — organiser election workspace
- `/candidate/applications` — candidate application status and management
- `/candidate/elections/{election}/applications/create` — election-scoped candidate application form
- `/voting` — secure voter portal
- `/admin` — administrator dashboard
- `/api/v1/...` — module REST providers

## Public-election schedule expiry

An unpublished public election becomes terminally `EXPIRED` when its nomination period ends while it is still a draft, awaiting hosting payment, awaiting administrator approval, or approved but not started by the organizer. VoteTrust checks this before opening or charging checkout, before calling the Payment API, and before an administrator verifies or approves payment. An expired election cannot be edited or resumed; the organiser must create a new election with a future schedule. Private elections are not subject to this public schedule-expiry rule.

## Verification

```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd run build
```

All three commands should complete successfully. Test and route counts change as the project evolves, so rely on the command output rather than a fixed count in this document. With Laragon running, also confirm that `http://votetrust.test/` returns the application home page.
