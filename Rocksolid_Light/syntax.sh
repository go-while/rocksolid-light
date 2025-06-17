find . -iname "*.php" -exec php -l {} + |grep -v "No syntax"
