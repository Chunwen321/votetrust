<!-- PIC: Chew Zhexuan, Low Wen Kai, Wong Shun Bin, Goh Chun Wen - shared IFA index. -->
# VoteTrust Interface Agreements (IFA)

## Common REST/JSON envelope

Every request carries both:

| Field | Type | Rules |
|---|---|---|
| `requestID` | string | Unique/correlatable, 8–64 allowed characters for VoteTrust providers |
| `timestamp` | ISO-8601 string | Fresh request-generation time |

Every response carries `status` (`S`, `F`, or `E`), `message`, the correlated `requestID`, and a newly generated ISO-8601 `timestamp`. Successful responses place module fields in `data`; failures place safe validation details in `errors`. Consumers reject non-2xx responses, bad JSON, failed status, mismatched IDs, stale timestamps, or malformed data.

## Service exposure

### User eligibility — Chew Zhexuan

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | User module |
| Target | Voting module |
| URL | `/api/v1/voters/eligibility` |
| Function | `VoterEligibilityController::__invoke` |
| Authentication | `X-User-Api-Key` or `X-Internal-Api-Key` |

Request data: `voterId`, `electionId`, `requestID`, `timestamp`. Success data contains `voterId`, `electionId`, boolean `eligible`, the enrolment `reasonCode`, and an internal `identity` object with nullable `dateOfBirth` plus `nationality.countryCode`. Voting uses those verified User-owned facts only when Election publishes a corresponding demographic restriction; missing required facts fail closed.

### Private voter roster — Chew Zhexuan

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`, `POST`, `PATCH`) |
| Source | User/Voter module |
| Target | Election module |
| URLs | `/api/v1/voters/private-enrollments`; `/api/v1/voters/private-directory`; `/api/v1/voters/private-enrollments/selection` |
| Functions | `PrivateVoterEnrollmentController::__invoke`; `PrivateVoterDirectoryController::__invoke`; `PrivateVoterSelectionController::__invoke` |
| Authentication | `X-User-Api-Key` or `X-Internal-Api-Key` |

The Election module parses an organiser CSV, XLSX, or XLS file in memory and sends only `electionId`, `organizerUserId`, and a `voters` array of `{row, fullName, identityNumber}` records. The file header remains `full_name,ic_number`; `ic_number` values may hold either a Malaysian IC or a passport number. The User/Voter provider normalises the identity number, requires an exact normalised full-name match, and writes only its own `voter` records. Imports add new matches without silently removing previous assignments. Source files are never stored. The response returns `requested`, `assigned`, `skipped`, `removed`, `totalAssigned`, `unmatchedCount`, and safe row/reason diagnostics; Election stores only audit-safe counts.

For live roster management, Election queries the paginated directory using `electionId`, `organizerUserId`, and an optional identity-number search, then sends checkbox changes with `electionId`, `organizerUserId`, `userId`, and boolean `assigned`. User identity and enrolment tables remain owned by the User/Voter provider.

### Election availability and ballot — Low Wen Kai

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Election module |
| Targets | User/Voter and Voting modules |
| URLs | `/api/v1/elections/available`; `/api/v1/elections/voter-catalog`; `/api/v1/elections/{election}/availability` |
| Functions | `ElectionAvailabilityController::index/catalog/show` |

All requests require `requestID` and `timestamp`. The available list returns currently votable summaries. The voter catalog lets the User/Voter module discover public voting and closed elections while retaining enrollment-only access for private elections. Detail returns lifecycle state, eligibility rules, permission booleans, periods, and an approved-only `ballot` containing `id`, `candidateId`, `name`, `position`, and `manifesto`.

The detail response keeps both `eligibilityRules` and `eligibility` keys for consumer compatibility. Both now contain the same structure:

```json
{
  "type": "PUBLIC",
  "minimumAge": 18,
  "nationality": {
    "restricted": true,
    "countryCode": "MY",
    "countryName": "Malaysia"
  }
}
```

`PUBLIC` elections can publish age and/or nationality requirements. `PRIVATE` elections return `minimumAge: null` and an unrestricted nationality object, then rely on the User/Voter provider's approved private-roster check. Country restrictions use ISO 3166-1 alpha-2 codes. Occupation is not an eligibility condition and is not published.

### Candidate and organisation information — Wong Shun Bin

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Candidate module |
| Target | Payment module (Puah Kaho Risky) |
| URL | `/api/v1/candidates/{candidate}/payment-information` |
| Function | `CandidatePaymentInformationController::__invoke` |
| Authentication | `X-Candidate-Api-Key` or `X-Internal-Api-Key` |

Request data: candidate path ID, `electionId`, `requestID`, `timestamp`. Success data contains minimal `candidate`, `organisation`, and `candidacy` objects; private documents and identity-card details are never exposed.

### Candidate participant reporting — Wong Shun Bin

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Candidate module |
| Target | User administration module |
| URLs | `/api/v1/elections/{election}/candidates`; `/api/v1/candidates/metrics` |
| Functions | `CandidateParticipantController::__invoke/metrics` |
| Authentication | `X-Internal-Api-Key` |

User administration uses these read-only endpoints to display candidate participants and aggregate dashboard metrics. Candidate decisions remain owned by the election organiser workflow; administration never writes the Candidate-owned `candidacy` table.

### Voting status and tally — Goh Chun Wen

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Voting Process module |
| Targets | User module (status); Election/results consumers (tally) |
| URLs | `/api/v1/votes/status`; `/api/v1/elections/{election}/tally` |
| Functions | `VotingApiController::status/tally` |
| Authentication | `X-Internal-Api-Key` |

Status request data: `voterId`, `electionId`, `requestID`, `timestamp`. It returns only boolean `hasVoted`; candidate choice is intentionally omitted. Tally is released only after the Election API authoritatively reports `CLOSED`.

## Service consumption

| Consumer (PIC) | Provider call | Purpose | Client implementation |
|---|---|---|---|
| User (Chew) | Election summaries, voter catalog, organiser elections, availability, and admin overview | Discover public voter elections and resolve election metadata without reading Election-owned tables | `App\Services\User\ElectionModuleClient` |
| User (Chew) | Candidate participant and metrics endpoints | Display read-only participant records without reading or writing Candidate-owned tables | `App\Services\User\CandidateModuleClient` |
| User (Chew) | Voting `/api/v1/votes/status` | Display participation status without reading Voting-owned tables | `App\Services\User\VotingParticipationClient` |
| Election (Low) | User/Voter private-roster endpoints | Match CSV/XLSX/XLS roster rows and manage directory selections without writing User/Voter tables directly | `App\Services\VoterModuleClient` |
| Election (Low) | `PAYMENT_API_URL` | Verify hosting payment before lifecycle transition | `App\Services\Payment\HttpPaymentGateway` |
| Candidate (Wong) | Payment `/api/v1/payments/{paymentId}/status` | Display/authorize actions using authoritative candidate-deposit status | `App\Services\CandidateRegistration\PaymentStatusClient` |
| Voting (Goh) | User eligibility plus Election list/detail | Authorize voter, election state, and approved ballot through teammate APIs | `App\Services\Voting\ElectionVotingIntegration` |
| Payment (Puah) | Candidate payment-information endpoint | Obtain candidate and organisation information | Implemented in the separate Payment module when supplied |

Election verifies hosting payments with `POST /api/v1/payments/verify`. It sends the authenticated organiser, election ID, fixed amount, currency, purpose, request ID, and timestamp without a browser-supplied payment reference. Payment resolves the latest hosting transaction for that election and returns its `paymentId`, `paymentReference`, `paymentStatus`, `electionId`, payer, amount, currency, and purpose. Election treats the response as confirmed only when every returned value matches its own expected election data.

## Local configuration

Provider keys have no insecure application defaults. Configure matching consumer keys in `.env`; see `.env.example`. Payment runs as a separate teammate service (default local port `8081`). Candidate and Election integrations fail closed while it is unavailable.
