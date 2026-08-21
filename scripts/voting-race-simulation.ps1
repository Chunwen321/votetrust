[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [uri] $BaseUrl,

    [Parameter(Mandatory)]
    [ValidateRange(1, [int]::MaxValue)]
    [int] $ElectionId,

    [ValidateRange(2, 50)]
    [int] $Requests = 5,

    [ValidateNotNullOrEmpty()]
    [string] $Email = 'voterjohn@votetrust.test',

    [Nullable[int]] $CandidateId
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-HtmlAttributeValue {
    param(
        [Parameter(Mandatory)] [string] $Tag,
        [Parameter(Mandatory)] [string] $AttributeName
    )

    $pattern = '\b' + [regex]::Escape($AttributeName) + '\s*=\s*(?:"([^"]*)"|''([^'']*)''|([^\s>]+))'
    $match = [regex]::Match($Tag, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

    if (-not $match.Success) {
        return $null
    }

    foreach ($groupNumber in 1..3) {
        if ($match.Groups[$groupNumber].Success) {
            return [System.Net.WebUtility]::HtmlDecode($match.Groups[$groupNumber].Value)
        }
    }

    return $null
}

function Get-HtmlForm {
    param(
        [Parameter(Mandatory)] [string] $Html,
        [string] $Id,
        [string] $ClassToken
    )

    $options = [System.Text.RegularExpressions.RegexOptions]::IgnoreCase -bor
        [System.Text.RegularExpressions.RegexOptions]::Singleline

    foreach ($match in [regex]::Matches($Html, '<form\b[^>]*>.*?</form>', $options)) {
        $openingTag = [regex]::Match($match.Value, '^<form\b[^>]*>', $options).Value

        if ($Id) {
            $formId = Get-HtmlAttributeValue -Tag $openingTag -AttributeName 'id'
            if ($formId -eq $Id) {
                return $match.Value
            }
        }

        if ($ClassToken) {
            $classValue = Get-HtmlAttributeValue -Tag $openingTag -AttributeName 'class'
            $classes = @($classValue -split '\s+' | Where-Object { $_ })
            if ($classes -contains $ClassToken) {
                return $match.Value
            }
        }
    }

    return $null
}

function Get-HtmlInputValues {
    param(
        [Parameter(Mandatory)] [string] $Html,
        [Parameter(Mandatory)] [string] $Name
    )

    $values = @()
    foreach ($match in [regex]::Matches($Html, '<input\b[^>]*>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        $inputName = Get-HtmlAttributeValue -Tag $match.Value -AttributeName 'name'
        if ($inputName -ne $Name) {
            continue
        }

        $value = Get-HtmlAttributeValue -Tag $match.Value -AttributeName 'value'
        if ($null -ne $value) {
            $values += $value
        }
    }

    return @($values)
}

function Resolve-FormAction {
    param(
        [Parameter(Mandatory)] [uri] $PageUri,
        [Parameter(Mandatory)] [string] $FormHtml
    )

    $openingTag = [regex]::Match(
        $FormHtml,
        '^<form\b[^>]*>',
        [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
    ).Value
    $action = Get-HtmlAttributeValue -Tag $openingTag -AttributeName 'action'

    if ([string]::IsNullOrWhiteSpace($action)) {
        return $PageUri
    }

    return [uri]::new($PageUri, $action)
}

function Assert-SameOrigin {
    param(
        [Parameter(Mandatory)] [uri] $ExpectedOrigin,
        [Parameter(Mandatory)] [uri] $ActualUri,
        [Parameter(Mandatory)] [string] $Purpose
    )

    $sameScheme = $ExpectedOrigin.Scheme.Equals($ActualUri.Scheme, [System.StringComparison]::OrdinalIgnoreCase)
    $sameHost = $ExpectedOrigin.DnsSafeHost.Equals($ActualUri.DnsSafeHost, [System.StringComparison]::OrdinalIgnoreCase)

    if (-not $sameScheme -or -not $sameHost -or $ExpectedOrigin.Port -ne $ActualUri.Port) {
        throw "Safety stop: the $Purpose points outside the BaseUrl origin ('$($ActualUri.AbsoluteUri)'). Check APP_URL and the Laragon virtual host."
    }
}

if (-not $BaseUrl.IsAbsoluteUri -or $BaseUrl.Scheme -notin @('http', 'https')) {
    throw 'BaseUrl must be an absolute http:// or https:// URL.'
}

if ($BaseUrl.UserInfo -or $BaseUrl.Query -or $BaseUrl.Fragment) {
    throw 'BaseUrl must not contain credentials, a query string, or a fragment.'
}

$targetHost = $BaseUrl.DnsSafeHost
$parsedAddress = $null
$hostIsAddress = [System.Net.IPAddress]::TryParse($targetHost, [ref] $parsedAddress)
$hostIsLoopback = $targetHost.Equals('localhost', [System.StringComparison]::OrdinalIgnoreCase) -or
    ($hostIsAddress -and [System.Net.IPAddress]::IsLoopback($parsedAddress))
$hostIsTestDomain = $targetHost.EndsWith('.test', [System.StringComparison]::OrdinalIgnoreCase)

if (-not $hostIsLoopback -and -not $hostIsTestDomain) {
    throw "Safety stop: '$targetHost' is neither a loopback host nor a .test host. This harness refuses production-like targets."
}

if ($hostIsTestDomain) {
    $resolvedAddresses = @([System.Net.Dns]::GetHostAddresses($targetHost))
    if ($resolvedAddresses.Count -eq 0 -or @($resolvedAddresses | Where-Object {
        -not [System.Net.IPAddress]::IsLoopback($_)
    }).Count -gt 0) {
        throw "Safety stop: '$targetHost' must resolve only to a loopback address. Check the Laragon hosts entry."
    }
}

if ($null -ne $CandidateId -and $CandidateId -lt 1) {
    throw 'CandidateId must be a positive integer when supplied.'
}

$baseAddress = $BaseUrl.AbsoluteUri.TrimEnd('/')
$loginPageUri = [uri]::new("$baseAddress/login")
$ballotPageUri = [uri]::new("$baseAddress/voting/elections/$ElectionId")
$webSession = $null

Write-Host 'VoteTrust voting race simulation (local test environments only)'
Write-Host "Target: $baseAddress"
Write-Host "Election: $ElectionId | Concurrent requests: $Requests | Account: $Email"
Write-Host 'This script will not read, modify, truncate, migrate, seed, or reset the database.'

$securePassword = Read-Host 'Demo voter password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
$plainPassword = $null

try {
    Write-Host 'Loading the real login form...'
    $loginPage = Invoke-WebRequest -Uri $loginPageUri -Method Get -SessionVariable webSession `
        -UseBasicParsing -TimeoutSec 30
    $loginForm = Get-HtmlForm -Html $loginPage.Content -Id 'login-panel'
    if (-not $loginForm) {
        throw 'The login form was not found. Confirm BaseUrl points to this VoteTrust application.'
    }

    $loginTokens = @(Get-HtmlInputValues -Html $loginForm -Name '_token')
    if ($loginTokens.Count -ne 1) {
        throw 'The login CSRF token could not be identified safely.'
    }

    $loginAction = Resolve-FormAction -PageUri $loginPageUri -FormHtml $loginForm
    Assert-SameOrigin -ExpectedOrigin $BaseUrl -ActualUri $loginAction -Purpose 'login form action'
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)

    Write-Host 'Signing in once with the supplied demo account...'
    $null = Invoke-WebRequest -Uri $loginAction -Method Post -WebSession $webSession `
        -UseBasicParsing -TimeoutSec 30 -ContentType 'application/x-www-form-urlencoded' -Body @{
            _token = $loginTokens[0]
            email = $Email
            password = $plainPassword
        }
}
finally {
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }

    $plainPassword = $null
    if ($securePassword -is [System.IDisposable]) {
        $securePassword.Dispose()
    }
}

Write-Host 'Loading the real ballot form...'
$ballotPage = Invoke-WebRequest -Uri $ballotPageUri -Method Get -WebSession $webSession `
    -UseBasicParsing -TimeoutSec 30
$ballotForm = Get-HtmlForm -Html $ballotPage.Content -ClassToken 'ballot-form'

if (-not $ballotForm) {
    if ($ballotPage.Content -match 'already been recorded') {
        throw 'This voter has already voted in the selected election. Manually reset only this local demo vote or choose a fresh demo voter/election.'
    }

    throw 'The ballot form was not available. Check the password, account status/eKYC, voter eligibility, election state, candidates, and whether this voter already voted.'
}

$csrfTokens = @(Get-HtmlInputValues -Html $ballotForm -Name '_token')
$requestIds = @(Get-HtmlInputValues -Html $ballotForm -Name 'request_id')
$timestamps = @(Get-HtmlInputValues -Html $ballotForm -Name 'timestamp')
$candidateValues = @(Get-HtmlInputValues -Html $ballotForm -Name 'candidate_id')

if ($csrfTokens.Count -ne 1 -or $requestIds.Count -ne 1 -or $timestamps.Count -ne 1) {
    throw 'The ballot security fields could not be identified safely. The page structure may have changed.'
}

$candidateIds = @($candidateValues | ForEach-Object {
    $parsedCandidateId = 0
    if ([int]::TryParse($_, [ref] $parsedCandidateId) -and $parsedCandidateId -gt 0) {
        $parsedCandidateId
    }
} | Select-Object -Unique)

if ($candidateIds.Count -eq 0) {
    throw 'The ballot contains no selectable candidate.'
}

$selectedCandidateId = if ($null -ne $CandidateId) { [int] $CandidateId } else { $candidateIds[0] }
if ($candidateIds -notcontains $selectedCandidateId) {
    throw "CandidateId $selectedCandidateId is not a candidate on election $ElectionId's current ballot. Available IDs: $($candidateIds -join ', ')."
}

$castAction = Resolve-FormAction -PageUri $ballotPageUri -FormHtml $ballotForm
Assert-SameOrigin -ExpectedOrigin $BaseUrl -ActualUri $castAction -Purpose 'ballot form action'
$cookieHeader = $webSession.Cookies.GetCookieHeader($castAction)
if ([string]::IsNullOrWhiteSpace($cookieHeader)) {
    throw 'No authenticated session cookie was established after login.'
}

Write-Host "Candidate selected: $selectedCandidateId"
Write-Host 'Preparing independent background processes at a shared start gate...'

$startEventName = 'Local\VoteTrustVotingRaceStart_' + [guid]::NewGuid().ToString('N')
$startGate = [System.Threading.EventWaitHandle]::new(
    $false,
    [System.Threading.EventResetMode]::ManualReset,
    $startEventName
)
$readyGates = @()
$jobs = @()

$worker = {
    param(
        [int] $Index,
        [string] $StartEventName,
        [string] $ReadyEventName,
        [string] $CastUrl,
        [string] $BallotUrl,
        [string] $CookieHeader,
        [string] $CsrfToken,
        [string] $RequestId,
        [string] $Timestamp,
        [int] $CandidateId
    )

    $startEvent = $null
    $readyEvent = $null
    $client = $null
    $request = $null
    $response = $null

    try {
        Add-Type -AssemblyName System.Net.Http
        $startEvent = [System.Threading.EventWaitHandle]::OpenExisting($StartEventName)
        $readyEvent = [System.Threading.EventWaitHandle]::OpenExisting($ReadyEventName)

        $handler = [System.Net.Http.HttpClientHandler]::new()
        $handler.AllowAutoRedirect = $false
        $handler.UseCookies = $false
        $handler.AutomaticDecompression = [System.Net.DecompressionMethods]::GZip -bor
            [System.Net.DecompressionMethods]::Deflate
        $client = [System.Net.Http.HttpClient]::new($handler)
        $client.Timeout = [TimeSpan]::FromSeconds(45)

        $formValues = [System.Collections.Generic.Dictionary[string, string]]::new()
        $formValues.Add('_token', $CsrfToken)
        $formValues.Add('request_id', $RequestId)
        $formValues.Add('timestamp', $Timestamp)
        $formValues.Add('candidate_id', [string] $CandidateId)

        $request = [System.Net.Http.HttpRequestMessage]::new(
            [System.Net.Http.HttpMethod]::Post,
            [uri] $CastUrl
        )
        $request.Content = [System.Net.Http.FormUrlEncodedContent]::new($formValues)
        $null = $request.Headers.TryAddWithoutValidation('Cookie', $CookieHeader)
        $request.Headers.Referrer = [uri] $BallotUrl
        $request.Headers.UserAgent.ParseAdd('VoteTrust-Local-Race-Simulation/1.0')
        $request.Headers.Accept.ParseAdd('text/html,application/xhtml+xml')

        $readyEvent.Set() | Out-Null
        $startEvent.WaitOne() | Out-Null

        $startedAt = [DateTime]::UtcNow
        $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
        $response = $client.SendAsync($request).GetAwaiter().GetResult()
        $stopwatch.Stop()

        $statusCode = [int] $response.StatusCode
        $location = if ($null -ne $response.Headers.Location) {
            $response.Headers.Location.ToString()
        } else {
            ''
        }
        $locationPath = ''

        if ($location) {
            try {
                $resolvedLocation = [uri]::new([uri] $CastUrl, $location)
                $locationPath = $resolvedLocation.PathAndQuery
            }
            catch {
                $locationPath = $location
            }
        }

        $ballotPath = ([uri] $BallotUrl).AbsolutePath.TrimEnd('/')
        $category = if ($statusCode -ge 300 -and $statusCode -lt 400) {
            if ($locationPath -match '/voting/receipts/\d+/?(?:\?.*)?$') {
                'Receipt'
            }
            elseif ($locationPath.Split('?')[0].TrimEnd('/') -eq $ballotPath) {
                'ReturnedToBallot'
            }
            else {
                'OtherRedirect'
            }
        }
        elseif ($statusCode -eq 419) {
            'CsrfRejected'
        }
        elseif ($statusCode -eq 429) {
            'RateLimited'
        }
        elseif ($statusCode -ge 500) {
            'ServerError'
        }
        else {
            'UnexpectedHttpResponse'
        }

        [pscustomobject]@{
            Kind = 'Result'
            Request = $Index
            StartedUtc = $startedAt.ToString('HH:mm:ss.fff')
            Milliseconds = $stopwatch.ElapsedMilliseconds
            Status = $statusCode
            Category = $category
            Location = $locationPath
            Error = ''
        }
    }
    catch {
        [pscustomobject]@{
            Kind = 'Result'
            Request = $Index
            StartedUtc = ''
            Milliseconds = 0
            Status = 0
            Category = 'TransportError'
            Location = ''
            Error = $_.Exception.Message
        }
    }
    finally {
        if ($null -ne $response) { $response.Dispose() }
        if ($null -ne $request) { $request.Dispose() }
        if ($null -ne $client) { $client.Dispose() }
        if ($null -ne $readyEvent) { $readyEvent.Dispose() }
        if ($null -ne $startEvent) { $startEvent.Dispose() }
    }
}

try {
    foreach ($index in 1..$Requests) {
        $readyEventName = 'Local\VoteTrustVotingRaceReady_' + [guid]::NewGuid().ToString('N')
        $readyGate = [System.Threading.EventWaitHandle]::new(
            $false,
            [System.Threading.EventResetMode]::ManualReset,
            $readyEventName
        )
        $readyGates += $readyGate

        $jobs += Start-Job -ScriptBlock $worker -ArgumentList @(
            $index,
            $startEventName,
            $readyEventName,
            $castAction.AbsoluteUri,
            $ballotPageUri.AbsoluteUri,
            $cookieHeader,
            $csrfTokens[0],
            $requestIds[0],
            $timestamps[0],
            $selectedCandidateId
        )
    }

    $readyDeadline = [DateTime]::UtcNow.AddSeconds(45)
    do {
        $readyCount = @($readyGates | Where-Object { $_.WaitOne(0) }).Count
        if ($readyCount -eq $Requests) {
            break
        }

        $failedBeforeStart = @($jobs | Where-Object { $_.State -in @('Failed', 'Stopped', 'Disconnected') })
        if ($failedBeforeStart.Count -gt 0) {
            throw 'One or more background jobs failed before reaching the common start gate.'
        }

        Start-Sleep -Milliseconds 100
    } while ([DateTime]::UtcNow -lt $readyDeadline)

    if ($readyCount -ne $Requests) {
        throw "Only $readyCount of $Requests workers became ready within 45 seconds."
    }

    Write-Host 'All workers are ready. Releasing the common start gate now.'
    $startGate.Set() | Out-Null

    $completionDeadline = [DateTime]::UtcNow.AddSeconds(90)
    while (@($jobs | Where-Object { $_.State -in @('NotStarted', 'Running') }).Count -gt 0 -and
        [DateTime]::UtcNow -lt $completionDeadline) {
        Start-Sleep -Milliseconds 200
    }

    $unfinishedJobs = @($jobs | Where-Object { $_.State -in @('NotStarted', 'Running') })
    if ($unfinishedJobs.Count -gt 0) {
        $unfinishedJobs | Stop-Job
        Write-Warning "$($unfinishedJobs.Count) worker(s) exceeded the 90-second completion limit and were stopped."
    }

    $results = @($jobs | Receive-Job | Where-Object { $_.Kind -eq 'Result' })
    $seenRequestNumbers = @($results | Select-Object -ExpandProperty Request)
    foreach ($jobIndex in 1..$Requests) {
        if ($seenRequestNumbers -notcontains $jobIndex) {
            $job = $jobs[$jobIndex - 1]
            $reason = if ($job.ChildJobs.Count -gt 0 -and $job.ChildJobs[0].JobStateInfo.Reason) {
                $job.ChildJobs[0].JobStateInfo.Reason.Message
            } else {
                "Worker ended in state $($job.State)."
            }
            $results += [pscustomobject]@{
                Kind = 'Result'
                Request = $jobIndex
                StartedUtc = ''
                Milliseconds = 0
                Status = 0
                Category = 'WorkerError'
                Location = ''
                Error = $reason
            }
        }
    }

    Write-Host ''
    Write-Host 'Per-request results (redirects were not followed):'
    $results | Sort-Object Request | Format-Table Request, StartedUtc, Milliseconds, Status, Category, Location, Error -AutoSize

    Write-Host 'Summary:'
    $summary = @($results | Group-Object Category | Sort-Object Name | ForEach-Object {
        [pscustomobject]@{ Category = $_.Name; Count = $_.Count }
    })
    $summary | Format-Table -AutoSize

    $receiptCount = @($results | Where-Object { $_.Category -eq 'Receipt' }).Count
    $returnedCount = @($results | Where-Object { $_.Category -eq 'ReturnedToBallot' }).Count

    if ($receiptCount -gt 1) {
        Write-Error "INVARIANT FAILED: $receiptCount requests received vote receipts. Expected no more than one. Inspect the local database and server logs before claiming race safety."
    }
    elseif ($receiptCount -eq 1 -and $returnedCount -eq ($Requests - 1)) {
        Write-Host 'INVARIANT OBSERVED: one receipt and all remaining requests redirected back as duplicate/rejected.' -ForegroundColor Green
        Write-Host 'For final evidence, manually confirm that exactly one local vote row exists for this voter/election.'
    }
    else {
        Write-Warning 'INCONCLUSIVE: expected one receipt and all other requests redirected back. Review RateLimited, CSRF, server, worker, transport, and other redirect outcomes above.'
    }
}
finally {
    if ($jobs.Count -gt 0) {
        $jobs | Where-Object { $_.State -in @('NotStarted', 'Running') } | Stop-Job
        $jobs | Remove-Job -Force
    }

    foreach ($readyGate in $readyGates) {
        $readyGate.Dispose()
    }

    $startGate.Dispose()
}
