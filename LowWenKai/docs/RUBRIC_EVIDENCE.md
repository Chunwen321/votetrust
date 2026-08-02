# Rubric Evidence Map

## PHP Web Application - 20%

- Organizer workflow: `app/Controllers/ElectionController.php` and `app/Views/elections`.
- Independent admin workflow: `app/Controllers/AdminElectionController.php` and `app/Views/admin/elections.php`.
- MVC separation: `routes/`, `app/Controllers`, `app/Models`, `app/Services`, and `app/Views`.
- ORM: `app/ORM/Model.php` and `app/ORM/Query.php`, with relationships in `User`, `Organizer`, and `Election`.
- Shared schema compliance: only the submitted `user`, `organizer`, `election`, `payment`, and other team tables; no `lwk_*` tables.
- Robustness: validation, centralized exceptions, PDO transactions, optimistic locking, security headers, and actor-attributed audit history.

## State Design Pattern - 25%

- Diagram: `docs/STATE_PATTERN.puml` and `.png`.
- State interface and Context: `ElectionState.php` and `ElectionLifecycle.php`.
- Concrete States: `DraftState`, `PendingPaymentState`, `PendingAdminApprovalState`, `NominationOpenState`, `VotingOpenState`, `ClosedState`, `CancelledState`, and `RejectedState`.
- Factory: `ElectionStateFactory.php` reconstructs behaviour from the persisted shared `election.status`.
- Real integration: payment confirmation stops at the admin gate; only an independent admin can approve; Candidate/Voting APIs ask the same State objects for availability.
- Tests: complete lifecycle, state skipping, payment, approval, separation of duties, and optimistic locking in `tests/run.php`.

## Secure Coding Practices - 20%

- Practice 1: current-password reauthentication in `app/Security/ReauthenticationGuard.php`.
- Practice 2: server-side workflow authorization and separation of duties in the concrete States and `ElectionService`.
- Payment response integrity: `app/Services/Payment/HttpPaymentGateway.php`.
- Mandatory baselines: CSRF, prepared queries, validation, output encoding, secure session settings, and API envelope validation.
- Safe before/after evidence: `tests/security_demo.php` and `docs/SECURITY_BEFORE_AFTER.md`; the insecure fixture is CLI-only.

## Web Services - 20%

- Exposes election availability: `GET /api/v1/elections/{id}/availability`.
- Consumes teammate payment verification through `HttpPaymentGateway`.
- Exposes admin review integration: `POST /api/v1/elections/{id}/approval` with an API key and admin-role check.
- IFA: `docs/WEB_SERVICE_IFA.md`, including request ID, timestamp, status, authentication, fields, and error conditions.
- HTTP evidence: `tests/http_e2e.ps1` verifies organizer login, payment gate, blocked self-approval, admin web approval, and admin API approval.

## Personal Skills and Report - 15%

- Dependency-free Active Record ORM, shared-schema adaptation, optimistic locking, State pattern, REST IFA validation, CSP/security headers, and automated regression tests show independent application of researched techniques.
- The report contains diagrams, terminal evidence, code excerpts, APA references, security comparison, and a reflection on the shared-team integration constraint.
