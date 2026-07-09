# Run this file as Administrator (right-click -> "Run with PowerShell" after
# right-clicking PowerShell itself and choosing "Run as administrator", or
# right-click this file -> Run as administrator if that option appears).
#
# Adds hawalaty.test to the Windows hosts file and flushes the DNS cache,
# so http://hawalaty.test works in the browser without needing Laragon's
# own auto-hosts feature to have kicked in.

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entry = "127.0.0.1`thawalaty.test"

$content = Get-Content -Path $hostsPath -Raw
if ($content -notmatch "hawalaty\.test") {
    Add-Content -Path $hostsPath -Value "`n$entry" -Encoding ASCII
    Write-Host "Added: $entry" -ForegroundColor Green
} else {
    Write-Host "hawalaty.test is already in the hosts file." -ForegroundColor Yellow
}

ipconfig /flushdns | Out-Null
Write-Host "DNS cache flushed. Try http://hawalaty.test now." -ForegroundColor Green
Write-Host ""
Write-Host "Press Enter to close..."
Read-Host
