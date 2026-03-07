# AWS BMI Calculator - URL Reference

**Domain:** aaronzammit.com
**Last verified:** 2026-03-07

---

## Application URLs

### Standalone BMI Calculator

| URL | Description |
|-----|-------------|
| https://aaronzammit.com/bmi/ | Standalone BMI Calculator (main entry point) |
| https://aaronzammit.com/bmi/index.php | Same as above (explicit PHP file) |

### WordPress Gutenberg Block

| URL | Description |
|-----|-------------|
| https://aaronzammit.com/index.php/bmi-calculator/ | BMI Calculator rendered inside WordPress theme (Twenty Twenty-Five) |

### WordPress Admin

| URL | Description |
|-----|-------------|
| https://aaronzammit.com/wp-admin/ | WordPress admin dashboard (redirects to login) |

---

## API Endpoints

### WordPress REST API

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/history | None | Retrieve all BMI records (JSON array) |
| POST | https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/record | None | Save a new BMI record (guest) |
| POST | https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/lead | None | Submit lead capture email |
| POST | https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/user-record | WP Login | Save BMI record for logged-in user (stored in user meta) |
| GET | https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/user-history | WP Login | Get personal BMI history from user meta |

**Alternative query-string format** (same functionality):

| Method | URL | Description |
|--------|-----|-------------|
| GET | https://aaronzammit.com/?rest_route=/bmi-calculator/v1/history | History via query string |
| POST | https://aaronzammit.com/?rest_route=/bmi-calculator/v1/record | Save via query string |

**POST `/record` request body** (JSON):
```json
{
  "name": "John",
  "surname": "Doe",
  "weight_kg": "75.00",
  "height_cm": "175.00"
}
```

**POST `/record` response:**
```json
{
  "success": true,
  "record": {
    "bmi": 24.49,
    "category": "Normal weight"
  }
}
```

**POST `/lead` request body** (JSON):
```json
{
  "email": "user@example.com",
  "name": "John",
  "bmi": 24.49
}
```

**POST `/user-record` request body** (JSON, requires WP login + X-WP-Nonce):
```json
{
  "weight_kg": "75.00",
  "height_cm": "175.00"
}
```

### Standalone API

| Method | URL | Description |
|--------|-----|-------------|
| GET | https://aaronzammit.com/bmi/index.php?action=get_history | Retrieve BMI records |
| GET | https://aaronzammit.com/bmi/index.php?action=get_goal | Get user's goal weight (requires login) |
| POST | https://aaronzammit.com/bmi/index.php | Save a record (`"action": "save"`) or set goal (`"action": "set_goal"`) |

---

## Health & Monitoring

| URL | Description |
|-----|-------------|
| https://aaronzammit.com/bmi/health.php | Health check endpoint (used by ALB target group) |

**Health check response (healthy):**
```json
{
  "healthy": true,
  "checks": {
    "sqlite": "ok",
    "mariadb": "ok",
    "disk": "ok",
    "apache": "ok"
  }
}
```

---

## Admin Panel

| URL | Auth | Description |
|-----|------|-------------|
| https://aaronzammit.com/bmi/admin.php | HTTP Basic Auth | BMI records admin panel (view/hide/unhide records) |

---

## Infrastructure URLs

### CloudFront (CDN)

| URL | Description |
|-----|-------------|
| https://d2392ulp4il11k.cloudfront.net/ | CloudFront distribution (direct access, same as aaronzammit.com) |
| https://d2392ulp4il11k.cloudfront.net/bmi/ | Standalone BMI Calculator via CloudFront |

### Application Load Balancer

| URL | Description |
|-----|-------------|
| http://bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com/ | ALB direct (HTTP only, no TLS) |
| http://bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com/bmi/ | Standalone BMI Calculator via ALB |

> **Note:** ALB URLs are HTTP-only and bypass CloudFront/WAF. They should not be used publicly.

---

## GitHub Repositories

| URL | Description |
|-----|-------------|
| https://github.com/aazamm/AWS_BMI_Calculator | BMI Calculator source code (standalone + WordPress plugin) |
| https://github.com/aazamm/claude | Parent repository (private, contains all sub-projects) |

---

## DNS & Certificate

| Resource | Value |
|----------|-------|
| Domain | aaronzammit.com |
| DNS Provider | Cloudflare |
| DNS Record | CNAME → d2392ulp4il11k.cloudfront.net |
| TLS Certificate | ACM (arn:aws:acm:us-east-1:359345324847:certificate/df802f98-5faa-4328-a927-571382bd00e5) |

---

## Request Flow

```
User → aaronzammit.com (Cloudflare DNS)
     → CloudFront (d2392ulp4il11k.cloudfront.net)
     → WAF (bmi-calculator-waf)
     → ALB (bmi-calculator-alb)
     → EC2 Instance (t3.micro, eu-central-1b or eu-central-1c)
     → Apache + PHP-FPM → WordPress / Standalone PHP
```
