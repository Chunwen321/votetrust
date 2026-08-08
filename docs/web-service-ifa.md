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

Request data: `voterId`, `electionId`, `requestID`, `timestamp`. Success data: `voterId`, `electionId`, boolean `eligible`, and enrolment `status`.

### Election availability and ballot — Low Wen Kai

| IFA item | Value |
|---|---|
| Protocol | REST/JSON (`GET`) |
| Source | Election module |
| Target | Voting module |
| URLs | `/api/v1/elections/available`; `/api/v1/elections/{election}/availability` |
| Functions | `ElectionAvailabilityController::index/show` |

Both requests require `requestID` and `timestamp`. The list returns voting-open summaries. Detail returns lifecycle state, eligibility rules, permission booleans, periods, and an approved-only `ballot` containing `id`, `candidateId`, `name`, `position`, and `manifesto`.

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
| Election (Low) | `PAYMENT_API_URL` | Verify hosting payment before lifecycle transition | `App\Services\Payment\HttpPaymentGateway` |
| Candidate (Wong) | Payment `/api/v1/payments/{paymentId}/status` | Display/authorize actions using authoritative candidate-deposit status | `App\Services\CandidateRegistration\PaymentStatusClient` |
| Voting (Goh) | User eligibility plus Election list/detail | Authorize voter, election state, and approved ballot through teammate APIs | `App\Services\Voting\ElectionVotingIntegration` |
| Payment (Puah) | Candidate payment-information endpoint | Obtain candidate and organisation information | Implemented in the separate Payment module when supplied |

## Local configuration

Provider keys have no insecure application defaults. Configure matching consumer keys in `.env`; see `.env.example`. Payment runs as a separate teammate service (default local port `8081`). Candidate and Election integrations fail closed while it is unavailable.
