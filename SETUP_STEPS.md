# BMI Calculator + WordPress on AWS EC2 - Setup Steps

## 1. Project Created

Files created in `bmi-calculator/`:
- `index.php` - BMI calculator form + results table
- `db.php` - SQLite database layer (creates table, saves/reads records)
- `style.css` - Styling for the calculator page
- `deploy.sh` - EC2 deployment script

### Database Schema (SQLite - `bmi_data.db`)
- `id` INTEGER PRIMARY KEY
- `name` TEXT
- `surname` TEXT
- `weight_kg` REAL
- `height_cm` REAL
- `bmi` REAL
- `created_at` DATETIME

---

## 2. Local Testing

```bash
brew install php
php -S localhost:8000 -t /Users/aaron/aazamm/bmi-calculator
# Access at http://localhost:8000
```

---

## 3. AWS CLI Installation & Configuration

```bash
brew install awscli
aws configure
# AWS Access Key ID: <from AWS Console>
# AWS Secret Access Key: <from AWS Console>
# Default region: eu-central-1
# Default output format: json
```

Verified with:
```bash
aws sts get-caller-identity
```

---

## 4. EC2 Key Pair Created

```bash
aws ec2 create-key-pair --key-name bmi-calculator-key --query 'KeyMaterial' --output text > ~/.ssh/bmi-calculator-key.pem
chmod 400 ~/.ssh/bmi-calculator-key.pem
```

---

## 5. Security Group Created

```bash
# Get default VPC
aws ec2 describe-vpcs --filters "Name=isDefault,Values=true" --query 'Vpcs[0].VpcId' --output text
# Result: vpc-04c3e303f1975de6c

# Create security group
aws ec2 create-security-group --group-name bmi-calculator-sg --description "Security group for BMI Calculator and WordPress" --vpc-id vpc-04c3e303f1975de6c
# Result: sg-0415fab6c3b564196

# Allow SSH (port 22) and HTTP (port 80)
aws ec2 authorize-security-group-ingress --group-id sg-0415fab6c3b564196 --protocol tcp --port 22 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id sg-0415fab6c3b564196 --protocol tcp --port 80 --cidr 0.0.0.0/0
```

---

## 6. EC2 Instance Launched

```bash
# Find latest Amazon Linux 2023 AMI (x86_64)
aws ec2 describe-images --owners amazon --filters "Name=name,Values=al2023-ami-2023*-x86_64" "Name=state,Values=available" --query 'sort_by(Images, &CreationDate)[-1].ImageId' --output text
# Result: ami-09e939ec71a36e537

# Launch instance
aws ec2 run-instances \
  --image-id ami-09e939ec71a36e537 \
  --instance-type t3.micro \
  --key-name bmi-calculator-key \
  --security-group-ids sg-0415fab6c3b564196 \
  --count 1 \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=bmi-calculator}]'
# Result: i-02c7b760e6ac58028

# Wait for instance
aws ec2 wait instance-status-ok --instance-ids i-02c7b760e6ac58028
```

---

## 7. Deployment to EC2

```bash
# Copy files
ssh -i ~/.ssh/bmi-calculator-key.pem -o StrictHostKeyChecking=no ec2-user@54.93.223.221 "mkdir -p /tmp/bmi-calculator"
scp -i ~/.ssh/bmi-calculator-key.pem /Users/aaron/aazamm/bmi-calculator/* ec2-user@54.93.223.221:/tmp/bmi-calculator/

# Run deploy script (installs Apache, PHP, MariaDB, WordPress)
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo bash /tmp/bmi-calculator/deploy.sh"

# Deploy BMI calculator manually (script had a sed issue)
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo mkdir -p /var/www/html/bmi && sudo cp /tmp/bmi-calculator/index.php /tmp/bmi-calculator/db.php /tmp/bmi-calculator/style.css /var/www/html/bmi/ && sudo chown -R apache:apache /var/www/html/bmi && sudo chmod 775 /var/www/html/bmi"

# Start PHP-FPM
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo systemctl start php-fpm && sudo systemctl enable php-fpm"

# Restart Apache
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo systemctl restart httpd"
```

---

## 8. Final Resources

| Resource | Value |
|----------|-------|
| Instance ID | `i-02c7b760e6ac58028` |
| Instance Type | t3.micro |
| Region | eu-central-1 (Frankfurt) |
| Public IP | 54.93.223.221 |
| AMI | ami-09e939ec71a36e537 (Amazon Linux 2023) |
| Key Pair | `~/.ssh/bmi-calculator-key.pem` |
| Security Group | sg-0415fab6c3b564196 |
| VPC | vpc-04c3e303f1975de6c |

### Live URLs
- **BMI Calculator**: https://aaronzammit.com/bmi/
- **Admin Panel**: https://aaronzammit.com/bmi/admin.php
- **WordPress**: https://aaronzammit.com/

### SSH Access
```bash
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221
```

### Credentials
Saved on instance at `/root/db_credentials.txt`:
- MariaDB root password
- WordPress DB user (wpuser) and password

---

## Software Installed on EC2
- Apache (httpd)
- PHP 8.4 with FPM, SQLite3, MySQLnd, mbstring, GD, XML
- MariaDB 10.5 (for WordPress)
- SQLite 3.40 (for BMI calculator)
- WordPress (latest)

---

## 9. Push to GitHub

```bash
# Initialize git repo
cd /Users/aaron/aazamm/bmi-calculator
git init
git add -A
git commit -m "Initial commit: BMI Calculator with WordPress on AWS EC2"

# Create GitHub repo and push (using GitHub CLI)
gh auth status  # Verify logged in as aazamm
gh repo create AWS_BMI_Calculator --public --source=. --remote=origin --push
```

**GitHub Repository**: https://github.com/aazamm/AWS_BMI_Calculator

---

## 10. HTTPS Setup via Cloudflare

Domain `aaronzammit.com` is managed on Cloudflare.

**Cloudflare DNS records (proxy enabled - orange cloud):**
- A record: `@` → `54.93.223.221`
- A record: `www` → `54.93.223.221`

**Cloudflare SSL/TLS mode:** Flexible

**WordPress HTTPS detection (added to wp-config.php on EC2):**
```php
/* Cloudflare HTTPS detection */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

**Apache Cloudflare config (created /etc/httpd/conf.d/cloudflare.conf):**
```bash
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo tee /etc/httpd/conf.d/cloudflare.conf > /dev/null <<'EOF'
<IfModule mod_remoteip.c>
    RemoteIPHeader X-Forwarded-For
    RemoteIPTrustedProxy 173.245.48.0/20
    RemoteIPTrustedProxy 103.21.244.0/22
    RemoteIPTrustedProxy 103.22.200.0/22
    RemoteIPTrustedProxy 103.31.4.0/22
    RemoteIPTrustedProxy 141.101.64.0/18
    RemoteIPTrustedProxy 108.162.192.0/18
    RemoteIPTrustedProxy 190.93.240.0/20
    RemoteIPTrustedProxy 188.114.96.0/20
    RemoteIPTrustedProxy 197.234.240.0/22
    RemoteIPTrustedProxy 198.41.128.0/17
    RemoteIPTrustedProxy 162.158.0.0/15
    RemoteIPTrustedProxy 104.16.0.0/13
    RemoteIPTrustedProxy 104.24.0.0/14
    RemoteIPTrustedProxy 172.64.0.0/13
    RemoteIPTrustedProxy 131.0.72.0/22
</IfModule>
EOF"
```

**Added HTTPS port to security group:**
```bash
aws ec2 authorize-security-group-ingress --group-id sg-0415fab6c3b564196 --protocol tcp --port 443 --cidr 0.0.0.0/0
```

**Restarted Apache:**
```bash
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo systemctl restart httpd"
```

---

## 11. Added Visitor Counter

Added a numeric visitor counter stored in SQLite (`visitor_counter` table). Increments on every page load and displays at the bottom of the BMI calculator page.

---

## 12. Added Hide Feature & Admin Panel

**Hide feature on public page:**
- Each BMI record has a "Hide" button
- Clicking Hide sets `hidden = 1` in the database
- Hidden records no longer appear on the public page

**Admin panel (`admin.php`):**
- Protected with HTTP Basic Authentication
- Shows all records including hidden ones (greyed out)
- Can hide/unhide any record
- Default credentials: `admin` / `bmi2026!`

**Redeployed updated files:**
```bash
scp -i ~/.ssh/bmi-calculator-key.pem index.php db.php style.css admin.php ec2-user@54.93.223.221:/tmp/bmi-calculator/
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@54.93.223.221 "sudo cp /tmp/bmi-calculator/index.php /tmp/bmi-calculator/db.php /tmp/bmi-calculator/style.css /tmp/bmi-calculator/admin.php /var/www/html/bmi/ && sudo chown apache:apache /var/www/html/bmi/*.php /var/www/html/bmi/*.css"
```

---

## 13. Instance Management

**Stop instance:**
```bash
aws ec2 stop-instances --instance-ids i-02c7b760e6ac58028
```

**Start instance:**
```bash
aws ec2 start-instances --instance-ids i-02c7b760e6ac58028
```

**Check instance status:**
```bash
aws ec2 describe-instances --instance-ids i-02c7b760e6ac58028 --query 'Reservations[0].Instances[0].{State:State.Name,IP:PublicIpAddress}' --output table
```

**Note:** The public IP may change when restarting the instance. If so, update the Cloudflare A records with the new IP.
