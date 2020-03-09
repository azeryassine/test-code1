#!/bin/bash

echo "Setting up database server ..."


if [ $DATABASE_SERVER ]
then
    # remove the pre-installed MariaDB server
    sudo apt-get purge -y mysql-*
    sudo rm -rf /var/lib/mysql
    sudo rm -rf /etc/mysql

    sudo apt-get install software-properties-common
    sudo apt-key adv --fetch-keys 'http://mariadb.org/mariadb_release_signing_key.asc'

    # Oracle / MySQL Key
    sudo apt-key adv --keyserver keys.gnupg.net --recv-keys 5072E1F5
fi


if [ $DATABASE_SERVER = "mariadb-10.1" ]; then
    sudo add-apt-repository 'deb [arch=amd64,arm64,i386,ppc64el] http://mirror.rackspace.com/mariadb/repo/10.1/ubuntu xenial main'
    sudo apt-get update
    sudo apt-get install -y mariadb-server-10.1
    sudo systemctl start mysql
    mysql -e "SET GLOBAL innodb_large_prefix=1;"
fi

if [ $DATABASE_SERVER = "mariadb-10.2" ]; then
    sudo add-apt-repository 'deb [arch=amd64,arm64,i386,ppc64el] http://mirror.rackspace.com/mariadb/repo/10.2/ubuntu xenial main'
    sudo apt-get update
    sudo apt-get install -y mariadb-server-10.2
    sudo systemctl start mysql
fi

if [ $DATABASE_SERVER = "mariadb-10.3" ]; then
    sudo add-apt-repository 'deb [arch=amd64,arm64,i386,ppc64el] http://mirror.rackspace.com/mariadb/repo/10.3/ubuntu xenial main'
    sudo apt-get update
    sudo apt-get install -y mariadb-server-10.3
    sudo systemctl start mysql
fi

if [ $DATABASE_SERVER = "mariadb-10.4" ]; then
    sudo add-apt-repository 'deb [arch=amd64,arm64,i386,ppc64el] http://mirror.rackspace.com/mariadb/repo/10.4/ubuntu xenial main'
    sudo apt-get update
    sudo apt-get install -y mariadb-server-10.4
    sudo systemctl start mysql
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY ''; flush privileges;"
fi

if [ $DATABASE_SERVER = "mysql-5.6" ]; then
    sudo add-apt-repository 'deb http://repo.mysql.com/apt/ubuntu/ xenial mysql-5.6'
    sudo apt-get update
    sudo apt-get install -y mysql-server mysql-client
    sudo systemctl start mysql
fi

if [ $DATABASE_SERVER = "mysql-5.7" ]; then
    sudo add-apt-repository 'deb http://repo.mysql.com/apt/ubuntu/ xenial mysql-5.7'
    sudo apt-get update
    sudo apt-get install -y mysql-server mysql-client
    sudo systemctl start mysql
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY ''; flush privileges;"
fi

if [ $DATABASE_SERVER = "mysql-8.0" ]; then
    sudo add-apt-repository 'deb http://repo.mysql.com/apt/ubuntu/ xenial mysql-5.8'
    sudo apt-get update
    sudo apt-get install -y mysql-server mysql-client
    sudo systemctl start mysql
fi