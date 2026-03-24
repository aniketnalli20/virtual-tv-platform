$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpExe = $null
if (Test-Path 'C:\xampp\php\php.exe') {
  $phpExe = 'C:\xampp\php\php.exe'
} else {
  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd -and $cmd.Source -and (Test-Path $cmd.Source)) {
    $phpExe = $cmd.Source
  }
}

if (-not $phpExe) {
  throw 'PHP executable not found. Install PHP 8+ or use XAMPP (C:\xampp\php\php.exe).'
}

function Test-PortOpen([int]$Port) {
  try {
    $client = New-Object System.Net.Sockets.TcpClient
    $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
    $ok = $async.AsyncWaitHandle.WaitOne(120)
    if (-not $ok) { $client.Close(); return $false }
    $client.EndConnect($async) | Out-Null
    $client.Close()
    return $true
  } catch {
    return $false
  }
}

$port = 8000
if (Test-PortOpen -Port $port) { $port = 8001 }
if (Test-PortOpen -Port $port) { throw "No free port (tried 8000, 8001)." }

$server = Start-Process -FilePath $phpExe -ArgumentList @('-S', "127.0.0.1:$port", 'router.php') -WorkingDirectory $root -PassThru -NoNewWindow

try {
  $base = "http://127.0.0.1:$port"

  $ready = $false
  for ($i = 0; $i -lt 60; $i++) {
    try {
      $r = Invoke-WebRequest -Uri "$base/api/health" -UseBasicParsing -TimeoutSec 2
      if ($r.StatusCode -eq 200) { $ready = $true; break }
    } catch {
    }
    Start-Sleep -Milliseconds 250
  }

  if (-not $ready) {
    throw "Server did not become ready at $base"
  }

  $me = Invoke-RestMethod -Uri "$base/api/me" -Method Get -TimeoutSec 5
  if (-not $me.ok) { throw 'GET /api/me did not return ok=true' }

  $pinRequired = [bool]($env:TVOS_PIN -and $env:TVOS_PIN.Trim().Length -gt 0)
  if ($pinRequired) {
    $pin = $env:TVOS_PIN
    $loginBody = @{ pin = $pin } | ConvertTo-Json -Compress
    $login = Invoke-RestMethod -Uri "$base/api/login" -Method Post -ContentType 'application/json' -Body $loginBody -TimeoutSec 5
    if (-not $login.ok) { throw 'POST /api/login did not return ok=true' }
  }

  $apps = Invoke-RestMethod -Uri "$base/api/apps" -Method Get -TimeoutSec 5
  if (-not $apps.ok) { throw 'GET /api/apps did not return ok=true' }

  $expectedIds = @('live','media','browser','mirroring','store','files','notifications','input','settings')
  $actualIds = @($apps.data.apps | ForEach-Object { $_.id }) | Sort-Object
  $expectedSorted = $expectedIds | Sort-Object

  if (($actualIds -join ',') -ne ($expectedSorted -join ',')) {
    throw ("Unexpected /api/apps ids. Expected: " + ($expectedSorted -join ',') + " Got: " + ($actualIds -join ','))
  }

  foreach ($app in $apps.data.apps) {
    if (-not $app.iconUrl) { throw "Missing iconUrl for app id=$($app.id)" }
    if (-not ($app.iconUrl -like 'https://unpkg.com/@tabler/icons@latest/icons/*/*.svg')) {
      throw "iconUrl is not a Tabler SVG for app id=$($app.id)"
    }
  }

  $js = Invoke-WebRequest -Uri "$base/assets/os.js" -UseBasicParsing -TimeoutSec 5
  if ($js.Content -match 'FEATURED_APPS') { throw 'Third-party FEATURED_APPS list still present in assets/os.js' }
  if ($js.Content -match 'youtube|gmail|drive|maps|wikipedia|archive') { throw 'Third-party app references still present in assets/os.js' }

  Write-Output "OK: $base"
  Write-Output 'OK: /api/apps returns only system apps and icons'
  Write-Output 'OK: assets/os.js has no third-party app tiles'
} finally {
  if ($server -and -not $server.HasExited) {
    Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
  }
}
