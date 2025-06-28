# CRONJOBS

Add this to your crontab

# Remove worker.log every minute
* * * * * rm /root/apps/bts-guru-daemon/worker.log

# Run AFL standings every 12 hours (at 00:00 and 12:00)
0 */12 * * * php /root/apps/bts-guru-daemon/artisan api:afl:standings

# Run AFL schedules every 12 hours (at 00:00 and 12:00)
0 */12 * * * php /root/apps/bts-guru-daemon/artisan api:afl:schedules
