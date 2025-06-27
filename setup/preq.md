
# Complete Guide: Setting up Supervisord with Laravel Reverb and SQLite

This guide covers the complete setup process for running Laravel Reverb WebSocket server using Supervisord with SQLite database support.

## Step 1: Install Supervisord

```bash
# Update package list
sudo apt update

# Install supervisor
sudo apt install supervisor

# Start and enable the service
sudo systemctl start supervisor
sudo systemctl enable supervisor

# Check status
sudo systemctl status supervisor
```

## Step 2: Install PHP SQLite Extensions

For Laravel to work with SQLite, you need the appropriate PHP extensions:

```bash
# For PHP 8.4 (check your version with: php --version)
sudo apt install php8.4-sqlite3

# For generic PHP installation
sudo apt install php-sqlite3

# Verify installation
php -m | grep sqlite
```

Expected output:
```
pdo_sqlite
sqlite3
```

## Step 3: Configure Laravel for SQLite

Update your `.env` file:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/app/database/database.sqlite
# Comment out other DB settings
#DB_HOST=pgsql
#DB_PORT=5432
#DB_USERNAME=sail
#DB_PASSWORD=password
```

Create the SQLite database file:
```bash
touch /path/to/your/app/database/database.sqlite

# Test database connection
php artisan migrate
```

## Step 4: Create Supervisord Configuration

Create configuration file: `/etc/supervisor/conf.d/your-app-worker.conf`

```ini
[program:your-app-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /path/to/your/app/artisan reverb:start
directory=/path/to/your/app
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/app/worker.log
stderr_logfile=/path/to/your/app/worker_error.log
stopwaitsecs=3600
```

**Important Notes:**
- **Use `numprocs=1`** for WebSocket servers (unlike queue workers)
- **Use absolute path** for PHP binary (`/usr/bin/php`)
- **Add `directory`** parameter for working directory
- **Add `stderr_logfile`** for better error debugging

## Step 5: Load and Start the Configuration

```bash
# Read new configuration
sudo supervisorctl reread

# Update supervisor with new configs
sudo supervisorctl update

# Start the process
sudo supervisorctl start your-app-worker

# Check status
sudo supervisorctl status
```

## Step 6: Troubleshooting Common Issues

### Issue 1: "could not find driver" (SQLite)
**Solution:** Install PHP SQLite extension (Step 2)

### Issue 2: "Address already in use (EADDRINUSE)"
**Find what's using the port:**
```bash
sudo netstat -tulpn | grep :8080
sudo lsof -i :8080
sudo fuser -v 8080/tcp
```

**Kill the process:**
```bash
# Find PID and kill it
sudo kill -9 <PID>

# Or kill by process name
sudo pkill -f "artisan reverb"
```

### Issue 3: "ERROR (spawn error)"
**Check detailed logs:**
```bash
sudo supervisorctl tail your-app-worker stderr
sudo tail -f /var/log/supervisor/supervisord.log
```

**Test command manually:**
```bash
cd /path/to/your/app
php artisan reverb:start
```

### Issue 4: Multiple Process Errors
**For WebSocket servers like Reverb:**
- Use `numprocs=1` (only one process)
- Multiple processes can't bind to the same port

**For Queue Workers:**
- Use `numprocs=8` (or desired number)
- Multiple processes can work simultaneously

## Step 7: Useful Supervisord Commands

```bash
# View all processes
sudo supervisorctl status

# Start a program
sudo supervisorctl start your-app-worker

# Stop a program
sudo supervisorctl stop your-app-worker

# Restart a program
sudo supervisorctl restart your-app-worker

# View logs
sudo supervisorctl tail your-app-worker
sudo supervisorctl tail your-app-worker stderr

# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update
```

## Step 8: Verification

1. **Check supervisor status:**
```bash
sudo supervisorctl status your-app-worker
```

2. **Test WebSocket connection:**
```bash
# Check if port is listening
sudo netstat -tulpn | grep :8080
```

3. **Monitor logs:**
```bash
tail -f /path/to/your/app/worker.log
```

## Configuration Template

Here's a complete working example:

```ini
[program:bts-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /root/apps/bts-guru-daemon/artisan reverb:start
directory=/root/apps/bts-guru-daemon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/root/apps/bts-guru-daemon/worker.log
stderr_logfile=/root/apps/bts-guru-daemon/worker_error.log
stopwaitsecs=3600
```

## Security Note

Running as `root` user is not recommended for production. Consider creating a dedicated user:

```bash
# Create dedicated user
sudo useradd -r -s /bin/false laravel

# Change ownership
sudo chown -R laravel:laravel /path/to/your/app

# Update config
user=laravel
```

This setup ensures your Laravel Reverb WebSocket server runs reliably under Supervisord with proper SQLite database support.
