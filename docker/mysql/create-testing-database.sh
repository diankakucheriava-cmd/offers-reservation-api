#!/usr/bin/env bash
set -e

mysql -u root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`offers_reservation_test\`;
    GRANT ALL PRIVILEGES ON \`offers_reservation_test\`.* TO '${MYSQL_USER}'@'%';
EOSQL
