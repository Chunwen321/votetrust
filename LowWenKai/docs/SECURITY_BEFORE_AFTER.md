# Secure Coding: Before and After Evidence

## Recommended presentation method

Use the terminal-driven comparison together with the protected website. This is stronger than commenting insecure production code in and out because it is repeatable, produces observable results, and never leaves a vulnerable web endpoint in the submission.

The intentionally insecure behaviour exists only in `tests/Security/InsecureElectionWorkflow.php`. It has no route. The production path goes through the MVC controller, `ElectionService`, `ReauthenticationGuard`, the active State object, and the database transaction.

Run:

```powershell
C:\xampp\php\php.exe tests\security_demo.php
```

Verified comparison:

```text
BEFORE  No password supplied                        NOMINATION_OPEN
BEFORE  Organiser bypasses admin approval           NOMINATION_OPEN (VULNERABLE)
AFTER   No password supplied                        BLOCKED (PASSWORD REQUIRED)
AFTER   Unconfirmed payment supplied                PENDING_PAYMENT (PAYMENT GATE HELD)
AFTER   Confirmed payment supplied                  PENDING_ADMIN_APPROVAL (ADMIN GATE HELD)
AFTER   Organiser attempts self-approval            PENDING_ADMIN_APPROVAL (ORGANISER FORBIDDEN)
AFTER   Independent administrator approves          NOMINATION_OPEN
```

## Threat 1: critical actions after session misuse

### Before

A legacy workflow trusts an open browser session and accepts a critical transition without proving knowledge of the current password. Anyone who obtains that session could submit, withdraw, open voting, close, or cancel an election.

### After

Every organizer browser transition calls `ReauthenticationGuard::assertPassword()`. `password_verify()` checks the current password against the stored adaptive hash. Failure returns HTTP 403, leaves the election unchanged, and records a security event without logging the password.

Tutor demonstration:

1. Show the first BEFORE/AFTER pair in the terminal.
2. In the organizer page, submit an action with an empty or wrong current password.
3. Show that the same State remains visible.
4. Show `app/Security/ReauthenticationGuard.php`.

## Threat 2: payment and administrator-approval bypass

### Before

Direct status assignment permits a user to skip payment or admin review and write `NOMINATION_OPEN` immediately. This breaks the required division of responsibility: the organizer would create, pay for, and approve the same election.

### After

The server accepts an allow-listed action, never a target status. `PendingPaymentState` verifies the teammate Payment API response and moves only to `PENDING_ADMIN_APPROVAL`. `PendingAdminApprovalState` requires a separate account with `user.is_admin = 1`, rejects self-approval, and records approve/reject decisions with the actor. Only approval moves to `NOMINATION_OPEN`. State/date rules, a database transaction, and optimistic locking prevent skipping and inconsistent concurrent changes.

Tutor demonstration:

1. Show the remaining BEFORE/AFTER terminal lines.
2. Verify a pending payment remains `PENDING_PAYMENT`.
3. Verify a confirmed payment stops at `PENDING_ADMIN_APPROVAL`.
4. Show that the organizer has no approval permission.
5. Log in as `SystemAdmin`, approve from the queue, and show the actor-attributed audit trail.
6. Run `tests/run.php` and `tests/http_e2e.ps1`.

## Why not use commented insecure code?

Commenting one implementation out and uncommenting another can demonstrate source differences, but it does not prove which path the website executed and risks submitting the unsafe path. The CLI legacy fixture gives the tutor the requested behavioural comparison while the routable application remains secure at all times.
