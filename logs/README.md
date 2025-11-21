# ScholarSeek Logs Directory

This directory contains application logs for the ScholarSeek scholarship management system.

## Log Files

### debug.log
- **Purpose:** Debug information and system diagnostics
- **Created by:** Various system components during development and troubleshooting
- **Content:** Debug messages, variable dumps, execution traces
- **Rotation:** Manual cleanup recommended when file exceeds 10MB

### registrations.log
- **Purpose:** Student registration audit trail
- **Created by:** `register_process.php`
- **Content:** Successful student registrations with timestamp, email, and student number
- **Format:** JSON entries with registration details
- **Rotation:** Automatically managed

### security.log
- **Purpose:** Security events and suspicious activity
- **Created by:** Security middleware and authentication system
- **Content:** Failed login attempts, access denials, security violations
- **Format:** JSON entries with IP address, timestamp, and event details
- **Rotation:** Automatically managed

### login_attempts.json
- **Purpose:** Failed login attempt tracking for rate limiting
- **Created by:** `RateLimiter` class in security_config.php
- **Content:** IP addresses and failed attempt counts
- **Format:** JSON object with IP addresses as keys
- **Auto-reset:** After 15 minutes of inactivity

## Security

- ✅ Direct web access is blocked via `.htaccess`
- ✅ Script execution is prevented
- ✅ Directory listing is disabled
- ✅ Logs contain sensitive information and should not be publicly accessible

## Maintenance

### Viewing Logs
Access logs through the admin panel or directly via SSH/FTP (not via web browser).

### Clearing Logs
To clear old logs, use the admin dashboard or manually delete files:
```bash
# Clear debug log
> $null | Set-Content logs/debug.log

# Clear security log
> $null | Set-Content logs/security.log

# Clear registrations log
> $null | Set-Content logs/registrations.log
```

### Archiving Logs
For production environments, implement log rotation:
1. Archive logs weekly or when they exceed 10MB
2. Keep archived logs for 90 days
3. Use timestamps in archive filenames: `debug.log.2025-11-21`

## Log Format Examples

### Registration Log Entry
```json
{
  "timestamp": "2025-11-21 11:38:00",
  "email": "student@biliran.edu.ph",
  "student_number": "2024-001",
  "status": "success"
}
```

### Security Log Entry
```json
{
  "timestamp": "2025-11-21 11:38:00",
  "event": "failed_login",
  "email": "user@biliran.edu.ph",
  "ip_address": "192.168.1.1",
  "reason": "invalid_password"
}
```

## Troubleshooting

If logs are not being created:
1. Verify the logs directory has write permissions (755 or 777)
2. Check that PHP has permission to write files
3. Ensure the logs directory exists and is not read-only
4. Review PHP error logs for permission errors

## Related Files

- `security_config.php` - Security logging configuration
- `auth_middleware.php` - Authentication logging
- `register_process.php` - Registration logging
- `login_process.php` - Login attempt logging
