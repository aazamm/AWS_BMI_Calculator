#!/bin/bash
# Deployment script for BMI Calculator + WordPress on EC2 Amazon Linux 2023
# Run this script as root (or with sudo) on a fresh EC2 instance
#
# Prerequisites:
#   - EC2 instance with Amazon Linux 2023 AMI
#   - Security group allowing inbound HTTP (port 80) and SSH (port 22)
#   - SSH access to the instance

set -e

echo "=== Updating system packages ==="
dnf update -y

echo "=== Installing Apache, PHP, SQLite, and MariaDB ==="
dnf install -y httpd php php-mysqlnd php-sqlite3 php-mbstring php-xml php-gd \
    mariadb105-server sqlite wget unzip

echo "=== Starting and enabling services ==="
systemctl start httpd
systemctl enable httpd
systemctl start mariadb
systemctl enable mariadb

echo "=== Securing MariaDB ==="
# Set root password and basic security
MYSQL_ROOT_PASS="ChangeMe_$(openssl rand -hex 8)"
mysql -u root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
EOF
echo "MariaDB root password: ${MYSQL_ROOT_PASS}" > /root/db_credentials.txt
chmod 600 /root/db_credentials.txt

echo "=== Creating WordPress database ==="
WP_DB_PASS="wp_$(openssl rand -hex 8)"
mysql -u root -p"${MYSQL_ROOT_PASS}" <<EOF
CREATE DATABASE wordpress;
CREATE USER 'wpuser'@'localhost' IDENTIFIED BY '${WP_DB_PASS}';
GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
EOF
echo "WordPress DB user: wpuser / ${WP_DB_PASS}" >> /root/db_credentials.txt

echo "=== Installing WordPress ==="
cd /var/www/html
wget -q https://wordpress.org/latest.tar.gz
tar xzf latest.tar.gz
cp -r wordpress/* .
rm -rf wordpress latest.tar.gz

# Configure WordPress
cp wp-config-sample.php wp-config.php
sed -i "s/database_name_here/wordpress/" wp-config.php
sed -i "s/username_here/wpuser/" wp-config.php
sed -i "s/password_here/${WP_DB_PASS}/" wp-config.php

# Generate unique auth keys
SALT=$(curl -s https://api.wordpress.org/secret-key/1.1/salt/)
# Remove existing placeholder keys and insert real ones
sed -i '/AUTH_KEY/d; /SECURE_AUTH_KEY/d; /LOGGED_IN_KEY/d; /NONCE_KEY/d' wp-config.php
sed -i '/AUTH_SALT/d; /SECURE_AUTH_SALT/d; /LOGGED_IN_SALT/d; /NONCE_SALT/d' wp-config.php
sed -i "/define( 'DB_COLLATE'/a\\${SALT}" wp-config.php

echo "=== Deploying BMI Calculator ==="
mkdir -p /var/www/html/bmi
cp /tmp/bmi-calculator/index.php /var/www/html/bmi/
cp /tmp/bmi-calculator/db.php /var/www/html/bmi/
cp /tmp/bmi-calculator/style.css /var/www/html/bmi/

echo "=== Setting permissions ==="
chown -R apache:apache /var/www/html
chmod -R 755 /var/www/html
# SQLite DB needs to be writable
chmod 775 /var/www/html/bmi

echo "=== Configuring Apache ==="
cat > /etc/httpd/conf.d/bmi.conf <<'APACHECONF'
<Directory /var/www/html/bmi>
    AllowOverride All
    Require all granted
</Directory>
APACHECONF

systemctl restart httpd

echo ""
echo "=== Deployment Complete ==="
echo ""
echo "WordPress:      http://YOUR_EC2_PUBLIC_IP/"
echo "BMI Calculator: http://YOUR_EC2_PUBLIC_IP/bmi/"
echo ""
echo "Database credentials saved to /root/db_credentials.txt"
echo "Complete the WordPress setup by visiting the WordPress URL in your browser."
