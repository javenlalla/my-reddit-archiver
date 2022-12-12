#!/bin/bash

echo "----------------------------"
echo "Running Saved profile sync."
echo "----------------------------"

php /var/www/mra/bin/console reddit:sync:saved:full --limit 100