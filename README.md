# AWS BMI Calculator

A standalone PHP web application that calculates Body Mass Index (BMI) and stores user records in a SQLite database. Deployed alongside WordPress on an AWS EC2 instance running Amazon Linux 2023.

## Features

- Calculate BMI from weight (kg) and height (cm)
- Store records: Name, Surname, Weight, Height, BMI, and timestamp
- View all previous calculations in a table
- BMI category classification (Underweight, Normal weight, Overweight, Obese)
- Lightweight SQLite database (no external DB server needed for the calculator)

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.4 |
| Database | SQLite 3 |
| Web Server | Apache (httpd) with PHP-FPM |
| CMS | WordPress (MariaDB 10.5) |
| SSL/HTTPS | Cloudflare (Flexible mode) |
| Domain | aaronzammit.com |
| Infrastructure | AWS EC2 t3.micro, Amazon Linux 2023 |
| Region | eu-central-1 (Frankfurt) |

## Project Structure

```
.
├── index.php          # Main application - form, BMI calculation, results display
├── db.php             # Database layer - SQLite connection, CRUD operations
├── style.css          # Application styling
├── deploy.sh          # Automated deployment script for EC2
├── SETUP_STEPS.md     # Detailed step-by-step setup commands
└── README.md          # This file
```

## BMI Formula

```
BMI = weight (kg) / height (m)²
```

| BMI Range | Category |
|-----------|----------|
| < 18.5 | Underweight |
| 18.5 - 24.9 | Normal weight |
| 25 - 29.9 | Overweight |
| >= 30 | Obese |

## Database Schema

SQLite table `bmi_records`:

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER | Primary key, auto-increment |
| name | TEXT | User's first name |
| surname | TEXT | User's surname |
| weight_kg | REAL | Weight in kilograms |
| height_cm | REAL | Height in centimetres |
| bmi | REAL | Calculated BMI value |
| created_at | DATETIME | Timestamp of record creation |

## Local Development

### Prerequisites
- PHP 8.x with SQLite3 extension

### Run locally
```bash
brew install php          # macOS
php -S localhost:8000     # Start dev server
# Open http://localhost:8000
```

## AWS Deployment

### Prerequisites
- AWS account with CLI access
- AWS CLI installed and configured (`aws configure`)
- GitHub CLI (optional, for repo management)

### Infrastructure Setup

```bash
# 1. Create SSH key pair
aws ec2 create-key-pair --key-name bmi-calculator-key --query 'KeyMaterial' --output text > ~/.ssh/bmi-calculator-key.pem
chmod 400 ~/.ssh/bmi-calculator-key.pem

# 2. Create security group (SSH + HTTP + HTTPS)
aws ec2 create-security-group --group-name bmi-calculator-sg --description "BMI Calculator SG" --vpc-id <your-vpc-id>
aws ec2 authorize-security-group-ingress --group-id <sg-id> --protocol tcp --port 22 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id <sg-id> --protocol tcp --port 80 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id <sg-id> --protocol tcp --port 443 --cidr 0.0.0.0/0

# 3. Launch EC2 instance (Amazon Linux 2023, t3.micro)
aws ec2 run-instances \
  --image-id <ami-id> \
  --instance-type t3.micro \
  --key-name bmi-calculator-key \
  --security-group-ids <sg-id> \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=bmi-calculator}]'
```

### Application Deployment

```bash
# Copy files to instance
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@<EC2-IP> "mkdir -p /tmp/bmi-calculator"
scp -i ~/.ssh/bmi-calculator-key.pem *.php *.css deploy.sh ec2-user@<EC2-IP>:/tmp/bmi-calculator/

# Run deployment script
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@<EC2-IP> "sudo bash /tmp/bmi-calculator/deploy.sh"
```

The deployment script installs and configures:
- Apache with PHP-FPM
- PHP 8.4 (SQLite3, MySQLnd, mbstring, GD, XML extensions)
- MariaDB 10.5 (for WordPress)
- WordPress (latest)
- BMI Calculator at `/bmi/`

### Live URLs

- **BMI Calculator**: https://aaronzammit.com/bmi/
- **WordPress**: https://aaronzammit.com/

### SSH Access

```bash
ssh -i ~/.ssh/bmi-calculator-key.pem ec2-user@<EC2-IP>
```

## HTTPS / SSL Configuration

HTTPS is handled via Cloudflare in **Flexible** mode:

| Layer | Protocol | Certificate |
|-------|----------|-------------|
| User → Cloudflare | HTTPS | Cloudflare's Universal SSL |
| Cloudflare → EC2 | HTTP | N/A (Flexible mode) |

### Setup Steps

1. **Cloudflare DNS**: Add A records for `@` and `www` pointing to the EC2 public IP, with proxy enabled (orange cloud)
2. **Cloudflare SSL/TLS**: Set encryption mode to **Flexible**
3. **WordPress config**: Added `X-Forwarded-Proto` detection in `wp-config.php` so WordPress recognises HTTPS
4. **Apache config**: Added `/etc/httpd/conf.d/cloudflare.conf` to trust Cloudflare IP ranges for real visitor IPs
5. **Security group**: Port 443 added for HTTPS

### Optional Cloudflare Settings

- **Always Use HTTPS** (SSL/TLS → Edge Certificates): Redirects all HTTP to HTTPS
- **HSTS**: Enforces HTTPS in browsers
- **Automatic HTTPS Rewrites**: Fixes mixed content issues

## Security Notes

- Database credentials are stored on the instance at `/root/db_credentials.txt`
- The SSH key is stored locally at `~/.ssh/bmi-calculator-key.pem`
- Security group allows SSH, HTTP, and HTTPS from all IPs (0.0.0.0/0) — restrict to your IP for production use
- SQLite database file is created at runtime in `/var/www/html/bmi/bmi_data.db`
- HTTPS is terminated at Cloudflare; traffic between Cloudflare and EC2 is over HTTP (Flexible mode)

## License

MIT
