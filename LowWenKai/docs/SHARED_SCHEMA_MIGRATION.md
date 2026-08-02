# Shared Schema Migration Record

## Decision

The team's submitted database already defines eight fixed table names. The Election Module therefore reuses those tables and adds columns only where its lifecycle requires them.

Retained tables:

```text
candidacy
candidate
election
organizer
payment
user
vote
voter
```

Removed module-specific duplicates:

```text
lwk_users
lwk_elections
lwk_payment_verifications
lwk_election_transition_logs
```

The existing `user` table received `is_admin`. The existing `election` table received organizer/configuration, schedule, payment-verification snapshot, admin-decision, transition-history JSON, optimistic-lock version, and timestamp columns. No submitted row in the original shared tables was deleted.

## Role mapping

- An organizer is a shared `user` whose ID exists in `organizer`.
- A system administrator is a shared `user` whose `is_admin` value is `1`.
- The roles are deliberately independent. Approval rejects an administrator who owns the same election.

## Migration safety

Before dropping the four obsolete tables, the migration created:

```text
storage/backups/election_db_before_shared_migration_2026-08-01.sql
```

Verified SHA-256:

```text
6C23BA9EDF3FE4CAEFB622CC9558DE69ACE2E1D33E6D13706937CEE6B2D6ABCD
```

The backup includes password hashes and must remain private. The migration first copied and verified election/payment/audit data in the shared tables, then dropped only the four exact `lwk_*` names. It also corrected a previously paid/open election to `PENDING_ADMIN_APPROVAL` so the newly clarified administrator gate could not be bypassed.

To inspect the live schema without changing it:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -D election_db -e "SHOW TABLES; DESCRIBE user; DESCRIBE election;"
```

The migration utility is `bin/migrate_to_shared_schema.php`. Its destructive phase requires the explicit `--confirm-drop-lwk-tables` flag and should not be rerun merely to test the application.
