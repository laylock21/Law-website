# PowerShell script to fix lawyer/availability.php column names
# Run this script from the project root directory

$file = "lawyer/availability.php"
$backup = "lawyer/availability.php.backup"

Write-Host "Creating backup..." -ForegroundColor Yellow
Copy-Item $file $backup

Write-Host "Reading file..." -ForegroundColor Cyan
$content = Get-Content $file -Raw

Write-Host "Applying fixes..." -ForegroundColor Green

# Fix 1: Change user_id to lawyer_id in lawyer_availability context
$content = $content -replace 'lawyer_availability \(user_id,', 'lawyer_availability (lawyer_id,'
$content = $content -replace 'WHERE user_id = \?', 'WHERE lawyer_id = ?'
$content = $content -replace 'AND user_id = \?', 'AND lawyer_id = ?'

# Fix 2: Change id to la_id for lawyer_availability primary key
$content = $content -replace 'SELECT id FROM lawyer_availability', 'SELECT la_id FROM lawyer_availability'
$content = $content -replace 'WHERE id = \? AND user_id', 'WHERE la_id = ? AND lawyer_id'
$content = $content -replace 'WHERE id IN \(\$placeholders\) AND user_id', 'WHERE la_id IN ($placeholders) AND lawyer_id'

# Fix 3: Change is_active to la_is_active
$content = $content -replace 'SET is_active = 0', 'SET la_is_active = 0'
$content = $content -replace 'SET is_active = 1', 'SET la_is_active = 1'
$content = $content -replace 'AND is_active = 1', 'AND la_is_active = 1'
$content = $content -replace 'WHERE is_active = 1', 'WHERE la_is_active = 1'

# Fix 4: Change weekdays to weekday (ENUM column)
$content = $content -replace 'schedule_type, weekdays,', 'schedule_type, weekday,'
$content = $content -replace 'specific_date, weekdays,', 'specific_date, weekday,'

# Fix 5: Update users table references
$content = $content -replace 'FROM users WHERE id = \?', 'FROM users WHERE user_id = ?'
$content = $content -replace 'UPDATE users SET', 'UPDATE users SET'
$content = $content -replace 'WHERE id = \? AND role', 'WHERE user_id = ? AND role'

Write-Host "Writing fixed content..." -ForegroundColor Green
Set-Content $file $content -NoNewline

Write-Host "Done! Backup saved as: $backup" -ForegroundColor Yellow
Write-Host "Please test the changes. If there are issues, restore from backup:" -ForegroundColor Cyan
Write-Host "  Copy-Item $backup $file -Force" -ForegroundColor White
