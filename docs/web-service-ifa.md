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
| Protocol | REST/JSON (`POST`) |
| Source | User/Voter module |
| Target | Election module |
| URLs | `/api/v1/voters/private-enrollments`; `/api/v1/voters/private-enrollments/manual` |
| Functions | `PrivateVoterEnrollmentController::__invoke`; `ManualPrivateVoterEnrollmentController::__invoke` |
| Authentication | `X-User-Api-Key` or `X-Internal-Api-Key` |

The Election module parses an organiser CSV, XLSX, or XLS file in memory and sends only `electionId`, `organizerUserId`, and a `voters` array of `{row, fullName, identityNumber}` records. The file header remains `full_name,ic_number`; `ic_number` values may hold either a Malaysian IC or a passport number. The User/Voter provider normalises the identity number, requires an exact normalised full-name match for imports, and creates or replaces only its own `voter` records. Source files are never stored. The response returns `requested`, `assigned`, `removed`, `totalAssigned`, `unmatchedCount`, and safe row/reason diagnostics; Election stores only audit-safe counts.

For an organiser's one-person assignment, Election calls the `/manual` endpoint with `electionId`, `organizerUserId`, and `identityNumber`. The provider returns only `assigned`, a safe `reasonCode`, and `totalAssigned`; it does not disclose a matched member's details. A successful manual assignment is preserved when a later spreadsheet replaces CSV/XLS/XLSX-derived rows.

### Election availability and ballot — Low Wen Kai

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Election module |
| Target | Voting module |
| URLs | `/api/v1/elections/available`; `/api/v1/elections/{election}/availability` |
| Functions | `ElectionAvailabilityController::index/show` |

Both requests require `requestID` and `timestamp`. The list returns voting-open summaries. Detail returns lifecycle state, eligibility rules, permission booleans, periods, and an approved-only `ballot` containing `id`, `candidateId`, `name`, `position`, and `manifesto`.

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
| User (Chew) | Voting `/api/v1/votes/status` | Display participation status without reading Voting-owned tables | `App\Services\User\VotingParticipationClient` |
| Election (Low) | User/Voter private-enrolment endpoints | Match CSV/XLSX/XLS roster rows or manually assign a voter by IC/passport without writing User/Voter tables directly | `App\Services\VoterModuleClient` |
| Election (Low) | `PAYMENT_API_URL` | Verify hosting payment before lifecycle transition | `App\Services\Payment\HttpPaymentGateway` |
| Candidate (Wong) | Payment `/api/v1/payments/{paymentId}/status` | Display/authorize actions using authoritative candidate-deposit status | `App\Services\CandidateRegistration\PaymentStatusClient` |
| Voting (Goh) | User eligibility plus Election list/detail | Authorize voter, election state, and approved ballot through teammate APIs | `App\Services\Voting\ElectionVotingIntegration` |
| Payment (Puah) | Candidate payment-information endpoint | Obtain candidate and organisation information | Implemented in the separate Payment module when supplied |

## Local configuration

Provider keys have no insecure application defaults. Configure matching consumer keys in `.env`; see `.env.example`. Payment runs as a separate teammate service (default local port `8081`). Candidate and Election integrations fail closed while it is unavailable.
