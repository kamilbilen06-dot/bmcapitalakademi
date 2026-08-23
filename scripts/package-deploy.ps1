# BM Capital / yeni akademi — canlı paket
# Kullanım: powershell -File scripts\package-deploy.ps1
# Çıktı: deploy\akademi-live-YYYYMMDD.zip

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$outDir = Join-Path $root "deploy"
$stamp = Get-Date -Format "yyyyMMdd-HHmm"
$zipPath = Join-Path $outDir "akademi-live-$stamp.zip"
$stage = Join-Path $outDir "stage-$stamp"

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Force -Path $stage | Out-Null
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$excludeDirNames = @(
  ".git", ".tools", "deploy", "node_modules", "agent-transcripts", ".cursor"
)

Get-ChildItem -Path $root -Force | ForEach-Object {
  if ($excludeDirNames -contains $_.Name) { return }
  if ($_.Name -eq "deploy") { return }
  Copy-Item $_.FullName -Destination (Join-Path $stage $_.Name) -Recurse -Force
}

# Gizli local config'leri paketten çıkar (sunucuda elle oluşturulacak)
@(
  "api\paytr_config.local.php",
  "api\site_brand.local.php",
  "api\iyzico_config.local.php",
  "api\mail_config.local.php",
  "api\oauth_config.local.php"
) | ForEach-Object {
  $p = Join-Path $stage $_
  if (Test-Path $p) { Remove-Item $p -Force }
}

# uploads içeriğini boşalt, klasör kalsın
$uploadsCourses = Join-Path $stage "uploads\courses"
if (Test-Path $uploadsCourses) {
  Get-ChildItem $uploadsCourses -Force | Where-Object { $_.Name -ne ".gitkeep" } | Remove-Item -Recurse -Force
}

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $zipPath -Force
Remove-Item $stage -Recurse -Force

Write-Host "OK: $zipPath"
Write-Host "Sonraki:"
Write-Host "  1) Zip'i public_html'e aç"
Write-Host "  2) api/config.php DB bilgileri + INSTALL_LOCKED=true"
Write-Host "  3) api/site_brand.local.php (domain)"
Write-Host "  4) api/iyzico_config.local.php (iyzico keys + IYZICO_MERCHANT_ID)"
Write-Host "  5) api/mail_config.local.php"
Write-Host "  6) /api/launch_status.php ve /api/iyzico_status.php kontrol"
Write-Host "  7) Cron: api/cron.php?key=CRON_KEY (15 dk)"
