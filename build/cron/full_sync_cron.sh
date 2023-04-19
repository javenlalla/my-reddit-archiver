#!/bin/bash

echo "----------------------------"
echo "Running Saved profile sync."
echo "----------------------------"

/usr/local/bin/php /var/www/mra/bin/console reddit:sync
