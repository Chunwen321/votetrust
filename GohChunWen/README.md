# VoteTrust Election and Voting Modules

VoteTrust is a modular online election system supporting controlled election lifecycles and private, one-person-one-vote ballot submission.

This guide covers the two integrated modules currently present in the repository:

- **Election Management Module — Low Wen Kai:** creates elections, verifies hosting payment, requires independent administrator approval, reviews candidates, and controls when voting opens or closes.
- **Voting Process Module — Goh Chun Wen:** lists authorised open elections, displays approved candidates, records private votes, prevents duplicate submissions, and provides voting-status and final-tally APIs.

The integration changes are contained in `GohChunWen/`. Low Wen Kai's source remains unchanged.

## Architecture

```text
Browser
  |-- Election Management UI :8080
  |      |-- shared authenticated PHP session
  |      `-- Payment verification API :8081
  |
  `-- Voting Process UI :8082
         |-- Election availability API :8080
         |-- optional future User eligibility API
         `-- shared election_db
```

The applications remain separate services. Integration does not mean one module starts another; both applications run and communicate through the Election API and the shared development database.

### Integration boundaries

The Voting module consumes Low Wen Kai's existing endpoint:

```text
GET /api/v1/elections/{id}/availability
```

That response is authoritative for `lifecycleState`, `votingAllowed`, and the voting period. A vote is accepted only when the Election module returns `VOTING_OPEN` and `votingAllowed: true`.

Low's existing API does not expose an election list or approved candidate names. Until those endpoints exist, `Service/ExternalApi.php` reads election identity and approved ballot candidates from the shared `election`, `candidacy`, `candidate`, and `user` tables. Lifecycle authorisation still comes from Low's API.

The future User Module can own eligibility through `USER_API_URL`. While that URL is empty, the Voting module requires the authenticated account to exist in the shared `voter` role table.

## Design patterns and security

### Election module

- MVC controllers, models, views, and routes.
- Active Record ORM with prepared statements and optimistic locking.
- State pattern for `DRAFT`, payment, administrator approval, nominations, voting, closure, rejection, and cancellation.
- Password reauthentication for sensitive lifecycle actions.
- CSRF protection, security headers, audit logging, and separation of organiser/administrator duties.

### Voting module

- MVC-style controller, entity, ORM, services, and views.
- Decorator pattern:
  - `AuthorizationDecorator` checks voter eligibility, Election state, and candidate membership.
  - `DuplicateVoteDecorator` serialises concurrent attempts and enforces one vote per election.
- The database unique key `(voter_id, election_id)` is the final duplicate-vote safeguard.
- The voter ID comes from the authenticated server session, never from a browser form.
- CSRF, fresh timestamps, traceable request IDs, prepared statements, API keys, and output escaping are enforced.
- Status responses never expose the selected candidate.
- Tally responses are blocked until the Election module reports `CLOSED`.

## Repository structure

```text
LowWenKai/
|-- app/                 Election MVC, ORM, services, states, and security
|-- database/schema.sql  Election extensions to the shared schema
|-- payment_stub/        Local Payment API simulator
|-- public/              Election web entry point and assets
|-- routes/              Election web/API routes
`-- tests/               Election workflow and security tests

GohChunWen/
|-- Controller/          Browser and API request handling
|-- Model/               Vote entity and shared-table Vote ORM
|-- Service/             Decorators and teammate API adapter
|-- View/                Voting portal, ballot, success, and error views
|-- public/              Voting web entry point, router, and shared-style CSS
|-- tests/               Voting service and database-contract tests
|-- database.sql         Voting-only `vote` table migration
|-- election_db.sql      Complete integrated schema and demo data
|-- .env.example         Local configuration template
`-- index.php            Voting composition root and routes
```

## Database

`GohChunWen/election_db.sql` is the canonical integrated development SQL. It includes all eight shared tables:

```text
user, organizer, candidate, voter, payment, election, candidacy, vote
```

Role tables reuse `user.id`:

- `organizer.id -> user.id`
- `candidate.id -> user.id`
- `voter.id -> user.id`

The voting table follows the supplied shared schema:

```text
vote(id, election_id, voter_id, candidate_id, cast_at)
```

It contains foreign keys to `election`, `voter`, and `candidate`, plus a unique constraint on `(voter_id, election_id)`.

The SQL is non-destructive: it does not drop tables. It creates missing tables, inserts missing demo data, and normalises the named seed accounts and closed seed election. Importing it into an existing development database will reset the documented seed-account passwords and the seed data for election `#1`; it does not remove unrelated rows.

### Demo accounts

| Role | Username | Password |
|---|---|---|
| Organiser | `LowWenKaiOrganizer` | `VoteTrust!2026` |
| Administrator | `SystemAdmin` | `VoteTrustAdmin!2026` |
| Candidate | `CandidateAisha` | `Candidate!2026` |
| Candidate | `CandidateDaniel` | `Candidate!2026` |
| Voter | `VoterJohn` | `VoteTrustVoter!2026` |
| Voter | `VoterAmina` | `VoteTrustVoter!2026` |
| Voter | `VoterBenjamin` | `VoteTrustVoter!2026` |
| Voter | `VoterChen` | `VoteTrustVoter!2026` |
| Voter | `VoterDevi` | `VoteTrustVoter!2026` |
| Voter | `VoterFarah` | `VoteTrustVoter!2026` |

The closed seed election contains six sample votes, producing a stable `4-2` tally. No votes are inserted into the date-relative election created by Low's setup command, so every demo voter can test that election normally.

## First-time setup with XAMPP

### 1. Start MySQL

Open XAMPP Control Panel and start **MySQL**. Apache is not required when using PHP's built-in development servers.

### 2. Import the integrated database

From the repository root:

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:/Users/gohch/source/repos/votetrust-voting-system/GohChunWen/election_db.sql;"
```

Alternatively, import `GohChunWen/election_db.sql` through phpMyAdmin.

### 3. Create local environment files

```powershell
cd C:\Users\gohch\source\repos\votetrust-voting-system

Copy-Item LowWenKai\.env.example LowWenKai\.env
Copy-Item GohChunWen\.env.example GohChunWen\.env
```

Set a local provider key in `GohChunWen/.env`:

```env
VOTING_API_KEY=local-voting-provider-key
```

`.env` is ignored by Git and must never be committed.

### 4. Run Low Wen Kai's setup

```powershell
C:\xampp\php\php.exe LowWenKai\bin\setup.php
```

This applies Election schema extensions, prepares Payment stub data, and creates a date-relative election with candidate applications when no active election exists.

## Start the integrated system

Use the same hostname for both web applications. Use `127.0.0.1` everywhere; do not mix it with `localhost`, because the local authenticated session cookie is hostname-scoped.

### Terminal 1 — Payment stub

Required only while testing election payment verification:

```powershell
cd C:\Users\gohch\source\repos\votetrust-voting-system\LowWenKai
C:\xampp\php\php.exe -S 127.0.0.1:8081 -t payment_stub\public payment_stub\public\router.php
```

### Terminal 2 — Election module

```powershell
cd C:\Users\gohch\source\repos\votetrust-voting-system\LowWenKai
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public\router.php
```

Election URL: `http://127.0.0.1:8080`

### Terminal 3 — Voting module

```powershell
cd C:\Users\gohch\source\repos\votetrust-voting-system\GohChunWen
C:\xampp\php\php.exe -S 127.0.0.1:8082 -t public public\router.php
```

Voting URL: `http://127.0.0.1:8082/voting`

Once payment has already been verified, only MySQL plus ports `8080` and `8082` are required.

## Complete demonstration workflow

1. Sign in to port `8080` as `LowWenKaiOrganizer`.
2. Open the active demo election created by `bin/setup.php`.
3. Submit it for payment.
4. Verify payment using `VT-HOST-PAID-001` while the Payment stub runs.
5. Sign out and sign in as `SystemAdmin`.
6. Approve the election independently.
7. Sign out and return as `LowWenKaiOrganizer`.
8. Approve or reject every candidate application; at least one must be approved.
9. After the configured voting start, transition the election to `VOTING_OPEN`.
10. Sign out and sign in through port `8080` using any demo voter account.
11. Low's organiser-focused UI may redirect a voter to a restricted page; the authenticated session is still valid.
12. Open `http://127.0.0.1:8082/voting`, select the election, and cast one vote.
13. Refresh the portal and confirm the election now shows `Vote recorded` for that voter.
14. Repeat with other voter accounts if desired.
15. After the scheduled voting end, close the election through Low's lifecycle action.
16. Consume the Voting tally API to retrieve final totals.

Never update `election.status` manually in MySQL. Use Low's lifecycle UI so payment, approval, scheduling, reauthentication, and audit rules remain enforced.

## Web routes

### Election module (`:8080`)

```text
GET  /login
POST /login
POST /logout
GET  /elections
GET  /elections/create
GET  /elections/{id}
GET  /admin/elections
GET  /admin/elections/{id}
```

### Voting module (`:8082`)

```text
GET  /voting
GET  /voting/ballot?electionId={id}
POST /voting/cast
```

## API consumed by Voting

### Election availability

```http
GET /api/v1/elections/2/availability?requestID=VOTE-REQ-0001&timestamp=2026-08-07T09:30:00Z
Accept: application/json
```

Relevant success response:

```json
{
  "status": "S",
  "requestID": "VOTE-REQ-0001",
  "timestamp": "2026-08-07T17:30:00+08:00",
  "data": {
    "electionId": 2,
    "lifecycleState": "VOTING_OPEN",
    "votingAllowed": true,
    "votingPeriod": {
      "start": "2026-08-07T17:00:00+08:00",
      "end": "2026-08-09T18:00:00+08:00"
    }
  }
}
```

The Voting module validates the correlated `requestID`, response shape, election ID, lifecycle state, and boolean permission.

### Future User eligibility

When `USER_API_URL` is configured, Voting calls:

```http
GET {USER_API_URL}/api/v1/voters/eligibility
```

Parameters are `voterId`, `electionId`, `requestID`, and `timestamp`. A successful response must contain `data.eligible: true`. Until then, the local `voter` role table is used.

## APIs provided by Voting

Both endpoints require:

```http
X-Internal-Api-Key: <VOTING_API_KEY>
```

`requestID` must contain 8-64 letters, digits, underscores, or hyphens. `timestamp` must be ISO 8601 and within five minutes of server time.

### Voting status

```http
GET /api/v1/votes/status?voterId=2&electionId=2&requestID=STATUS-REQ-0001&timestamp=2026-08-07T09:30:00Z
```

```json
{
  "status": "S",
  "message": "Voting status retrieved.",
  "requestID": "STATUS-REQ-0001",
  "timestamp": "2026-08-07T17:30:00+08:00",
  "data": {
    "voterId": 2,
    "electionId": 2,
    "hasVoted": true
  }
}
```

Candidate choice is intentionally omitted.

### Final tally

```http
GET /api/v1/votes/tally?electionId=1&requestID=TALLY-REQ-0001&timestamp=2026-08-07T09:30:00Z
```

The Election API must report `CLOSED` before this endpoint succeeds.

```json
{
  "status": "S",
  "message": "Vote tally retrieved.",
  "requestID": "TALLY-REQ-0001",
  "timestamp": "2026-08-07T17:30:00+08:00",
  "data": {
    "electionId": 1,
    "tally": [
      {"candidateId": 5, "candidateName": "CandidateAisha", "votes": 4},
      {"candidateId": 6, "candidateName": "CandidateDaniel", "votes": 2}
    ]
  }
}
```

## Automated verification

Voting tests:

```powershell
C:\xampp\php\php.exe GohChunWen\tests\run.php
```

Election regression tests:

```powershell
cd LowWenKai
C:\xampp\php\php.exe tests\run.php
```

The Voting suite checks the shared database contract, vote receipts, duplicate prevention, eligibility, approved-candidate authorisation, lifecycle enforcement, all eight SQL tables, demo voters, and database constraints.

## Troubleshooting

### `Table election_db.candidate doesn't exist`

Import `GohChunWen/election_db.sql`. `candidate` and `candidacy` are different tables: `candidate` is a user role, while `candidacy` is that candidate's application to one election.

### Voter sees no open election

Confirm all of the following:

- Election status is `VOTING_OPEN`, not `NOMINATION_OPEN`.
- Every candidacy is approved or rejected; none remain pending.
- At least one candidacy is approved.
- Current time falls between `voting_start` and `voting_end`.
- Election API on port `8080` returns `votingAllowed: true`.
- Both sites use the same hostname.

### Voting says the user must sign in

Sign in through port `8080` first, then open port `8082`. Both modules must use session name `VOTETRUST_ELECTION` and the configured shared session directory.

### API access denied

Set `VOTING_API_KEY` in `GohChunWen/.env` and send the same value in `X-Internal-Api-Key`.

### Tally returns a validation error

The election must be closed through Low's lifecycle action before Voting releases a tally.

## Git safety

Before committing:

```powershell
git add GohChunWen
git diff --cached --name-only
git diff --cached --check
```

Confirm `GohChunWen/.env` is not listed. Commit `.env.example`, `.gitignore`, both SQL files, source, views, public assets, tests, and this README.
