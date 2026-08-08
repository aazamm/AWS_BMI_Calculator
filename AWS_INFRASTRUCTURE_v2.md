# AWS Infrastructure v2 — BMI Calculator

**Date:** 2026-08-07 · **Account:** 359345324847 · **Region:** eu-central-1 (CloudFront/ACM: us-east-1)
**Method:** live component-by-component CLI investigation (read-only profile `bmi-ro`)
**Supersedes:** `AWS_INFRASTRUCTURE.md` (2026-02-20, EC2/WordPress era)

The BMI calculator (`https://aaronzammit.com/bmi/`) runs as a **single PHP 8.4 container on ECS
Fargate**, behind CloudFront and a WAF-protected ALB, with data on a shared RDS PostgreSQL
instance. The same ALB and RDS also serve the separate **financial-rss** application
(`echelonfinance.live`). WordPress, MariaDB, and all EC2 instances were decommissioned in June 2026.

---

## Architecture Diagram

```
                      ┌───────────────────────── INTERNET ─────────────────────────┐
                      │       Users (aaronzammit.com)         Scanners / bots      │
                      └───────────────────┬────────────────────────────────────────┘
                                          │ HTTPS · TLSv1.2+ · HTTP/2 + HTTP/3
  Cloudflare DNS                   ┌──────▼───────────────────────┐
  aaronzammit.com ──CNAME────────► │ CloudFront  E16YYJ4DT46N17   │
  (no Route 53 zones)              │ PriceClass_100 · ACM cert    │
                                   │ cache: /bmi/icons/*, *.css,  │
                                   │        *.js (24h) — rest OFF │
                                   │ logs → s3://aazamm-bmi-logs…/cf-logs/
                                   └──────┬───────────────────────┘
                                          │ HTTP :80 (+ X-CloudFront-Forwarded-Proto: https)
                     ┌────────────────────▼────────────────────┐
    WAF (REGIONAL)   │ ALB bmi-calculator-alb                  │
    bmi-calculator-──┤ internet-facing · 3 AZs · default VPC   │
    waf: 4 managed   │ sg: 80/443 open · logs → …/alb-logs/    │
    rule groups +    └──────┬───────────────────────────┬──────┘
    scanner-URI +           │ host aaronzammit.com      │ host echelonfinance.live
    rate-limit rules        │ (also default + staging)  │ (+ staging)
                   ┌────────▼──────────┐       ┌────────▼───────────────┐
                   │ bmi-fargate-tg    │       │ financial-rss-         │
                   │ type ip · sticky  │       │ fargate-tg             │
                   │ hc /bmi/health.php│       └────────┬───────────────┘
                   └────────┬──────────┘                │
              ┌─────────────▼──────────────┐   ┌────────▼───────────────┐
              │ ECS bmi-cluster            │   │ ECS financial-rss-     │
              │  service bmi-web           │   │ cluster (separate app) │
              │  Fargate 0.25 vCPU/512 MB  │   └────────┬───────────────┘
              │  1 task (autoscale 1–3,    │            │
              │  CPU target 60%)           │            │
              │  image: ECR bmi-web:latest │            │
              │  (php:8.4-apache)          │            │
              └──────┬──────────┬──────────┘            │
                     │          │ at container start    │
                     │   ┌──────▼─────────────────┐     │
                     │   │ SSM Parameter Store    │     │
                     │   │ /bmi/* SecureString ×4 │     │
                     │   │ (KMS alias/aws/ssm)    │     │
                     │   └────────────────────────┘     │
                     │ :5432                            │ :5432
              ┌──────▼──────────────────────────────────▼──────┐
              │ RDS financial-rss-db · PostgreSQL 16.13        │
              │ db.t3.micro · 20 GB gp3 · single-AZ (1b)       │
              │ private · DB "bmi_calculator" + financial-rss  │
              └────────────────────────────────────────────────┘

  CI/CD:  git push main ──► GitHub Actions ──OIDC──► role bmi-ci-oidc
          ──► docker build ──► ECR bmi-web:latest ──► ecs update-service (rolling)

  Alerting: CloudWatch alarms (8) ──► SNS bmi-calculator-alerts ──► aazamm@outlook.com
```

---

## 1. DNS & TLS

| Item | Value |
|------|-------|
| DNS provider | **Cloudflare** (no Route 53 hosted zones in the account) |
| Record | `aaronzammit.com` CNAME → `d2392ulp4il11k.cloudfront.net` |
| ACM certificate | `arn:aws:acm:us-east-1:…:certificate/df802f98-5faa-4328-a927-571382bd00e5` |
| Covers | `aaronzammit.com`, `*.aaronzammit.com` · Status ISSUED · expires 2027-02-24, auto-renewal eligible (DNS-validated) |

## 2. CloudFront — `E16YYJ4DT46N17`

| Setting | Value |
|---------|-------|
| Domain / alias | `d2392ulp4il11k.cloudfront.net` / `aaronzammit.com` |
| Origin | `bmi-calculator-alb-…elb.amazonaws.com`, HTTP-only, header `X-CloudFront-Forwarded-Proto: https` |
| Viewer policy | redirect-to-https · TLSv1.2_2021 · **HTTP/2 + HTTP/3** (h3 enabled 2026-08-07) |
| Default behavior | CachingDisabled + AllViewer (dynamic PHP passes through) |
| Cache behaviors (added 2026-08-07) | `/bmi/icons/*`, `*.css`, `*.js` → CachingOptimized (24h default TTL), GET/HEAD, compressed |
| Standard logging | **Enabled** (2026-08-07) → `s3://aazamm-bmi-logs-eu-central-1/cf-logs/` |
| Price class | PriceClass_100 (NA + EU edges) · No CloudFront-attached WAF (WAF sits on the ALB) |

*Caveat: origin sends no Cache-Control, so JS/CSS changes can stay edge-cached up to 24h after a
deploy — add a CI invalidation step or Apache Cache-Control headers if this bites.*

## 3. WAF — `bmi-calculator-waf` (REGIONAL, associated with the ALB)

| Priority | Rule | 24h blocks (2026-08-07) |
|----------|------|-------------------------|
| 1 | AWSManagedRulesCommonRuleSet | 496 |
| 2 | AWSManagedRulesKnownBadInputsRuleSet | 197 |
| 3 | AWSManagedRulesPHPRuleSet | 5 |
| 4 | AWSManagedRulesSQLiRuleSet | 0 |
| 5 | BlockScannerURIs (custom: phpunit/eval-stdin/ThinkPHP/pearcmd/docker-API probes) | 1 |
| 6 | RateLimitPerIP — 2,000 req/5 min | 0 (never fires; scanner waves stay below it) |

Blocks ~25% of all traffic (~700/day). Blocked requests surface as ELB 403s.

## 4. Application Load Balancer — `bmi-calculator-alb`

| Setting | Value |
|---------|-------|
| Scheme / type | internet-facing · application · state active |
| VPC / subnets | default VPC `vpc-04c3e303f1975de6c` (172.31.0.0/16) · 3 subnets across eu-central-1a/b/c |
| Security group | `sg-0d78cc17352915ee6` — 80 + 443 from 0.0.0.0/0 |
| Listener | HTTP :80 only (TLS terminates at CloudFront) |
| Idle timeout | 60 s · Deletion protection: **enabled** (2026-08-07) |
| Access logs | **Enabled** (2026-08-07) → `s3://aazamm-bmi-logs-eu-central-1/alb-logs/` |

### Listener rules (host-header routing)

| Priority | Host | Target group |
|----------|------|--------------|
| 5 | `echelonfinance.live` | financial-rss-fargate-tg |
| 15 | `aaronzammit.com` | bmi-fargate-tg |
| 20 | `fargate-staging.echelonfinance.live` | financial-rss-fargate-tg |
| 30 | `bmi-staging.aaronzammit.com` | bmi-fargate-tg |
| default | * | bmi-fargate-tg |

### Target group `bmi-fargate-tg`

Type **ip** · HTTP :80 · health check `/bmi/health.php` (checks PostgreSQL) · stickiness
**lb_cookie 24h** (PHP sessions) · deregistration delay 30 s · current targets: 1× healthy
(172.31.45.213:80).

## 5. ECS Fargate — cluster `bmi-cluster`, service `bmi-web`

| Setting | Value |
|---------|-------|
| Task definition | `bmi-web:2` · 0.25 vCPU / 512 MB · awsvpc · platform LATEST |
| Container | image ECR `bmi-web:latest` (php:8.4-apache) · port 80 |
| Env vars (plain) | `BASE_URL`, `GOOGLE_CLIENT_ID`, `ADMIN_USER` |
| Secrets (SSM-injected) | `DATABASE_URL`, `SESSION_SECRET`, `GOOGLE_CLIENT_SECRET`, `ADMIN_PASS` |
| Logs | awslogs → `/ecs/bmi` (30-day retention), stream prefix `web` |
| Roles | execution `bmi-ecs-exec` (pull image + fetch/decrypt SSM) · task `bmi-task` (no permissions — app needs none) |
| Networking | public subnets, `assignPublicIp: ENABLED` (needed for ECR pull in default VPC — no NAT); SG `bmi-fargate-sg` accepts :80 **only from the ALB SG** |
| Deployment | rolling, min 100% / max 200%, health-check grace 60 s |
| Autoscaling | 1–3 tasks, target-tracking **CPU 60%** (out 60 s / in 300 s cooldown) |
| Utilization (typical) | CPU ~1%, memory ~6.5% — one task is ample |
| Container Insights | enabled (performance log group, 1-day retention) |

## 6. ECR — `bmi-web`

Scan-on-push **enabled**. Latest image: tags `latest`, `2d295d8`, pushed 2026-06-27, ~192 MB.
**Last scan (2026-06-27): 1 CRITICAL (CVE-2026-12087, perl 5.40.1-6), 8 HIGH, 20 MEDIUM** — OS
packages in the June base image; a rebuild/redeploy (any push to main) picks up the patched
`php:8.4-apache` base.

## 7. RDS — `financial-rss-db` (shared)

| Setting | Value |
|---------|-------|
| Engine | PostgreSQL **16.13** · db.t3.micro · 20 GB gp3 |
| Endpoint | `financial-rss-db.cbmw40ai0tgk.eu-central-1.rds.amazonaws.com:5432` (private) |
| AZ | eu-central-1b · **single-AZ** |
| Databases | `bmi_calculator` (this app) + financial-rss |
| Backups | automated, retention **7 days** (raised from 1 on 2026-08-07) · window 22:08–22:38 UTC |
| Storage encryption | **DISABLED** |
| Deletion protection | **enabled** (2026-08-07) |
| Utilization | CPU ~3.7% · ≤6 connections · 18.2 GB free (~9% used) |
| SG `financial-rss-rds-sg` | :5432 from `bmi-fargate-sg` and `financial-rss-fargate-sg` only (leftover EC2 SG rule removed 2026-08-07) |

## 8. VPC & Security Groups

Default VPC `vpc-04c3e303f1975de6c` (172.31.0.0/16), public subnets in 3 AZs. Ingress chain:

| SG | Allows | From |
|----|--------|------|
| `bmi-calculator-alb-sg` | 80, 443 | 0.0.0.0/0 |
| `bmi-fargate-sg` | 80 | ALB SG only |
| `financial-rss-rds-sg` | 5432 | the two Fargate SGs (+ leftover EC2 SG, see §12) |

## 9. S3 Buckets

| Bucket | Purpose |
|--------|---------|
| `aazamm-bmi-logs-eu-central-1` | ALB (`alb-logs/`) + CloudFront (`cf-logs/`) access logs · private · 90-day auto-expiry (created 2026-08-07) |
| `aazamm-tf-state-eu-central-1` | Terraform/OpenTofu state (`bmi-fargate/terraform.tfstate`) |
| ~~`bmi-calculator-deploy-359345324847`~~ | **Deleted 2026-08-07.** Held March deploy artifacts *and* the final WordPress backup (2026-06-26 wp-content + DB dump) — the latter noticed only during deletion. Jan-2025 WordPress state remains recoverable from the four retained `bmi-calculator-ami-*` AMIs/snapshots (~$1.6/mo, deletable once WordPress is confirmed never-again-needed) |

## 10. Monitoring & Alerting

**Log groups:** `/ecs/bmi` (30 d, ~42 MB) · Container Insights performance (1 d) · equivalents for financial-rss.

**Alarms (all → SNS `bmi-calculator-alerts` → email aazamm@outlook.com):**

| Alarm | Trigger |
|-------|---------|
| BMI-Calculator-ALB-5xx-Errors | ELB-generated 5xx ≥ 10 / 5 min |
| BMI-Calculator-Target-5xx-Errors | backend 5xx ≥ 10 / 5 min *(new 2026-08-07)* |
| BMI-Calculator-High-Latency | target response > 3 s avg, 2×5 min |
| BMI-Calculator-Unhealthy-Targets | ≥ 1 unhealthy, 2×1 min |
| BMI-Calculator-ECS-High-CPU / -Memory | service > 80%, 2×5 min *(new 2026-08-07)* |
| BMI-Calculator-RDS-Low-Storage / -Memory | < 2 GB free / < 50 MB freeable *(new 2026-08-07)* |
| TargetTracking AlarmHigh/Low ×2 | managed by ECS autoscaling (AlarmLow in ALARM at min capacity is normal) |

## 11. IAM & CI/CD

**Users:** `aazamm_bmi` (AdministratorAccess, CLI profile `bmi-admin`) · `aazamm_bmi_ro`
(ReadOnlyAccess, profile `bmi-ro`) — both created 2026-08-07, see `IAM_ACCESS.md` ·
`bmi-calculator-cicd` — **legacy pre-OIDC CI user, active key unused since 2026-03-19** ·
root — CLI default as of this writing; retirement steps in `IAM_ACCESS.md`.

**Roles:** `bmi-ci-oidc` (GitHub OIDC via `token.actions.githubusercontent.com`, trust scoped to
`repo:aazamm/AWS_BMI_Calculator:*`, inline `deploy` policy: ECR push + ECS update only, no SSM
access) · `bmi-ecs-exec` (inline `bmi-exec-secrets`: `ssm:GetParameters` on the four `/bmi/*` ARNs
+ `kms:Decrypt` on `alias/aws/ssm`) · `bmi-task` (no policies) · financial-rss equivalents.

**Pipeline** (`.github/workflows/deploy.yml`): push to main → PHP lint → OIDC auth →
docker build → push `bmi-web:latest` + `:<sha7>` → `ecs update-service --force-new-deployment` →
wait for stable. `[skip ci]` in the commit message skips deployment.

## 12. Findings from this investigation (2026-08-07)

| # | Severity | Finding | Suggested action |
|---|----------|---------|------------------|
| 1 | HIGH | Container image has 1 CRITICAL / 8 HIGH CVEs (scanned June 27; base image is 6 weeks old) | Any push to main rebuilds on a patched base; consider a scheduled monthly rebuild |
| 2 | HIGH | RDS backup retention is only **1 day**, and it is the only stateful component | ✅ Raised to 7 days (2026-08-07) |
| 3 | MEDIUM | RDS storage unencrypted; deletion protection off (RDS and ALB) | ✅ Deletion protection enabled on both (2026-08-07); encryption still open (snapshot-restore migration) |
| 4 | LOW | Leftover from EC2 era: `bmi-calculator-cicd` user + active key (unused since March), `bmi-calculator-sg` + its RDS ingress rule, `bmi-calculator-deploy-*` bucket | ✅ Mostly done (2026-08-07): key deleted, SG + RDS rule removed, bucket deleted (see §9 note); empty user shell remains to delete |
| 5 | LOW | Fargate tasks get public IPs (default-VPC pattern, SG-locked to ALB) | Acceptable; private subnets + NAT (~$32/mo) or VPC endpoints would be tidier but cost more |
| 6 | INFO | Container Insights on both clusters bills custom metrics for very small workloads | Optional: disable to save a few $/mo |

## 13. Estimated monthly cost (BMI share)

| Component | Est. |
|-----------|------|
| Fargate (0.25 vCPU / 512 MB, 24×7) | ~$9–10 |
| ALB (shared with financial-rss) | ~$20 total |
| WAF (ACL + 6 rules, regional) | ~$11 |
| RDS db.t3.micro + 20 GB gp3 (shared) | ~$15 total |
| CloudFront, S3 logs, ECR, SSM, SNS, alarms | ~$2–3 |
| **BMI-attributable total (with half of shared)** | **~$40** |

## Related documents

`AWS_INVESTIGATION_REPORT.md` (24h metrics, 2026-08-07) · `IAM_ACCESS.md` (CLI identities) ·
`SSM_SECRETS.md` (secrets lifecycle) · `infra/terraform/README.md` (IaC) · `URLS.md` (endpoints)
