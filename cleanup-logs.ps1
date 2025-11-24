# Log Cleanup Script
# Use this to reduce the size of your laravel.log file

Write-Host "=== Laravel Log Cleanup Tool ===" -ForegroundColor Cyan
Write-Host ""

$logPath = "storage\logs\laravel.log"
$backupPath = "storage\logs\laravel.log.old"

# Check if log file exists
if (-not (Test-Path $logPath)) {
    Write-Host "✅ No log file found - nothing to clean!" -ForegroundColor Green
    exit 0
}

# Get current file size
$currentSize = (Get-Item $logPath).Length
$sizeMB = [math]::Round($currentSize / 1MB, 2)

Write-Host "Current log file size: $sizeMB MB" -ForegroundColor Yellow
Write-Host ""

# Option 1: Archive old logs and start fresh
Write-Host "Option 1: Archive and Start Fresh" -ForegroundColor Green
Write-Host "  - Moves current log to laravel.log.old"
Write-Host "  - Creates new empty log file"
Write-Host ""

# Option 2: Keep only last N lines
Write-Host "Option 2: Keep Last 1000 Lines" -ForegroundColor Green
Write-Host "  - Keeps only the most recent 1000 log entries"
Write-Host "  - Discards older logs"
Write-Host ""

# Option 3: Delete completely
Write-Host "Option 3: Delete Completely" -ForegroundColor Green
Write-Host "  - Removes the log file entirely"
Write-Host "  - Laravel will create a new one automatically"
Write-Host ""

$choice = Read-Host "Choose option (1/2/3) or 'q' to quit"

switch ($choice) {
    "1" {
        Write-Host "`nArchiving old logs..." -ForegroundColor Cyan
        if (Test-Path $backupPath) {
            Remove-Item $backupPath -Force
        }
        Move-Item $logPath $backupPath
        New-Item $logPath -ItemType File -Force | Out-Null
        Write-Host "✅ Done! Old logs archived to laravel.log.old" -ForegroundColor Green
    }
    "2" {
        Write-Host "`nKeeping last 1000 lines..." -ForegroundColor Cyan
        $lastLines = Get-Content $logPath -Tail 1000
        $lastLines | Set-Content $logPath
        $newSize = (Get-Item $logPath).Length
        $newSizeMB = [math]::Round($newSize / 1MB, 2)
        Write-Host "✅ Done! New size: $newSizeMB MB" -ForegroundColor Green
    }
    "3" {
        Write-Host "`nDeleting log file..." -ForegroundColor Cyan
        Remove-Item $logPath -Force
        Write-Host "✅ Done! Log file deleted" -ForegroundColor Green
    }
    "q" {
        Write-Host "`nCancelled" -ForegroundColor Yellow
        exit 0
    }
    default {
        Write-Host "`n❌ Invalid choice" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "📝 Tip: The deduplication is now set to:" -ForegroundColor Cyan
Write-Host "  - Level: info (deduplicates more logs)" -ForegroundColor Gray
Write-Host "  - TTL: 1 hour (same log won't appear for 1 hour)" -ForegroundColor Gray
Write-Host ""
