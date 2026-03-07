# AWS BMI Calculator

A full-featured BMI, BMR, TDEE, and macronutrient calculator available as both a standalone PHP web app and a WordPress Gutenberg block plugin. Deployed on AWS with CloudFront CDN, Application Load Balancer, and Auto Scaling for high availability.

## Live URLs

| Application | URL |
|-------------|-----|
| **Standalone BMI Calculator** | https://aaronzammit.com/bmi/ |
| **WordPress BMI Calculator** | https://aaronzammit.com/index.php/bmi-calculator/ |
| **WordPress Admin** | https://aaronzammit.com/wp-admin/ |
| **BMI Admin Panel** | https://aaronzammit.com/bmi/admin.php |
| **Health Check** | https://aaronzammit.com/bmi/health.php |

## Features

### Core Calculator
- BMI calculation with visual gauge and category classification
- BMR (Basal Metabolic Rate) and TDEE (Total Daily Energy Expenditure) calculation
- Daily macronutrient guide (protein, carbs, fats in grams)
- Body Fat % estimation via Navy Method (neck/waist/hip measurements)
- Waist-to-Height Ratio (WHtR) health indicator
- Weight velocity tracking and goal date projection
- Interactive BMI progress chart (Chart.js)
- Downloadable PDF health report (jsPDF)
- Advanced real-time unit converter (weight and height)

### Dietary Preference Presets
A "Diet Type" selector auto-adjusts macronutrient percentages:

| Diet Type | Protein | Carbs | Fats |
|-----------|---------|-------|------|
| Balanced (default) | 30% | 40% | 30% |
| Keto | 20% | 5% | 75% |
| Paleo | 30% | 20% | 50% |
| High Protein | 40% | 30% | 30% |

Selecting a preset immediately recalculates macro grams if results are visible. The WordPress block editor includes a Diet Type selector that auto-updates the macro ratio sliders.

### Accessibility (WCAG AA)
- `aria-label` and `aria-required` on all form inputs
- `role="status"` and `aria-live="polite"` on results area for screen reader announcements
- `role="img"` with descriptive `aria-label` on the BMI gauge
- Visible `:focus-visible` outlines on all interactive elements
- Skip link for keyboard navigation
- Dark mode contrast fixes: status colors lightened to meet 4.5:1 contrast ratio
- `.result-subtext` uses CSS variable instead of hardcoded `#777`

### Multi-language Support (i18n)
- **Native app:** Client-side i18n with language selector (English, Spanish, French). All ~50 user-facing strings externalized in `lang.js`. Language preference persists via localStorage.
- **WordPress plugin:** All PHP strings wrapped with `__()` / `esc_html__()` for WP i18n. JS strings passed via `wp_localize_script`. POT translation template included at `languages/bmi-calculator-block.pot`.

### User Management & History
- User authentication (login/signup) with personal history tracking
- Per-user record filtering and chart display
- Goal weight setting with projected goal date
- CSV export of filtered records
- Visitor counter

### WordPress Plugin Features
- Gutenberg block with sidebar controls (accent color, history toggle, converter toggle, macro ratios, diet type)
- Shortcode support: `[bmi_calculator]` with all block attributes
- Lead capture form with email opt-in (shown after calculation)
- REST API for record management
- WP user integration (auto-fill name, personal history via user meta)
- Extensibility hooks and filters (`bmi_calculator_form_fields`, `bmi_calculator_results_html`, etc.)

### Admin & Monitoring
- Admin panel with HTTP Basic Authentication
- Soft-delete records (hide/unhide)
- **Google Analytics** integration for traffic insights

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.4 |
| Database | SQLite 3 |
| Web Server | Apache (httpd) with PHP-FPM |
| CDN | AWS CloudFront |
| Load Balancer | AWS Application Load Balancer |
| Compute | AWS EC2 Auto Scaling (2-4 t3.micro) |
| SSL/HTTPS | AWS ACM Certificate |
| DNS | Cloudflare |
| Analytics | Google Analytics 4 (G-3B3EDTZ0JT) |
| Monitoring | AWS CloudWatch |
| Domain | aaronzammit.com |
| Region | eu-central-1 (Frankfurt) |

## Architecture

```
Internet → Cloudflare DNS → CloudFront (HTTPS) → ALB → Auto Scaling Group
                                                              ↓
                                                    EC2 instances (2-4)
                                                    Apache + PHP + SQLite
```

## Project Structure

```
.
├── index.php              # Main application - form, BMI calculation, results display
├── functionality.js       # Calculator logic, diet presets, i18n, chart, PDF report
├── lang.js                # Translation dictionary (en, es, fr)
├── style.css              # Application styling with a11y and dark mode support
├── auth.php               # User authentication (login/signup)
├── login.php              # Login/signup page
├── admin.php              # Admin panel - view all records, hide/unhide (password protected)
├── db.php                 # Database layer - SQLite connection, CRUD operations
├── health.php             # ALB health check endpoint
├── deploy.sh              # Automated deployment script for EC2
├── manifest.json          # PWA manifest
├── sw.js                  # Service worker for PWA
├── wordpress-plugin/
│   ├── bmi-calculator-block.php   # Plugin main file - block registration, rendering, i18n
│   ├── block/
│   │   ├── block.json             # Block metadata and attributes (incl. dietType)
│   │   └── editor.js              # Gutenberg editor controls
│   ├── assets/
│   │   ├── js/bmi-functionality.js  # Front-end calculator logic with diet presets & i18n
│   │   └── css/bmi-style.css        # Scoped styles with a11y and dark mode fixes
│   ├── includes/
│   │   ├── db.php                 # WordPress SQLite database operations
│   │   ├── rest-api.php           # REST API endpoints (record, history, lead, user)
│   │   ├── leads.php              # Lead capture table management
│   │   └── admin.php              # WP admin leads page
│   ├── languages/
│   │   └── bmi-calculator-block.pot  # Translation template for translators
│   └── DEPLOYMENT.md             # WordPress plugin deployment guide
├── URLS.md                # Full URL reference for all endpoints
├── ARCHITECTURE.md        # AWS architecture documentation
├── SETUP_STEPS.md         # Detailed step-by-step setup commands
└── README.md              # This file
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
| hidden | INTEGER | 0 = visible, 1 = hidden from public page |
| created_at | DATETIME | Timestamp of record creation |

SQLite table `visitor_counter`:

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER | Always 1 (single row) |
| count | INTEGER | Total page visits |

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

| Site | URL |
|------|-----|
| **Standalone BMI Calculator** | https://aaronzammit.com/bmi/ |
| **WordPress BMI Calculator** | https://aaronzammit.com/index.php/bmi-calculator/ |
| **WordPress Home** | https://aaronzammit.com/ |
| **WordPress Admin** | https://aaronzammit.com/wp-admin/ |
| **BMI Admin Panel** | https://aaronzammit.com/bmi/admin.php |
| **Health Check** | https://aaronzammit.com/bmi/health.php |
| **Google Analytics** | https://analytics.google.com/ |

### REST API Endpoints (WordPress)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/bmi-calculator/v1/history` | Retrieve all BMI records |
| POST | `/wp-json/bmi-calculator/v1/record` | Save a new BMI record |
| POST | `/wp-json/bmi-calculator/v1/lead` | Submit lead capture email |
| POST | `/wp-json/bmi-calculator/v1/user-record` | Save record for logged-in user |
| GET | `/wp-json/bmi-calculator/v1/user-history` | Get personal history (logged-in) |

### Standalone API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/bmi/index.php?action=get_history` | Retrieve BMI records |
| GET | `/bmi/index.php?action=get_goal` | Get user's goal weight |
| POST | `/bmi/index.php` | Save record or set goal (`action: save` / `action: set_goal`) |

See [URLS.md](URLS.md) for the complete URL reference including infrastructure endpoints.

## Admin Panel

The admin panel at `/bmi/admin.php` is protected with HTTP Basic Authentication.

**Default credentials:**
- Username: `admin`
- Password: `bmi2026!`

To change credentials, edit the `ADMIN_USER` and `ADMIN_PASS` constants at the top of `admin.php`.

**Admin features:**
- View all BMI records (including hidden ones)
- Hidden records are displayed with reduced opacity
- Hide or unhide any record with a single click
- Records hidden from the public page remain in the database

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
- Admin panel is protected with HTTP Basic Authentication — change the default credentials in production

## License

MIT
