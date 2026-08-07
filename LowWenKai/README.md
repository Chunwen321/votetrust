# VoteTrust Election Management Module

Owner: **Low Wen Kai**  
Assignment: BMIT3173 Integrative Programming  
System: VoteTrust Online Election Management System  
SDG: **SDG 16 - Peace, Justice and Strong Institutions**

This is the MVC Election Management Module. An organizer creates an election, verifies the hosting payment, and waits for a separate system administrator to approve it. Candidate registration and voting are handled by the teammates' modules through this module's API.

## Open these folders during the presentation

| Tutor asks for | Open | What it demonstrates |
|---|---|---|
| MVC controller | `app/Controllers` | Receives browser requests and calls the service layer |
| MVC model | `app/Models` | ORM entities mapped only to existing shared database tables |
| MVC view | `app/Views` | PHP pages shown to organizers and administrators |
| MVC routing | `routes` | Maps URLs to controller actions |
| ORM | `app/ORM` | Active Record model, queries, prepared statements, and optimistic locking |
| State design pattern | `app/States` | State interface, factory, lifecycle context, and concrete election states |
| Secure coding | `app/Security` | Reauthentication, API validation, and security audit logging |
| Before/after security test | `tests/security_demo.php` | Safe terminal comparison of insecure and secured workflows |
| Web service integration | `app/Controllers/Api` and `app/Services/Payment` | APIs provided by this module and Payment Module API consumption |

For the State pattern, start with these files in order:

1. `ElectionState.php` — common State interface.
2. `ElectionStateFactory.php` — creates the correct object for the current election status.
3. `ElectionLifecycle.php` — context that delegates actions to the current State.
4. `DraftState.php`, `PendingPaymentState.php`, and `PendingAdminApprovalState.php` — concrete examples of different rules for each state.

## Folder structure

```text
LowWenKai/
|-- app/                 Application source code
|   |-- Controllers/     MVC controllers and API controllers
|   |-- Models/          Database-table ORM models
|   |-- ORM/             Reusable ORM infrastructure
|   |-- Security/        Selected secure coding practices
|   |-- Services/        Election workflow and Payment API integration
|   |-- States/          State design pattern implementation
|   |-- ValueObjects/    Embedded election data; not database tables
|   `-- Views/            MVC user interface
|-- bin/                 One-time application setup command
|-- database/            Shared-table schema additions
|-- payment_stub/        Temporary Payment Module API simulator
|-- public/              Web entry point and CSS
|-- report/              Assignment report; does not run in the application
|-- routes/              Web and API route definitions
|-- storage/             Runtime security logs and PHP sessions
`-- tests/               Automated and tutor demonstration tests
```

The report is the only non-code submission artifact intentionally retained in this folder.

## Database mapping

The module preserves the team's eight submitted tables:

```text
candidacy, candidate, election, organizer, payment, user, vote, voter
```

No `lwk_*` tables are required. The existing `election` table stores payment verification, admin approval, version, and transition-history data in added columns. The existing `user` table gains only `is_admin`.

- `app/Models/User.php` maps to `user`.
- `app/Models/Organizer.php` maps to `organizer`.
- `app/Models/Election.php` maps to `election`.
- `app/Models/Candidate.php` and `Candidacy.php` map to the existing teammate tables so the organizer can review the ballot without creating another table.
- `app/ValueObjects/PaymentVerification.php` and `ElectionTransitionRecord.php` are not tables; they represent data stored inside the election row.

## Election lifecycle

```text
DRAFT -> PENDING_PAYMENT -> PENDING_ADMIN_APPROVAL
      -> NOMINATION_OPEN -> VOTING_OPEN -> CLOSED
```

`CANCELLED` and `REJECTED` are terminal states. An organizer creates and pays for an election. A user with `user.is_admin = 1` independently approves it. An administrator cannot approve their own election. An account named `OrganizerAdmin` is still only an organizer unless its `user.is_admin` column is set to `1`.

## Run with XAMPP

```powershell
cd C:\Users\lowwe\source\repos\votetrust-voting-system\LowWenKai
Copy-Item .env.example .env
C:\xampp\php\php.exe bin\setup.php

# Terminal 1: temporary Payment Module API
C:\xampp\php\php.exe -S 127.0.0.1:8081 -t payment_stub\public payment_stub\public\router.php

# Terminal 2: Election Module
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public\router.php
```

Open `http://127.0.0.1:8080`.

- Organizer: `LowWenKaiOrganizer` / `VoteTrust!2026`
- Administrator: `SystemAdmin` / `VoteTrustAdmin!2026`
- Confirmed payment: `VT-HOST-PAID-001`
- Pending payment: `VT-HOST-PENDING-001`

## Test in sequence

1. Log in as the organizer and create an election.
2. Submit it for payment and confirm it becomes `PENDING_PAYMENT`.
3. Try the pending payment reference; the state must not change.
4. Use the confirmed reference; the state becomes `PENDING_ADMIN_APPROVAL`.
5. Verify the organizer cannot approve the election.
6. Log in as the administrator and approve it from **Approval queue**.
7. Verify it becomes `NOMINATION_OPEN` and the audit trail names the administrator.
8. Log back in as the organizer, review every candidate application, and approve at least one candidate.
9. After the configured voting start time, open voting and verify only approved candidates are ballot-eligible.

Run the automated checks:

```powershell
C:\xampp\php\php.exe tests\run.php
C:\xampp\php\php.exe tests\security_demo.php
powershell -ExecutionPolicy Bypass -File tests\http_e2e.ps1
```

The security demonstration keeps the deliberately insecure example in a command-line-only test fixture. It is never reachable from the website.

## Teammate module integration

Candidate and Voting modules can check whether an election accepts their action through:

```text
GET /api/v1/elections/{id}/availability?requestID=...&timestamp=...
```

A separate Admin Module can call:

```text
POST /api/v1/elections/{id}/approval
X-Admin-API-Key: value from ADMIN_API_KEY
Content-Type: application/json
```

When the teammate Payment Module is available, change only `PAYMENT_API_URL` and `PAYMENT_API_KEY` in `.env`; the controller, State classes, views, and ORM do not need redesigning.
