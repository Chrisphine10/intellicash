<#
.SYNOPSIS
  Copies the newest verified IntelliCash backup off the server, onto this PC.

.DESCRIPTION
  The server keeps its backups on the same disk as the live database, so one
  failed disk (or one compromised server) loses both. This pulls the newest
  verified backup (database + uploads) over the existing SSH key, checks every
  file against the server's SHA256SUMS, and keeps the last -Keep copies.

  The folder is encrypted with Windows EFS (cipher /e): these files hold
  members' names, phone numbers and savings. Keep it that way.

  Read-only on the server: it only reads /root/backups/INTELLICASH_LATEST.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File ops\pull-backup.ps1
.EXAMPLE
  # Nightly at 06:00, once you have decided to (see ops/README.md):
  schtasks /Create /SC DAILY /ST 06:00 /TN "IntelliCash backup pull" /TR "powershell -NoProfile -ExecutionPolicy Bypass -File \"$PWD\ops\pull-backup.ps1\""
#>
param(
  [string]$HostAlias = "intellicash",
  [string]$Destination = (Join-Path $env:USERPROFILE "IntelliCashBackups"),
  [int]$Keep = 30
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $Destination)) {
  New-Item -ItemType Directory -Path $Destination | Out-Null
  # Encrypt the folder so every file written into it is encrypted too.
  cipher /e /s:"$Destination" | Out-Null
}

$remote = (ssh -o BatchMode=yes $HostAlias "readlink -f /root/backups/INTELLICASH_LATEST").Trim()
if (-not $remote -or $remote -notmatch "^/root/backups/intellicash-") {
  throw "The server has no verified latest backup ($remote)."
}
$name = Split-Path $remote -Leaf
$target = Join-Path $Destination $name
if (Test-Path (Join-Path $target "SHA256SUMS")) {
  Write-Output "Already have $name."
} else {
  $partial = "$target.partial"
  if (Test-Path $partial) { Remove-Item -Recurse -Force -Confirm:$false $partial }
  New-Item -ItemType Directory -Path $partial | Out-Null
  scp -q -o BatchMode=yes "${HostAlias}:$remote/*" "$partial\"
  if ($LASTEXITCODE -ne 0) { throw "scp failed ($LASTEXITCODE)." }

  # Every file must match the checksum the server wrote when it verified it.
  foreach ($line in Get-Content (Join-Path $partial "SHA256SUMS")) {
    $expected, $file = $line -split "\s+\*?", 2
    $actual = (Get-FileHash -Algorithm SHA256 (Join-Path $partial $file)).Hash.ToLower()
    if ($actual -ne $expected.ToLower()) { throw "Checksum mismatch on $file in $name; not kept." }
  }
  Rename-Item $partial $target
  Write-Output "Pulled and verified $name."
}

# Keep the newest $Keep copies.
Get-ChildItem $Destination -Directory |
  Where-Object { $_.Name -like "intellicash-*" -and $_.Name -notlike "*.partial" } |
  Sort-Object Name -Descending |
  Select-Object -Skip $Keep |
  ForEach-Object { Remove-Item -Recurse -Force -Confirm:$false $_.FullName }
