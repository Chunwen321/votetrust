[CmdletBinding()]
param(
    [string] $LaragonRoot = 'C:\laragon',
    [switch] $BackupExisting
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $PSBoundParameters.ContainsKey('LaragonRoot') -and $env:VOTETRUST_LARAGON_ROOT) {
    $LaragonRoot = $env:VOTETRUST_LARAGON_ROOT
}

$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$laragonRootPath = [System.IO.Path]::GetFullPath($LaragonRoot)
$laragonWwwPath = Join-Path $laragonRootPath 'www'
$sitePath = Join-Path $laragonWwwPath 'votetrust'

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan') -PathType Leaf) -or
    -not (Test-Path -LiteralPath (Join-Path $projectRoot 'public\index.php') -PathType Leaf)) {
    throw "VoteTrust's Laravel entry files were not found under '$projectRoot'."
}

if (-not (Test-Path -LiteralPath $laragonWwwPath -PathType Container)) {
    throw "Laragon's www directory was not found at '$laragonWwwPath'. Pass -LaragonRoot or set VOTETRUST_LARAGON_ROOT."
}

$pathComparer = [System.StringComparer]::OrdinalIgnoreCase
$normalizedProjectRoot = $projectRoot.TrimEnd('\', '/')
$normalizedSitePath = $sitePath.TrimEnd('\', '/')

if ($pathComparer.Equals($normalizedProjectRoot, $normalizedSitePath)) {
    Write-Host "VoteTrust is already cloned directly into Laragon: $sitePath"
    Write-Host 'Start or reload Laragon, then open http://votetrust.test'
    exit 0
}

if (Test-Path -LiteralPath $sitePath) {
    $existingItem = Get-Item -LiteralPath $sitePath -Force
    $existingTarget = $null

    if ($existingItem.LinkType -in @('Junction', 'SymbolicLink')) {
        $targetValue = @($existingItem.Target)[0]

        if ($targetValue) {
            $existingTarget = [System.IO.Path]::GetFullPath($targetValue).TrimEnd('\', '/')
        }
    }

    if ($existingTarget -and $pathComparer.Equals($existingTarget, $normalizedProjectRoot)) {
        Write-Host "Laragon is already linked to this clone: $sitePath"
        Write-Host 'Start or reload Laragon, then open http://votetrust.test'
        exit 0
    }

    if (-not $BackupExisting) {
        throw "'$sitePath' already exists and does not point to this clone. Inspect it, then re-run with -BackupExisting to move it aside safely."
    }

    $backupName = 'votetrust.backup-{0}' -f (Get-Date -Format 'yyyyMMdd-HHmmss')
    $backupPath = Join-Path $laragonWwwPath $backupName

    if (Test-Path -LiteralPath $backupPath) {
        throw "The backup destination already exists: '$backupPath'. Wait one second and try again."
    }

    Move-Item -LiteralPath $sitePath -Destination $backupPath
    Write-Host "Existing Laragon folder moved to: $backupPath"
}

$junction = New-Item -ItemType Junction -Path $sitePath -Target $projectRoot
$junctionTarget = [System.IO.Path]::GetFullPath(@($junction.Target)[0]).TrimEnd('\', '/')

if (-not $pathComparer.Equals($junctionTarget, $normalizedProjectRoot)) {
    throw "The Laragon junction was created, but its target could not be verified."
}

Write-Host "Laragon link created: $sitePath -> $projectRoot"
Write-Host 'Start or reload Laragon, then open http://votetrust.test'
