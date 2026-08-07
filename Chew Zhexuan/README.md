# VoteTrust User and Administration Module

Owner: **Chew Zhexuan**  
System: **VoteTrust Online Voting System**

This module manages user accounts and provides the system-administrator console.

## Modules

| Module | Responsibilities | Access |
|---|---|---|
| User | Register, sign in/out, update profile, change password, account status | Registered user |
| Voter | View election enrolments | Enrolled user |
| Admin | Full election, voter, and candidate management | `user.is_admin = 1` |

## Administrator control centre

Administrators are management-only and cannot vote or become candidates. The admin workspace provides:

- Election creation, editing, scheduling, approval, opening, closing, cancellation, and return-to-draft.
- A lifecycle overview and management dashboard for every election.
- Per-election voter access control: add an active registered user and set `APPROVED`, `PENDING`, `REJECTED`, or `SUSPENDED`.
- Per-election candidate control: add an active registered user and set `Approved`, `Pending`, or `Rejected`.
- Election lifecycle actions recorded in `election.transition_history_json`.

## Voter table

`voter` is an election-specific enrolment table, keyed by `user_id` and `election_id`.

| Field | Purpose |
|---|---|
| `user_id` | Registered user enrolled to vote. |
| `election_id` | Election for this enrolment. |
| `status` | `PENDING`, `APPROVED`, `REJECTED`, or `SUSPENDED`. |
| `added_by` | Administrator who created or last updated the enrolment. |
| `created_at`, `updated_at` | Enrolment timestamps. |

## Security and design decisions

- MVC structure: controllers handle requests, services enforce rules, repositories run prepared queries, and views escape output.
- Passwords use `password_hash()` / `password_verify()`. IC numbers are stored in normalised plain text for user identity matching.
- Login input is validated, the session ID is regenerated on success, and browser writes require a CSRF token.
- The Strategy pattern selects authentication protection and session termination. Five consecutive failed password checks disable an account; a successful sign-in resets the counter. Logout expires the cookie, clears all session data, and destroys the server-side session.
- Admin accounts cannot access voter pages or pass the eligibility API, even if an incorrect voter row is added manually.

## Database setup

Apply [database/schema.sql](database/schema.sql) for a new database. For the old `voter(id)` role table, use [database/migrate_voter_table.sql](database/migrate_voter_table.sql) once after taking a backup.

## Checks

```powershell
C:\xampp\php\php.exe tests\run.php
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```
