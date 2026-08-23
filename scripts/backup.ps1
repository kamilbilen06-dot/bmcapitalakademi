# BM Capital — günlük yedek (MySQL + uploads)
# Kullanım: powershell -File scripts/backup.ps1
# Çıktı: backups/YYYYMMDD-HHmm/

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$outRoot = Join-Path $root "backups"
$stamp = Get-Date -Format "yyyyMMdd-HHmm"
$dest = Join-Path $outRoot $stamp
New-Item -ItemType Directory -Force -Path $dest | Out-Null

$config = Join-Path $root "api\config.php"
$dbName = "bmcapital"
$dbUser = "root"
$dbPass = ""
$dbHost = "localhost"
if (Test-Path $config) {
  $txt = Get-Content $config -Raw
  if ($txt -match "define\('DB_NAME',\s*'([^']*)'") { $dbName = $Matches[1] }
  if ($txt -match "define\('DB_USER',\s*'([^']*)'") { $dbUser = $Matches[1] }
  if ($txt -match "define\('DB_PASS',\s*'([^']*)'") { $dbPass = $Matches[1] }
  if ($txt -match "define\('DB_HOST',\s*'([^']*)'") { $dbHost = $Matches[1] }
}

$dump = Join-Path $dest "mysql.sql"
$mysqldump = Get-Command mysqldump -ErrorAction SilentlyContinue
if ($mysqldump) {
  $args = @("-h$dbHost", "-u$dbUser")
  if ($dbPass -ne "") { $args += "-p$dbPass" }
  $args += @("--single-transaction", "--routines", $dbName)
  & mysqldump @args | Out-File -FilePath $dump -Encoding utf8
  Write-Host "MySQL: $dump"
} else {
  "mysqldump PATH'te yok. cPanel > phpMyAdmin ile $dbName dışa aktarın." | Set-Content (Join-Path $dest "MYSQL-ELLE.txt")
  Write-Host "mysqldump yok; MYSQL-ELLE.txt yazıldı."
}

$uploads = Join-Path $root "uploads"
if (Test-Path $uploads) {
  Copy-Item $uploads -Destination (Join-Path $dest "uploads") -Recurse -Force
  Write-Host "uploads kopyalandı"
}

Write-Host "OK: $dest"
Write-Host "Bu klasörü VPS dışı (bulut / başka disk) kopyalayın. Gizli local.php dosyalarını zip'e koymayın."
