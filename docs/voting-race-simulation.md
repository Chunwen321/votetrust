# Local voting race simulation

PIC: Goh Chun Wen

This guide checks the most important double-voting invariant through the real VoteTrust web application:

> Many identical vote requests may arrive together, but at most one vote may be recorded for one voter in one election.

The harness signs in through the real login form, loads the real ballot and its CSRF/security fields, then starts independent PowerShell background processes. Every process waits on the same named start event before sending an identical ballot POST. Redirect following is disabled so the result can distinguish a receipt redirect from a redirect back to the ballot.

This is a university-project verification tool, not a penetration-testing or production-load-testing tool. Run it only against an expendable local Laragon environment that you own.

## Safety boundaries

- The script accepts only `localhost`, a loopback IP, or a `.test` hostname that resolves only to loopback.
- It refuses production-like hostnames and embedded URL credentials.
- It never queries, writes, truncates, migrates, seeds, or resets the database.
- It prompts for the password as a secure string. The plaintext is held only briefly for the login POST, then its unmanaged buffer is zeroed. The password, cookies, CSRF token, and request ID are never printed.
- The hostname check is an extra guard, not permission to test somebody else's system. Check the Laragon hosts entry before running it.
- Each run deliberately records a real vote in the selected local database when successful.

## Prerequisites

1. Start Laragon's **Apache** and **MySQL** services.
2. Use Apache or another multi-worker local server. Do not use a single `php artisan serve` worker for this test; module-to-module HTTP calls can block it and one worker cannot create a meaningful race.
3. Use a disposable local database with migrations and demo data already loaded.
4. Use production-like shared coordination settings for the test:

   ```dotenv
   DB_CONNECTION=mysql
   CACHE_STORE=database
   SESSION_DRIVER=database
   ```

   Redis is also suitable for cache locks. Do not use the `array` cache: each process gets a separate in-memory store, so it cannot represent a distributed lock. After changing `.env`, run `php artisan config:clear`.
5. Confirm the selected election is `VOTING_OPEN`, has approved candidates, and the demo account is active, eKYC-verified, and eligible.
6. Confirm the voter has not already voted in that election. The ballot form intentionally disappears after a successful vote.
7. Confirm the User and Election module URLs/keys work. A normal ballot should load in the browser before the simulation.

The default email is `jason.lim@votetrust.test`. The script makes one login attempt, so enter the correct password. Repeated bad manual logins can disable the demo account under the current failed-login strategy.

## Run the simulation

From the repository root, open PowerShell and run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\voting-race-simulation.ps1 `
  -BaseUrl http://votetrust.test `
  -ElectionId 7
```

Replace `7` with the election number shown in the ballot URL; demo database IDs are not guaranteed to stay the same. The default is five concurrent requests. This is enough to create a real race while leaving capacity for VoteTrust's nested User and Election module calls.

To choose the load and candidate explicitly:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\voting-race-simulation.ps1 `
  -BaseUrl http://votetrust.test `
  -ElectionId 7 `
  -Requests 5 `
  -Email jason.lim@votetrust.test `
  -CandidateId 12
```

`Requests` is limited to 2 to 50 to keep this a focused race check rather than a load test. If `CandidateId` is omitted, the script chooses the first candidate on the current ballot. A supplied candidate must actually appear on that ballot.

## Understand the result

The expected result is:

- exactly one `Receipt` redirect, normally HTTP 302 to `/voting/receipts/{id}`;
- every other request classified `ReturnedToBallot`, normally HTTP 302 back to the ballot;
- exactly one matching vote row after a manual read-only check.

The script calls the back-to-ballot category `ReturnedToBallot` because a redirect response contains only its destination; Laravel keeps the precise validation, duplicate, or integration message in the session. For identical valid requests in a healthy controlled run, those redirects should be duplicate/lock rejections.

Interpret other categories as follows:

| Category | Meaning |
|---|---|
| `Receipt` | The request was accepted and redirected to a receipt. More than one is an invariant failure. |
| `ReturnedToBallot` | The request returned to the same ballot. In a healthy identical-request run, this normally means another request won. |
| `RateLimited` | HTTP 429; a route, proxy, or WAF limiter intervened. The race result is inconclusive. |
| `CsrfRejected` | HTTP 419; the shared authenticated session or CSRF token was not accepted. |
| `OtherRedirect` | Authentication/profile middleware or another redirect handled the request. |
| `ServerError` | The application returned HTTP 5xx. Check Laravel and Apache logs. |
| `TransportError` / `WorkerError` | A request process could not reach or complete against the application. |

One receipt is useful HTTP evidence, but the final invariant is the database state. In phpMyAdmin, perform a **read-only filter** on the local `vote` table for the exact voter and election and confirm the count is one. Do not include `candidate_id` or other ballot-choice data in screenshots, logs, or a shared report.

## Verified local result

On 21 August 2026, the harness was run against this repository through Laragon Apache and MySQL with five workers released at the same millisecond. The observed outcome was one receipt redirect and four returns to the ballot. A separate read-only database check found exactly one vote row for that voter/election, with its request ID stored.

This is evidence that the invariant held in that local run; it is not a mathematical proof for every server configuration. Repeat the exercise on the environment used for your demonstration and retain a non-sensitive screenshot of the summary plus the row count.

## Rate-limit interaction

Rate limiting is a separate protection from the unique database constraint. If a voting-route limiter, reverse proxy, or security product returns 429, it may stop requests before they reach the race-sensitive code. That is useful operational protection, but it makes this particular run inconclusive.

For a local test, lower `-Requests` first. If a course requirement asks you to test behind a limiter, record the 429 result separately, then run the race invariant in an isolated local profile whose limit safely permits the chosen request count. Never weaken a shared or production limiter for this simulation.

## Reset manually before another run

The script intentionally does not reset data. Use one of these local-only approaches:

1. Prefer a different eligible demo voter or a fresh disposable voting-open election.
2. Restore a disposable local database snapshot made before the run.
3. If your lecturer requires repeating the exact voter/election, use phpMyAdmin to:
   - open only the local VoteTrust database;
   - identify the exact voter ID and election ID;
   - filter the `vote` table by both IDs;
   - confirm the filter returns exactly one row;
   - delete that one row only.

Never truncate the `vote` table, run `migrate:fresh`, reseed a shared database, or delete a row whose voter/election IDs have not been verified. A reset destroys test evidence, so capture the count and non-sensitive outcome first.

## Troubleshooting

**The script refuses the URL**

Use `http://votetrust.test`, `http://localhost`, or a loopback IP. For a `.test` name, inspect the Windows hosts file and make sure it resolves only to `127.0.0.1` or `::1`.

**The login or ballot form is not found**

Open the same ballot manually. Check the password, account status, eKYC verification, eligibility, election state, approved candidates, and whether the voter already voted. Also confirm `BaseUrl` points to this repository's VoteTrust instance.

**The request timestamp expires**

Workers normally become ready well inside the five-minute window. Slow job startup, a paused debugger, or severe machine load can make the ballot stale. Close other heavy applications and rerun with fewer requests so the script fetches a fresh ballot.

**Requests time out or return server errors**

Make sure Apache has multiple workers and all teammate service URLs resolve. One vote request fans out through the User and Election module APIs, so too many outer requests can consume the local worker pool before those internal calls finish. Reduce `-Requests` to `5` or lower, then check `storage/logs/laravel.log`, Apache's error log, MySQL availability, and the database-backed cache lock table. Do not switch to the array cache to hide a lock problem.

**HTTPS fails locally**

Trust the local development certificate correctly. The harness does not disable TLS certificate validation.

**More than one receipt appears**

Treat this as a failed invariant. Preserve the local database and logs, record the timestamps/statuses, and inspect the `(voter_id, election_id)` unique constraint and migration state. Do not reset until the evidence has been reviewed.
