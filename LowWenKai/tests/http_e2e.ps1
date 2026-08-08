param(
    [string]$BaseUrl = 'http://127.0.0.1:8080',
    [int]$AdminUserId = 4,
    [switch]$KeepTestData
)

$ErrorActionPreference = 'Stop'
$script:createdElectionIds = [System.Collections.Generic.List[int]]::new()

function Assert-Contains {
    param([string]$Text, [string]$Expected, [string]$Message)
    if (-not $Text.Contains($Expected)) {
        throw "$Message Missing text: $Expected"
    }
}

function Get-CsrfToken {
    param([string]$Html)
    $match = [regex]::Match($Html, 'name="_token" value="([^"]+)"')
    if (-not $match.Success) {
        throw 'CSRF token was not found in the HTML response.'
    }
    return $match.Groups[1].Value
}

function Login-Account {
    param(
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [string]$Username,
        [string]$Password
    )
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $Session -UseBasicParsing
    $token = Get-CsrfToken $loginPage.Content
    return Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -WebSession $Session -UseBasicParsing -Body @{
        _token = $token
        username = $Username
        password = $Password
    }
}

function Create-And-PayElection {
    param(
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [string]$Title
    )
    $createPage = Invoke-WebRequest -Uri "$BaseUrl/elections/create" -WebSession $Session -UseBasicParsing
    $token = Get-CsrfToken $createPage.Content
    $now = Get-Date
    $created = Invoke-WebRequest -Uri "$BaseUrl/elections" -Method Post -WebSession $Session -UseBasicParsing -Body @{
        _token = $token
        title = $Title
        description = 'A full HTTP integration test for payment and independent administrator approval.'
        eligibility_mode = 'RESTRICTED'
        eligibility_minimum_age = '18'
        eligibility_occupation = 'STUDENT'
        eligibility_nationality = 'ANY'
        nomination_start = $now.AddDays(-2).ToString('yyyy-MM-ddTHH:mm')
        nomination_end = $now.AddHours(-2).ToString('yyyy-MM-ddTHH:mm')
        voting_start = $now.AddHours(-1).ToString('yyyy-MM-ddTHH:mm')
        voting_end = $now.AddHours(1).ToString('yyyy-MM-ddTHH:mm')
        hosting_fee = '100.00'
        currency = 'MYR'
    }
    Assert-Contains $created.Content 'Election draft created' 'Election creation failed.'
    $idMatch = [regex]::Match($created.Content, 'Election #(\d+)')
    if (-not $idMatch.Success) {
        throw 'Created election ID was not found.'
    }
    $electionId = [int]$idMatch.Groups[1].Value
    $script:createdElectionIds.Add($electionId)
    $token = Get-CsrfToken $created.Content

    $submitted = Invoke-WebRequest -Uri "$BaseUrl/elections/$electionId/transition" -Method Post -WebSession $Session -UseBasicParsing -Body @{
        _token = $token
        action = 'submit_for_payment'
        current_password = 'VoteTrust!2026'
    }
    Assert-Contains $submitted.Content 'Pending payment' 'Submission did not reach PENDING_PAYMENT.'
    $token = Get-CsrfToken $submitted.Content

    $paid = Invoke-WebRequest -Uri "$BaseUrl/elections/$electionId/transition" -Method Post -WebSession $Session -UseBasicParsing -Body @{
        _token = $token
        action = 'verify_payment'
        payment_reference = 'VT-HOST-PAID-001'
        current_password = 'VoteTrust!2026'
    }
    Assert-Contains $paid.Content 'Waiting for admin approval' 'Confirmed payment did not reach the admin gate.'

    return @{ Id = $electionId; Html = $paid.Content; Token = (Get-CsrfToken $paid.Content) }
}

try {
    $organizerSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $organizerHome = Login-Account $organizerSession 'LowWenKaiOrganizer' 'VoteTrust!2026'
    Assert-Contains $organizerHome.Content 'Organiser workspace' 'Organiser login failed.'

    $webElection = Create-And-PayElection $organizerSession 'E2E Administrator Web Approval 2026'
    $selfApproval = Invoke-WebRequest -Uri "$BaseUrl/elections/$($webElection.Id)/transition" -Method Post -WebSession $organizerSession -UseBasicParsing -Body @{
        _token = $webElection.Token
        action = 'approve'
        current_password = 'VoteTrust!2026'
    }
    Assert-Contains $selfApproval.Content 'system administrator account is required' 'Organiser self-approval was not blocked.'

    $adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $adminHome = Login-Account $adminSession 'SystemAdmin' 'VoteTrustAdmin!2026'
    Assert-Contains $adminHome.Content 'Election approval workspace' 'Administrator login failed.'
    $adminReview = Invoke-WebRequest -Uri "$BaseUrl/admin/elections/$($webElection.Id)" -WebSession $adminSession -UseBasicParsing
    $adminToken = Get-CsrfToken $adminReview.Content
    $adminApproved = Invoke-WebRequest -Uri "$BaseUrl/admin/elections/$($webElection.Id)/transition" -Method Post -WebSession $adminSession -UseBasicParsing -Body @{
        _token = $adminToken
        action = 'approve'
        current_password = 'VoteTrustAdmin!2026'
    }
    Assert-Contains $adminApproved.Content 'Nominations' 'Administrator web approval failed.'

    $apiElection = Create-And-PayElection $organizerSession 'E2E Administrator API Approval 2026'
    $requestId = 'ADMIN-E2E-' + ([guid]::NewGuid().ToString('N').Substring(0, 16).ToUpper())
    $apiResponse = Invoke-RestMethod -Uri "$BaseUrl/api/v1/elections/$($apiElection.Id)/approval" -Method Post -ContentType 'application/json' -Headers @{
        'X-Admin-API-Key' = 'local-admin-integration-key'
    } -Body (@{
        requestID = $requestId
        timestamp = (Get-Date).ToString('o')
        decision = 'APPROVE'
        adminUserID = $AdminUserId
        reason = 'Payment and election configuration verified through the Admin Module contract.'
    } | ConvertTo-Json)

    if ($apiResponse.status -ne 'S' -or $apiResponse.data.lifecycleState -ne 'NOMINATION_OPEN') {
        throw 'Administrator approval API did not return a successful NOMINATION_OPEN response.'
    }

    Write-Output "PASS organiser login"
    Write-Output "PASS payment stops at PENDING_ADMIN_APPROVAL"
    Write-Output "PASS organiser self-approval blocked"
    Write-Output "PASS administrator web approval"
    Write-Output "PASS administrator API approval"
} finally {
    if (-not $KeepTestData -and $script:createdElectionIds.Count -gt 0) {
        $mysql = 'C:\xampp\mysql\bin\mysql.exe'
        if (Test-Path -LiteralPath $mysql) {
            $idList = [string]::Join(',', $script:createdElectionIds)
            & $mysql -u root -D election_db -e "DELETE FROM election WHERE id IN ($idList) AND title IN ('E2E Administrator Web Approval 2026','E2E Administrator API Approval 2026');" | Out-Null
            Write-Output "CLEANUP removed test elections: $idList"
        }
    }
}
