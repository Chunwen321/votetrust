# VoteTrust Election Management Module

Owner: **Low Wen Kai**  
Assignment: BMIT3173 Integrative Programming  
System: VoteTrust Online Election Management System  
SDG: **SDG 16 - Peace, Justice and Strong Institutions**

This folder contains the MVC Election Management Module. An organiser creates an election and verifies its hosting payment. A separate system administrator must then approve it before candidate nomination is enabled. Candidate registration and voting remain owned by the other team modules; they can call this module's availability API.

## Roles and lifecycle

- **Organizer** means a user whose ID also exists in the shared `organizer` table. The organizer creates, pays for, and manages their own election.
- **System administrator** means a user with `user.is_admin = 1`. The administrator reviews paid elections and may approve or reject them. An administrator cannot approve their own election.
- `OrganizerAdmin` is only an existing username in the submitted database. It is not automatically a system administrator; the account has `is_admin = 0` unless the database explicitly grants that flag.

The State-pattern lifecycle is:

```text
DRAFT -> PENDING_PAYMENT -> PENDING_ADMIN_APPROVAL
      -> NOMINATION_OPEN -> VOTING_OPEN -> CLOSED
```

`CANCELLED` and `REJECTED` are terminal states.

## Database constraint

The implementation preserves the team's eight submitted tables:

```text
candidacy, candidate, election, organizer, payment, user, vote, voter
```

It does not require `lwk_users`, `lwk_elections`, `lwk_payment_verifications`, or `lwk_election_transition_logs`. Election configuration, payment verification, approval, optimistic-lock version, and JSON audit history are stored in added columns of the existing shared `election` table. The existing `user` table gains only `is_admin`.

## Rubric technologies

- PHP 8.2 and MariaDB/MySQL from XAMPP.
- MVC: routes delegate to controllers, services/ORM entities, and PHP views.
- ORM: the dependency-free Active Record mapper in `app/ORM` maps the shared tables to entity objects, uses prepared statements, protects fillable attributes, resolves relationships, and performs optimistic locking.
- State pattern: eight concrete State classes own lifecycle-specific actions and rules.
- Secure practice 1: current-password reauthentication for critical browser transitions.
- Secure practice 2: server-side workflow authorization, including payment verification, an independent admin approval gate, separation of duties, date validation, transactions, and an actor-attributed audit history.
- Web services: the module exposes election availability and admin approval APIs and consumes the Payment Module IFA through cURL.

## Run locally with XAMPP

Start MySQL in XAMPP, then run:

```powershell
cd C:\Users\lowwe\source\repos\votetrust-voting-system\LowWenKai
Copy-Item .env.example .env
C:\xampp\php\php.exe bin\setup.php

# Terminal 1 - local stand-in for the teammate Payment Module
C:\xampp\php\php.exe -S 127.0.0.1:8081 -t payment_stub\public payment_stub\public\router.php

# Terminal 2 - Election Module
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public\router.php
```

Open `http://127.0.0.1:8080`.

Local assessment accounts:

- Organizer: `LowWenKaiOrganizer` / `VoteTrust!2026`
- Administrator: `SystemAdmin` / `VoteTrustAdmin!2026`
- Confirmed sandbox payment: `VT-HOST-PAID-001`
- Pending sandbox payment: `VT-HOST-PENDING-001`

These values are local assessment fixtures only. Production credentials and API keys must come from the environment.

## Test in sequence

1. Log in as `LowWenKaiOrganizer`.
2. Create an election with valid nomination and voting dates.
3. Submit it for payment; it becomes `PENDING_PAYMENT`.
4. Try `VT-HOST-PENDING-001`; verify the status does not change.
5. Use `VT-HOST-PAID-001`; verify it becomes `PENDING_ADMIN_APPROVAL`, not `NOMINATION_OPEN`.
6. Confirm the organizer cannot approve the election.
7. Log out, then log in as `SystemAdmin`.
8. Open **Approval queue**, review the paid election, and approve it.
9. Verify the state becomes `NOMINATION_OPEN` and the audit trail identifies the admin.
10. Continue the date-dependent nomination, voting, and closure actions as the organizer.

Run automated verification:

```powershell
C:\xampp\php\php.exe tests\run.php
C:\xampp\php\php.exe tests\security_demo.php
powershell -ExecutionPolicy Bypass -File tests\http_e2e.ps1
```

The HTTP script removes only the two `E2E Administrator ...` elections it created. Add `-KeepTestData` if you deliberately want to inspect those rows afterward.

The terminal before/after demonstration is intentionally safer than commenting insecure production code in and out: the vulnerable examples exist only in a CLI fixture and have no web route.

## APIs

Availability for Candidate/Voting modules:

```text
GET /api/v1/elections/{id}/availability?requestID=...&timestamp=...
```

Admin Module approval integration:

```text
POST /api/v1/elections/{id}/approval
X-Admin-API-Key: value from ADMIN_API_KEY
Content-Type: application/json
```

The full request and response fields are in `docs/WEB_SERVICE_IFA.md`.

## Replacing teammate sandboxes

When the Payment Module is ready, update `PAYMENT_API_URL` and `PAYMENT_API_KEY` in `.env`. When a separate Admin Module calls the approval endpoint, share `ADMIN_API_KEY` securely and send a real system-admin user ID. No controller, State, view, or ORM redesign is required.

## Documentation map

- `docs/STATE_PATTERN.puml` - eight-state pattern class diagram.
- `docs/ENTITY_CLASS_DIAGRAM.puml` - shared-table ORM and embedded value objects.
- `docs/WEB_SERVICE_IFA.md` - availability, payment, and admin approval contracts.
- `docs/SECURITY_BEFORE_AFTER.md` - tutor-facing comparison procedure.
- `docs/SHARED_SCHEMA_MIGRATION.md` - schema decision and recovery record.
- `docs/RUBRIC_EVIDENCE.md` - code and test evidence by criterion.
