<?php
// Copy to config.local.php outside the public webroot; do not commit the copy.
putenv('TVRTKE_DB_HOST=localhost');
putenv('TVRTKE_DB_PORT=3306');
putenv('TVRTKE_DB_NAME=cpaneluser_tvrtke');
putenv('TVRTKE_DB_USER=cpaneluser_tvrtke');
putenv('TVRTKE_DB_PASSWORD=CHANGE_ME');
putenv('SUDREG_CLIENT_ID=CHANGE_ME');
putenv('SUDREG_CLIENT_SECRET=CHANGE_ME');
putenv('TVRTKE_ADMIN_TOKEN=CHANGE_ME_TO_LONG_RANDOM_SECRET');
putenv('TVRTKE_IMPORT_BATCH=100');
putenv('TVRTKE_IMPORT_ROW_LIMIT=10000'); // Raise only after measuring MySQL storage and official counts
putenv('TVRTKE_SYNC_PAGES_PER_RUN=3'); // Max 8; short cron-friendly series
putenv('TVRTKE_SYNC_BUDGET_SECONDS=15'); // Soft limit, not a process timeout
putenv('TVRTKE_PUBLIC_ROOT=/home/ACCOUNT/public_html'); // Importer rejects CSV under this directory
putenv('TVRTKE_SYNC_INCLUDE_INACTIVE=1'); // Include active and inactive Sudreg records
putenv('TVRTKE_SUDREG_ARCHIVE_RAW=0'); // 1 saves every raw API row; high disk usage
putenv('TVRTKE_FEEDS_CONFIG=/home/ACCOUNT/tvrtke-backend/feeds.local.json'); // CLI-only approved feeds
putenv('TVRTKE_FEEDS_CACHE_DIR=/home/ACCOUNT/tvrtke-backend/private-feeds'); // 0700; NOT web-accessible
