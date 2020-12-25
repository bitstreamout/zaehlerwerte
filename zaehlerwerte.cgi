#!/bin/sh
export DOCUMENT_ROOT=/srv/www/htdocs/smart
export SCRIPT_NAME=/cgi-bin/zaehlerwerte.php
export SCRIPT_FILENAME=/srv/www/cgi-bin/zaehlerwerte.php
export REDIRECT_STATUS=1
exec /srv/www/cgi-bin/php
