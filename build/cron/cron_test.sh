#!/bin/bash

echo "----------------------------"
echo "trying again with correct line-endings."
echo "----------------------------"

php /var/www/mra/bin/console reddit:sync:single -vvv --url https://www.reddit.com/r/AskReddit/comments/smc16n/what_is_a_website_everyone_should_know_about/