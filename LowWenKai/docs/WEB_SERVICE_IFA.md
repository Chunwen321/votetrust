# Web Service Interface Agreement (IFA)

## A. Election availability exposed to Candidate and Voting modules

| Item | Agreement |
|---|---|
| Protocol | REST over HTTP, JSON response |
| Function | Returns lifecycle, payment, administrator approval, and action availability |
| Source module | Election Management - Low Wen Kai |
| Target modules | Candidate Registration, Voting Process, User/Voter Management |
| Method and URL | `GET /api/v1/elections/{electionId}/availability` |
| Function name | `getElectionAvailability` |

### Request parameters

| Field | Type | Requirement | Description | Format |
|---|---|---|---|---|
| `electionId` | Integer | Mandatory | Election identifier in the URL | Positive integer |
| `requestID` | String | Mandatory | Unique trace identifier | 8-64 alphanumeric, `_`, or `-` |
| `timestamp` | String | Mandatory | Time request was generated | ISO 8601; at most five minutes old |

### Response parameters

| Field | Type | Requirement | Description |
|---|---|---|---|
| `status` | String | Mandatory | `S` success, `F` failure, `E` server error |
| `message` | String | Mandatory | Human-readable result |
| `requestID` | String | Mandatory | Echoes request trace ID |
| `timestamp` | String | Mandatory | Response generation time in ISO 8601 |
| `data.lifecycleState` | String | Success | Persisted State-pattern state |
| `data.hostingPaymentVerified` | Boolean | Success | Whether a matching hosting payment is confirmed |
| `data.administratorApproved` | Boolean | Success | Whether an independent admin approved the election |
| `data.candidateRegistrationAllowed` | Boolean | Success | Gate consumed by Candidate Module |
| `data.votingAllowed` | Boolean | Success | Gate consumed by Voting Module |
| `data.nominationPeriod` | Object | Success | ISO 8601 start and end |
| `data.votingPeriod` | Object | Success | ISO 8601 start and end |

Candidate registration and voting are false unless both the payment and administrator approval gates have passed and the State/date rules permit the action.

## B. Hosting payment verification consumed from the Payment Module

| Item | Agreement |
|---|---|
| Protocol | REST over HTTP, JSON request/response |
| Function | Verifies that an organiser paid the exact election hosting fee |
| Source module | Payment - Risky Puah Kaho (local sandbox until integration) |
| Target module | Election Management - Low Wen Kai |
| Method and URL | `POST {PAYMENT_API_URL}` |
| Function name | `verifyHostingPayment` |
| Authentication | `X-API-Key` header, value agreed between modules |

### Request sent by Election Module

| Field | Type | Requirement | Description |
|---|---|---|---|
| `requestID` | String | Mandatory | Cryptographically random request trace ID |
| `timestamp` | String | Mandatory | ISO 8601 request time |
| `paymentReference` | String | Mandatory | Reference supplied by organiser |
| `purpose` | String | Mandatory | Always `ELECTION_HOSTING` |
| `payerId` | Integer | Mandatory | Organiser entity identifier |
| `electionId` | Integer | Mandatory | Election being paid for |
| `expectedAmount` | Number | Mandatory | Configured hosting fee |
| `currency` | String | Mandatory | Three-letter currency code |

### Response consumed by Election Module

The response must include `status`, `message`, `requestID`, `timestamp`, and `data`. `data` contains `paymentReference`, `paymentStatus`, `amount`, `currency`, and `payerId`. Verification succeeds only when all values match and the current State is `PENDING_PAYMENT`.

A confirmed result moves the election to `PENDING_ADMIN_APPROVAL`. It does **not** open nominations. Timeouts, non-2xx responses, malformed JSON, missing IFA fields, pending/failed payments, or mismatched values leave the election unchanged.

## C. Election approval exposed to the Admin Module

| Item | Agreement |
|---|---|
| Protocol | REST over HTTP, JSON request/response |
| Function | Allows a trusted Admin Module to approve or reject a paid election |
| Source module | Election Management - Low Wen Kai |
| Target module | System Administration |
| Method and URL | `POST /api/v1/elections/{electionId}/approval` |
| Function name | `reviewPaidElection` |
| Authentication | `X-Admin-API-Key` header plus an administrator account ID |

### Request body

| Field | Type | Requirement | Description |
|---|---|---|---|
| `requestID` | String | Mandatory | 8-64 character trace identifier |
| `timestamp` | String | Mandatory | ISO 8601; at most five minutes old |
| `adminUserId` | Integer | Mandatory | Shared `user.id` with `is_admin = 1` |
| `decision` | String | Mandatory | `APPROVE` or `REJECT` |
| `reason` | String | Mandatory for rejection | 5-250 character rejection explanation |

### Response body

| Field | Type | Requirement | Description |
|---|---|---|---|
| `status` | String | Mandatory | `S`, `F`, or `E` |
| `message` | String | Mandatory | Human-readable result |
| `requestID` | String | Mandatory | Echoed trace identifier |
| `timestamp` | String | Mandatory | Response time |
| `data.electionId` | Integer | Success | Reviewed election |
| `data.lifecycleState` | String | Success | `NOMINATION_OPEN` or `REJECTED` |
| `data.approvedBy` | Integer/null | Success | Administrator ID after approval |

The endpoint rejects missing/wrong API keys, stale timestamps, non-admin users, self-approval, unpaid elections, and elections outside `PENDING_ADMIN_APPROVAL`. Every accepted decision is written to the election's actor-attributed JSON audit history in the same database transaction as the state change.
