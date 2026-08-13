# Voting Process security and design compliance

PIC: Goh Chun Wen

This document maps the implemented Voting Process module to its approved planning. The controls are enforced in the service layer as well as at HTTP boundaries so a non-controller caller cannot bypass the vote rules.

| Planned item | Implementation | Verification |
|---|---|---|
| Input validation | `CastVoteRequest` validates browser submissions; `InputValidationDecorator` validates positive identifiers, request-ID format, strict ISO-8601 timestamps, and freshness at the application-service boundary. | Invalid form and direct-service tests verify that no vote is written and no teammate API is called. |
| Access control / IDOR | All voting pages require authenticated, active, verified accounts. `AuthorizationDecorator` authorizes the signed-in voter against User eligibility and the Election-owned ballot. Receipt lookup is scoped by both vote ID and authenticated voter ID and returns `404` for missing and non-owned records. Voting APIs require `X-Internal-Api-Key` in route middleware. | Authentication, API-key, candidate/election authorization, and receipt ownership tests. |
| Double voting / race condition | `DuplicateVoteDecorator` obtains an atomic lock keyed by voter and election, then rechecks inside a database transaction. A database unique constraint on `(voter_id, election_id)` is the final integrity boundary. | Occupied-lock and repeated-vote tests verify rejection and exactly one stored vote. |
| Decorator design pattern | `VoteCastingService` is the component contract. `BasicVoteService` is the concrete component. `VoteServiceDecorator` is the base decorator. `InputValidationDecorator`, `AuthorizationDecorator`, and `DuplicateVoteDecorator` are composed by `VoteService`. | The service test exercises the complete chain from validation through remote authorization and synchronized persistence. |
| Web services provided | `GET /api/v1/votes/status` returns only `hasVoted`; `GET /api/v1/elections/{election}/tally` releases counts only after Election reports `CLOSED`. Both use the IFA envelope and API-key middleware. | Provider contract, access-control, privacy, and closed-election tally tests. |
| Web services consumed | Voting calls User `/api/v1/voters/eligibility` and Election list/detail availability endpoints through `VotingIntegration`. Correlation IDs, timestamps, response types, identifiers, eligibility rules, and candidate lists are validated. Missing credentials and invalid/stale responses fail closed. | HTTP fakes assert both endpoint calls and `X-Internal-Api-Key` headers; negative tests assert no unsecured request is sent. |

## Cross-module deployment requirements

Set non-empty matching `USER_SERVICE_API_KEY` and `ELECTION_SERVICE_API_KEY` values in the Voting deployment and provider deployments. Voting intentionally becomes unavailable when either credential or service is unavailable. The Election module owner remains responsible for validating the Election service key at its own API boundary.
