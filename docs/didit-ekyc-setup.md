# Didit eKYC setup

VoteTrust uses Didit's **hosted KYC session**. Didit owns the camera/document/selfie experience; VoteTrust never receives the selfie or document image. The User module only stores the provider, session ID, final status, timestamp, and provider reference.

## Flow

```text
Profile "Verify with eKYC"
        |
        v
Laravel POST /profile/verify creates a Didit session
        |
        v
Redirect to Didit: IC/passport capture + selfie/liveness
        |
        v
Didit POST /api/v1/webhooks/didit (signed X-Signature-V2)
        |
        v
Laravel validates HMAC + timestamp + session/user match
        |
        v
Only status "Approved" sets user.is_verified = true
```

The browser return URL is informational only. VoteTrust never trusts an `Approved` value from a query string; only the signed webhook can unlock election actions.

## 1. Set up Didit

1. Create an account at [Didit Business Console](https://business.didit.me/).
2. Create a **KYC** workflow (not KYB). For the intended flow enable:
   - ID Verification
   - Liveness
   - Face Match
   - optionally IP Analysis
3. In the document configuration, enable Malaysian ID cards and passports. Use passport support for foreign users.
4. Copy the workflow ID.
5. In **API & Webhooks**, create a V3 webhook destination:
   - URL: `https://YOUR-PUBLIC-DOMAIN/api/v1/webhooks/didit`
   - subscribed event: `status.updated`
   - copy the destination `secret_shared_key` immediately; Didit shows it only once.
6. Copy an application API key with session-create permission.

Didit must be able to reach the webhook at a public HTTPS URL. `http://votetrust.test` and `localhost` are not reachable from Didit. During local testing, expose the application with a secure tunnel and set `APP_URL` to its public HTTPS URL while testing callbacks.

## 2. Configure VoteTrust

Add these values to `.env` (never commit real keys):

```env
APP_URL=https://YOUR-PUBLIC-DOMAIN

EKYC_DRIVER=didit
DIDIT_BASE_URL=https://verification.didit.me
DIDIT_API_KEY=your_didit_application_api_key
DIDIT_WORKFLOW_ID=your_kyc_workflow_uuid
DIDIT_WEBHOOK_SECRET=your_didit_destination_secret_shared_key
DIDIT_WEBHOOK_TOLERANCE_SECONDS=300
```

Then apply the database change and clear cached configuration:

```powershell
php artisan migrate
php artisan optimize:clear
```

The added fields record the provider session safely enough for correlation:

- `verification_provider`
- `verification_session_id`
- `verification_status`
- `verification_requested_at`
- `verification_consent_at`
- `verification_last_event_id`
- `verification_failure_reason`

## 3. Test safely

1. Keep the Didit application in **Sandbox** first.
2. Use Didit's own synthetic test scenarios/documents; do not upload a real IC or passport to a demonstration environment.
3. In Didit's webhook console, use **Try Webhook** with an `Approved` `status.updated` event.
4. Confirm your user moves from `PENDING` to `APPROVED` and `is_verified` becomes true.
5. Try the VoteTrust election, voting, and candidate routes: they should be accessible only after approval.

For normal local work without Didit credentials, leave `EKYC_DRIVER=local`. That mode is an explicit development-only stub and must never be used as real identity verification.

## Implementation locations

- `app/Services/EkycVerificationService.php` — creates the Didit session and verifies webhook signatures.
- `app/Http/Controllers/Api/DiditWebhookController.php` — public webhook receiver.
- `routes/api-user.php` — `POST /api/v1/webhooks/didit`.
- `resources/views/profile/show.blade.php` — consent and redirection entry point.
- `app/Http/Middleware/EnsureVerifiedProfile.php` — blocks organising, voting, and candidate actions until approval.

The webhook uses Didit's recommended `X-Signature-V2`: HMAC-SHA256 over recursively sorted, compact, Unicode-preserved JSON, plus a five-minute timestamp window. It accepts only the event whose session ID and `vendor_data` belong to the tracked VoteTrust user.
