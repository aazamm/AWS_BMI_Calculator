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
- **BMI Calculator**: http://54.93.223.221/bmi/
- **WordPress**: http://54.93.223.221/

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
