# Rotating Backups

The rotating backup tasks should be triggered hourly by a cron job.

This is how it can be run via sake: `sake /HourlyTask flush=1` (you only need the flush first time you run it)

Example output of first run:

	HourlyTask
	RotatingBackupTask
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups/hourly
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups/daily
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups/weekly
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups/monthly
	Created directory: /private/var/git-repos/MYSITE/rotatingbackups/yearly

	Rotating db backup:
	Created db dump /private/var/git-repos/MYSITE/rotatingbackups/hourly/db/2013-03-27_15-32CET_db_MYDB.tar.gz
	Keeping 1 out of max 48 hourly backups totalling 8,0K
	Copied hourly/db/2013-03-27_15-32CET_db_MYDB.tar.gz to daily/db/2013-03-27_15-32CET_db_MYDB.tar.gz
	Keeping 1 out of max 7 daily backups totalling 8,0K
	Copied hourly/db/2013-03-27_15-32CET_db_MYDB.tar.gz to weekly/db/2013-03-27_15-32CET_db_MYDB.tar.gz
	Keeping 1 out of max 4 weekly backups totalling 8,0K
	Copied hourly/db/2013-03-27_15-32CET_db_MYDB.tar.gz to monthly/db/2013-03-27_15-32CET_db_MYDB.tar.gz
	Keeping 1 out of max 12 monthly backups totalling 8,0K
	Copied hourly/db/2013-03-27_15-32CET_db_MYDB.tar.gz to yearly/db/2013-03-27_15-32CET_db_MYDB.tar.gz
	Keeping 1 out of max -1 yearly backups totalling 8,0K
	Total db backup size: hourly: 8,0K, daily: 8,0K, weekly: 8,0K, monthly: 8,0K, yearly: 8,0K

	Rotating assets backup:
	Created assets dump /private/var/git-repos/MYSITE/rotatingbackups/hourly/assets/2013-03-27_15-32CET_assets.tar.gz
	Keeping 1 out of max 10 hourly backups totalling 220K
	Copied hourly/assets/2013-03-27_15-32CET_assets.tar.gz to daily/assets/2013-03-27_15-32CET_assets.tar.gz
	Keeping 1 out of max 7 daily backups totalling 220K
	Copied hourly/assets/2013-03-27_15-32CET_assets.tar.gz to weekly/assets/2013-03-27_15-32CET_assets.tar.gz
	Keeping 1 out of max 4 weekly backups totalling 220K
	Copied hourly/assets/2013-03-27_15-32CET_assets.tar.gz to monthly/assets/2013-03-27_15-32CET_assets.tar.gz
	Keeping 1 out of max 3 monthly backups totalling 220K
	Copied hourly/assets/2013-03-27_15-32CET_assets.tar.gz to yearly/assets/2013-03-27_15-32CET_assets.tar.gz
	Keeping 1 out of max -1 yearly backups totalling 220K
	Total assets backup size: hourly: 220K, daily: 220K, weekly: 220K, monthly: 220K, yearly: 220K
