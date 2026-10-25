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
putenv('TVRTKE_IMPORT_ROW_LIMIT=10000');
