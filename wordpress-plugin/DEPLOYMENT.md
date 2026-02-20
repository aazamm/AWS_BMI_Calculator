# BMI Calculator WordPress Gutenberg Block - Deployment Guide

## Overview

This document covers the migration of the BMI Calculator from a standalone PHP app at `/bmi/` into a WordPress Gutenberg block plugin. The calculator renders inside the WordPress theme (Twenty Twenty-Five) with proper header, navigation, and footer, while the standalone `/bmi/` endpoint remains untouched.

**Date:** 2026-02-21
**Author:** Aaron Zammit
**Plugin Version:** 1.0.0

---

## Architecture

### Approach: Dynamic Server-Side Rendered Block (No Build Step)

A WordPress plugin with a dynamic Gutenberg block that renders the BMI Calculator HTML via a PHP `render_callback`. No npm/webpack needed - just PHP + vanilla JS + CSS.

### Plugin Structure

```
wp-content/plugins/bmi-calculator-block/
├── bmi-calculator-block.php          # Main plugin file (11.5 KB)
├── includes/
│   ├── db.php                        # SQLite layer (2.8 KB)
│   └── rest-api.php                  # WordPress REST API endpoints (1.5 KB)
├── assets/
│   ├── js/bmi-functionality.js       # Frontend JavaScript (13.7 KB)
│   └── css/bmi-style.css             # Scoped CSS (7.7 KB)
└── block/
    ├── block.json                    # Block metadata (0.3 KB)
    └── editor.js                     # Editor placeholder (1.0 KB)
```

Total: 7 files, ~38 KB

### Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| No build step | Simplicity - uses `wp.blocks` and `wp.element` globals directly |
| Server-side rendering | Block content is dynamic (visitor count, DB queries) |
| Scoped CSS under `.bmi-calculator-wrap` | Prevents style leaking into/from WordPress theme |
| All element IDs prefixed with `bmi-` | Avoids ID collisions with WordPress or other plugins |
| `multiple: false` in block.json | Prevents duplicate block instances (avoids duplicate ID issues) |
| Shared SQLite database | WordPress block reads/writes the same `bmi_data.db` as standalone app |
| REST API endpoints replace inline PHP | WordPress-native approach for AJAX calls with nonce security |

---

## Infrastructure

### AWS Components

| Component | Detail |
|-----------|--------|
| **EC2 Instances** | 2x t3.micro in ASG (`bmi-calculator-asg`) |
| **Instance IDs** | `i-065a560e87bfe8ab6` (eu-central-1c), `i-06da8757b133a9679` (eu-central-1b) |
| **Load Balancer** | ALB (`bmi-calculator-alb`) |
| **CDN** | CloudFront (`E16YYJ4DT46N17`) |
| **WAF** | `bmi-calculator-waf` with managed rules |
| **Domain** | aaronzammit.com (Cloudflare DNS → CloudFront → ALB → EC2) |
| **WordPress DB** | MariaDB 10.5 (local per instance, NOT shared) |
| **BMI Data DB** | SQLite at `/var/www/html/bmi/bmi_data.db` (local per instance) |
| **PHP** | PHP 8.4.16 with php-fpm, SQLite3 extension |

### Important: Separate MariaDB Per Instance

Each EC2 instance runs its own local MariaDB. WordPress data (plugins, pages, posts) is **NOT shared** between instances. This means:

- Plugin must be activated on **each instance** separately
- WordPress pages must be created on **each instance** separately
- Any WordPress configuration changes must be applied to **all instances**
- New instances from the ASG launch template will need the same setup (handled via updated AMI)

The SQLite database for BMI records is also local per instance. Users may see different history depending on which instance serves their request.

---

## Plugin Files - Adaptations from Standalone

### 1. `bmi-calculator-block.php` (Main Plugin File)

- WordPress plugin header with metadata
- Registers block via `register_block_type()` with `block.json` + render callback
- Registers REST API routes via `rest_api_init` hook
- Enqueues assets (Chart.js CDN, `bmi-functionality.js`, `bmi-style.css`) only on pages with the block (`has_block()` check)
- `wp_localize_script()` passes REST API URL + nonce to JavaScript as `bmiCalcData`
- Render callback outputs the calculator HTML wrapped in `<div class="bmi-calculator-wrap">`
- All element IDs prefixed with `bmi-` (e.g., `id="name"` → `id="bmi-name"`)

### 2. `includes/db.php` (Database Layer)

- Adapted from standalone `db.php`
- Uses `BMI_DB_PATH` constant (defined in main plugin file as `/var/www/html/bmi/bmi_data.db`)
- All function names prefixed with `bmi_` to avoid WordPress namespace collisions
- Functions: `bmi_getDB()`, `bmi_saveRecord()`, `bmi_getRecords()`, `bmi_incrementVisitorCount()`, `bmi_getBMICategory()`
- Shares the same SQLite database as standalone app

### 3. `includes/rest-api.php` (REST API Endpoints)

Replaces the standalone `index.php` AJAX handling:

| Endpoint | Method | Standalone Equivalent |
|----------|--------|-----------------------|
| `/wp-json/bmi-calculator/v1/record` | POST | `POST index.php` (save) |
| `/wp-json/bmi-calculator/v1/history` | GET | `GET index.php?action=get_history` |

- Both endpoints are public (`permission_callback` returns true)
- Input sanitization via `sanitize_text_field()` for strings, `floatval()` for numbers
- Returns same JSON format as standalone for JS compatibility

**Note:** Due to the permalink structure (`/index.php/%year%/...`), REST API URLs use `/index.php/wp-json/` prefix. The `rest_url()` function handles this automatically.

### 4. `assets/js/bmi-functionality.js` (Frontend JavaScript)

Adapted from standalone `functionality.js`:

| Change | Before (Standalone) | After (WordPress Block) |
|--------|---------------------|------------------------|
| Container | `document.body` | `document.querySelector('.bmi-calculator-wrap')` |
| DOM queries | `document.getElementById('name')` | `container.querySelector('#bmi-name')` |
| Dark mode toggle | `document.body.classList.toggle('dark-mode')` | `container.classList.toggle('dark-mode')` |
| localStorage key | `'theme'` | `'bmi-theme'` |
| Save endpoint | `fetch('index.php', {...})` | `fetch(bmiCalcData.restUrl + 'record', {...})` |
| History endpoint | `fetch('index.php?action=get_history')` | `fetch(bmiCalcData.restUrl + 'history')` |
| WP nonce header | N/A | `'X-WP-Nonce': bmiCalcData.nonce` |
| CSV download link | Appended to `document.body` | Appended to `container` |

### 5. `assets/css/bmi-style.css` (Scoped CSS)

All selectors scoped under `.bmi-calculator-wrap`:

| Change | Before | After |
|--------|--------|-------|
| CSS variables | `:root { --bg-color: ... }` | `.bmi-calculator-wrap { --bg-color: ... }` |
| Dark mode | `body.dark-mode { ... }` | `.bmi-calculator-wrap.dark-mode { ... }` |
| Body styles | `body { font-family: ... }` | `.bmi-calculator-wrap { font-family: ... }` |
| All selectors | `.container { ... }` | `.bmi-calculator-wrap .container { ... }` |
| Bare elements | `h1 { }`, `button { }` | `.bmi-calculator-wrap h1 { }`, etc. |
| Media queries | `@media { .form-row { } }` | `@media { .bmi-calculator-wrap .form-row { } }` |

### 6. `block/block.json` (Block Metadata)

- API version 3
- Block name: `bmi-calculator/bmi-block`
- Category: widgets, Icon: heart
- `"multiple": false` prevents inserting twice

### 7. `block/editor.js` (Editor Placeholder)

- Uses `wp.blocks` and `wp.element` globals (no build step)
- `edit`: renders a placeholder box with heart icon
- `save`: returns `null` (dynamic block)

---

## Deployment Steps Executed

### Prerequisites

- AWS CLI configured with account `359345324847`
- SSM agent running on both EC2 instances
- Plugin files created locally in `bmi-calculator/wordpress-plugin/`

### Step 1: Create S3 Deployment Bucket

```bash
aws s3 mb s3://bmi-calculator-deploy-359345324847 --region eu-central-1
```

### Step 2: Package and Upload Plugin

```bash
tar czf /tmp/bmi-plugin.tar.gz -C wordpress-plugin .
aws s3 cp /tmp/bmi-plugin.tar.gz s3://bmi-calculator-deploy-359345324847/bmi-plugin.tar.gz --region eu-central-1
```

### Step 3: Add S3 Read Access to EC2 IAM Role

The `CloudWatchAgentRole` (used by both instances) needed S3 access:

```bash
aws iam put-role-policy \
  --role-name CloudWatchAgentRole \
  --policy-name S3DeploymentReadAccess \
  --policy-document '{
    "Version": "2012-10-17",
    "Statement": [{
      "Effect": "Allow",
      "Action": ["s3:GetObject"],
      "Resource": "arn:aws:s3:::bmi-calculator-deploy-359345324847/*"
    }]
  }'
```

### Step 4: Deploy Plugin Files via SSM

```bash
aws ssm send-command \
  --instance-ids "i-065a560e87bfe8ab6" "i-06da8757b133a9679" \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=[
    "set -e",
    "PLUGIN_DIR=/var/www/html/wp-content/plugins/bmi-calculator-block",
    "mkdir -p $PLUGIN_DIR/{includes,assets/{js,css},block}",
    "aws s3 cp s3://bmi-calculator-deploy-359345324847/bmi-plugin.tar.gz /tmp/bmi-plugin.tar.gz --region eu-central-1",
    "tar xzf /tmp/bmi-plugin.tar.gz -C $PLUGIN_DIR",
    "chown -R apache:apache $PLUGIN_DIR",
    "chmod -R 755 $PLUGIN_DIR",
    "rm /tmp/bmi-plugin.tar.gz"
  ]' \
  --region eu-central-1
```

### Step 5: Activate Plugin and Create Page (Per Instance)

Since each instance has its own MariaDB, plugin activation and page creation must happen on **each instance separately**.

The activation script (`activate_and_page.php`):

```php
<?php
define("WP_USE_THEMES", false);
require("/var/www/html/wp-load.php");

// Activate plugin
$active = get_option("active_plugins", array());
$slug = "bmi-calculator-block/bmi-calculator-block.php";
if (!in_array($slug, $active)) {
    $active[] = $slug;
    update_option("active_plugins", $active);
}

// Create page
$existing = get_page_by_path("bmi-calculator");
if (!$existing) {
    wp_insert_post(array(
        "post_title"   => "BMI Calculator",
        "post_name"    => "bmi-calculator",
        "post_content" => "<!-- wp:bmi-calculator/bmi-block /-->",
        "post_status"  => "publish",
        "post_type"    => "page",
        "post_author"  => 1,
    ));
}

// Flush rewrite rules
flush_rewrite_rules(true);
```

Deployed via base64-encoded SSM command to each instance.

### Step 6: Create .htaccess

WordPress needs `.htaccess` for proper URL routing:

```bash
# Created on both instances via SSM
# Note: AllowOverride is None in httpd.conf, so .htaccess is NOT processed
# However, the /index.php/ prefix in permalink structure bypasses this via PATH_INFO
```

### Step 7: Restart PHP-FPM

```bash
aws ssm send-command \
  --instance-ids "i-065a560e87bfe8ab6" "i-06da8757b133a9679" \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=["systemctl restart php-fpm"]' \
  --region eu-central-1
```

Critical step - opcache was serving stale bytecode without the plugin loaded.

---

## Verification Results

All tests passing as of 2026-02-21:

| # | Test | Result |
|---|------|--------|
| 1 | REST API GET `/history` | 200 OK, returns records |
| 2 | REST API POST `/record` | 200 OK, `{"success": true}` |
| 3 | WordPress page (6 LB requests) | 6/6 HTTP 200 |
| 4 | Block renders inside WP theme | PASS (wp-site-blocks + bmi-calculator-wrap) |
| 5 | Form fields present | PASS (bmi-name, bmi-mainWeight, etc.) |
| 6 | Chart.js loaded | PASS |
| 7 | BMI JS loaded | PASS |
| 8 | BMI CSS loaded | PASS |
| 9 | Localized REST URL (`bmiCalcData`) | PASS |
| 10 | Visitor counter | PASS |
| 11 | Standalone `/bmi/` | 200 OK, unchanged |
| 12 | Health check `/bmi/health.php` | `{"healthy": true}` |

### URLs

- **WordPress Block Page:** `https://aaronzammit.com/index.php/bmi-calculator/`
- **Standalone App:** `https://aaronzammit.com/bmi/`
- **REST API (History):** `https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/history`
- **REST API (Save):** `https://aaronzammit.com/index.php/wp-json/bmi-calculator/v1/record` (POST)
- **Health Check:** `https://aaronzammit.com/bmi/health.php`

---

## Troubleshooting

### Issue: REST API returns 404

**Cause:** PHP-FPM opcache serving stale bytecode.
**Fix:** Restart PHP-FPM on all instances:
```bash
aws ssm send-command --instance-ids "<ids>" --document-name "AWS-RunShellScript" \
  --parameters 'commands=["systemctl restart php-fpm"]' --region eu-central-1
```

### Issue: Page returns 404 on some requests

**Cause:** The load balancer routes to an instance where the plugin/page hasn't been set up.
**Fix:** Run the activation and page creation script on ALL instances.

### Issue: Pretty permalink `/wp-json/` URLs don't work

**Cause:** Apache `AllowOverride None` prevents `.htaccess` processing. The permalink structure uses `/index.php/` prefix which works via PATH_INFO.
**Note:** `rest_url()` automatically generates the correct URL format (`/index.php/wp-json/...`). No fix needed.

### Issue: WAF blocks REST API POST

**Symptoms:** 403 Forbidden when saving records.
**Fix:** Check WAF logs and add allow rule for `/wp-json/bmi-calculator/*` if needed:
```bash
# Check WAF logs
aws wafv2 get-web-acl --name bmi-calculator-waf --scope REGIONAL --id 7b42b8a6-9b01-43b6-b8f4-fb36304629c2 --region eu-central-1
```

---

## Future: Updating the Plugin

### Redeployment Steps

1. Update plugin files locally in `bmi-calculator/wordpress-plugin/`
2. Repackage: `tar czf /tmp/bmi-plugin.tar.gz -C wordpress-plugin .`
3. Upload: `aws s3 cp /tmp/bmi-plugin.tar.gz s3://bmi-calculator-deploy-359345324847/bmi-plugin.tar.gz`
4. Deploy to instances via SSM (same command as Step 4)
5. Restart PHP-FPM on all instances
6. No need to re-activate plugin or recreate page (unless plugin slug changes)

### New Instance Setup (ASG Scaling)

When the ASG launches a new instance from the launch template:

1. The current AMI does NOT include the plugin
2. **Action needed:** Create a new AMI from a working instance and update the launch template
3. Alternative: Add plugin deployment to the user data script in the launch template

---

## AWS Resources Created During Deployment

| Resource | ARN / Name |
|----------|------------|
| S3 Bucket | `bmi-calculator-deploy-359345324847` |
| IAM Inline Policy | `S3DeploymentReadAccess` on `CloudWatchAgentRole` |
| WordPress Page (Instance 1) | Post ID 10, slug `bmi-calculator` |
| WordPress Page (Instance 2) | Post ID 11, slug `bmi-calculator` |
