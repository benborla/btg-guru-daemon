Copy all files inside this directory to
/etc/supervisor/conf.d/


Once done, run the following commands:

# Read new configuration
sudo supervisorctl reread

# Update supervisor with new configs
sudo supervisorctl update

sudo supervisorctl start "bts-worker:*"
sudo supervisorctl start "bts-queue-worker:*"

# Check status
sudo supervisorctl status


If encountered any error, you may refer to worker.log
