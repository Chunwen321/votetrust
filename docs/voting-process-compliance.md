# Voting Process security and design compliance

PIC: Goh Chun Wen

This document maps the implemented Voting Process module to its approved planning. The controls are enforced in the service layer as well as at HTTP boundaries so a non-controller caller cannot bypass the vote rules.

| Planned item | Implementation | Verification |
|---|---|---|
| Input validation | `CastVoteRequest` validates browser submissions; `InputValidationDecorator` validates positive identifiers, request-ID format, strict ISO-8601 timestamps, and freshness at the application-service boundary. | Invalid form and direct-service tests verify that no vote is written and no teammate API is called. |
| Access control / IDOR | All voting pages require authenticated, active, verified accounts. `AuthorizationDecorator` authorizes the signed-in voter against User eligibility and the Election-owned ballot. Receipt lookup is scoped by both vote ID and authenticated voter ID and returns `404` for missing and non-owned records. Voting APIs require `X-Internal-Api-Key` in route middleware. | Tests reject guests, unverified/disabled accounts, missing/wrong API keys, candidates from another election, and another voter's receipt without side effects. |
| Replay protection | `ReplayProtectionDecorator` rejects a previously successful request ID before remote authorization. Successful request IDs are stored, and a unique database index is the atomic fallback when identical requests arrive simultaneously. | The replay test proves no second teammate API call or vote; the live synchronized-request exercise covers the simultaneous case. |
| Double voting / race condition | `DuplicateVoteDecorator` obtains an atomic lock keyed by voter and election, then rechecks inside a database transaction. A database unique constraint on `(voter_id, election_id)` is the final integrity boundary. | Occupied-lock and repeated-vote tests verify rejection. The local five-request race produced one receipt, four returns to the ballot, and exactly one stored row. |
| Abuse throttling | The cast route uses the named `voting.cast` limiter, keyed by authenticated voter and election, with a configurable per-minute limit. | The route test proves the configured third request returns `429` and creates no vote or outbound request. |
| Decorator design pattern | `VoteCastingService` is the component contract. `BasicVoteService` is the concrete component. `VoteServiceDecorator` is the base decorator. `InputValidationDecorator`, `ReplayProtectionDecorator`, `AuthorizationDecorator`, and `DuplicateVoteDecorator` are composed by `VoteService`. | The service test exercises the complete chain from validation through replay detection, remote authorization, synchronized persistence, and the database constraints. |
| Web services provided | `GET /api/v1/votes/status` returns only `hasVoted`; `GET /api/v1/elections/{election}/tally` releases counts only after Election reports `CLOSED`. Both use the IFA envelope and API-key middleware. | Provider contract, access-control, privacy, and closed-election tally tests. |
| Web services consumed | Voting calls User `/api/v1/voters/eligibility` and Election list/detail availability endpoints through `VotingIntegration`. Correlation IDs, timestamps, response types, identifiers, eligibility rules, and candidate lists are validated. Missing credentials and invalid/stale responses fail closed. | HTTP fakes assert both endpoint calls and `X-Internal-Api-Key` headers; negative tests assert no unsecured request is sent. |

## Cross-module deployment requirements

Set non-empty matching `USER_SERVICE_API_KEY` and `ELECTION_SERVICE_API_KEY` values in the Voting deployment and provider deployments. Voting intentionally becomes unavailable when either credential or service is unavailable. The Election module owner remains responsible for validating the Election service key at its own API boundary.

Use a shared lock-capable `CACHE_STORE` such as `database` or Redis when more than one PHP worker can accept votes; the `array` store is suitable only for isolated tests. Set `VOTING_CAST_RATE_LIMIT_PER_MINUTE` for the expected traffic and keep the database unique constraints enabled.

For repeatable concurrency evidence, follow [the local voting race simulation guide](voting-race-simulation.md).
