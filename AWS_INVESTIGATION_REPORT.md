# AWS Infrastructure Investigation Report - Last 24 Hours

**Date:** 2026-08-07 (window 2026-08-06 19:08 → 2026-08-07 19:08 CEST)
**Infrastructure:** BMI Calculator (aaronzammit.com/bmi/) — ECS Fargate
**Previous report:** 2026-02-11 (pre-Fargate, see git history)

## Architecture (current)

```
User → CloudFront E16YYJ4DT46N17 (CachingDisabled) → bmi-calculator-alb (+ WAF regional)
         → host-header routing:
             aaronzammit.com / default        → bmi-fargate-tg → ECS bmi-cluster/bmi-web (1× Fargate 0.25vCPU/512MB, scale 1–3)
             echelonfinance.live (+staging)   → financial-rss-fargate-tg (separate app, shared ALB)
         → RDS financial-rss-db (Postgres db.t3.micro, single-AZ, 20GB) — shared, DB `bmi_calculator`
```

EC2 ASG, WordPress, and MariaDB are fully decommissioned. Secrets in SSM `/bmi/*`; CI via GitHub OIDC.

## 1. CloudFront (E16YYJ4DT46N17)

| Metric | Value |
|--------|-------|
| Total Requests | ~2,536 (vs ~12,600 in Feb) |
| Data Transferred | ~2.2 MB |
| Peak Hour | 11:08 CEST — 792 requests |
| 4xx Error Rate | 25–80% during scanner waves (traffic is mostly scanners) |
| 5xx Error Rate | **0% all 24h** |
| Cache Policy | still **CachingDisabled** |
| Access Logging | still **Disabled** |
| HTTP Version | HTTP/2 (HTTP/3 not enabled) |
| WAF on CloudFront | None (WAF is regional, on the ALB) |

## 2. ALB (bmi-calculator-alb)

| Metric | Value |
|--------|-------|
| Total Requests | ~2,094 |
| 2xx | ~126 (6%) |
| 3xx | ~145 |
| Target 4xx | ~1,823 (87% — scanner 404s) |
| ELB 4xx | ~767 (mostly WAF 403 blocks) |
| **Target 5xx** | **0** (was 817 in Feb) |
| ELB 5xx | 0 |
| Avg Response Time | 1–88 ms |
| Max Response Time | 0.55 s |
| Healthy Targets | 1/1 the entire 24h, zero unhealthy minutes |

## 3. WAF (bmi-calculator-waf, regional on ALB)

| Rule | Blocked (24h) |
|------|---------------|
| CommonRuleSet | 496 |
| KnownBadInputs | 197 |
| PHPRuleSet | 5 |
| BlockScannerURIs | 1 |
| SQLiRuleSet / RateLimitPerIP | 0 |
| **Total blocked** | **~699** (~25% of all traffic) |

## 4. ECS Fargate (bmi-cluster / bmi-web)

| Metric | Value |
|--------|-------|
| Tasks | 1 running (task def `bmi-web:2`), steady state |
| Task size | 0.25 vCPU / 512 MB, autoscaling 1–3 on CPU |
| CPU | avg ~1.1%, peak 4.3% |
| Memory | ~6.5% (~33 MB of 512 MB) |
| App logs | Only scanner-probe 404s (`x.php`, `wp-*.php`, webshell names). **No genuine application errors.** |

## 5. RDS (financial-rss-db, shared Postgres)

| Metric | Value |
|--------|-------|
| CPU | ~3.7% steady |
| Connections | max 6 |
| Free Storage | 18.2 GB of 20 GB (~9% used) |
| Freeable Memory | 145–185 MB (of 1 GB, typical for t3.micro Postgres) |

## 6. CloudWatch Alarms

| Alarm | State | Note |
|-------|-------|------|
| BMI-Calculator-ALB-5xx-Errors | OK | monitors ELB 5xx only, not target 5xx |
| BMI-Calculator-High-Latency | OK | |
| BMI-Calculator-Unhealthy-Targets | OK | |
| BMI-Calculator-High-CPU | **INSUFFICIENT_DATA since 26 Jun** | points at deleted EC2 |
| BMI-Calculator-High-Memory | **INSUFFICIENT_DATA since 26 Jun** | CloudWatch-agent metric, gone with EC2 |
| BMI-Calculator-High-Disk | **INSUFFICIENT_DATA since 26 Jun** | same |
| TargetTracking bmi-web AlarmLow | ALARM | normal (service at min capacity) |

## Assessment

**The February crisis is resolved.** Scanner traffic is still ~85–90% of requests, but: WAF blocks ~25% at the ALB, the rest get cheap 404s, target 5xx went from 817 → 0, latency is excellent, and the single Fargate task never exceeded 4.3% CPU. Overall traffic is ~5× lower than February (scanners lost interest post-WordPress). Health was 100% for the full window.

## Recommendations

### HIGH — Alarm hygiene (the monitoring is half-blind)
1. **Delete the three dead EC2 alarms** (High-CPU, High-Memory, High-Disk) — stuck in INSUFFICIENT_DATA since the June decommission.
2. **Replace with ECS + RDS alarms**: `AWS/ECS CPUUtilization`/`MemoryUtilization` (bmi-cluster/bmi-web) >80%, `AWS/RDS FreeStorageSpace` <2 GB and `FreeableMemory` <50 MB, and add `HTTPCode_Target_5XX_Count` ≥10 to complement the existing ELB-5xx alarm. Route all to `bmi-calculator-alerts`.

### MEDIUM — Carry-overs from February, still open
3. **CloudFront caching** — still CachingDisabled; cache static assets (css/js/icons) even with low traffic for latency + resilience.
4. **CloudFront access logging** (and/or ALB logs) — still no audit trail of attacking IPs.
5. **Enable HTTP/3** — free toggle.

### MEDIUM — Account hygiene
6. **Stop using root credentials for CLI/daily work** — create an IAM/Identity Center user; lock root with MFA.
7. **Verify RDS automated backups/retention** — single-AZ db.t3.micro is now the only stateful component in the whole stack.

### LOW
8. Rate-limit rule (2000 req/5 min) never fires; scanner waves stay below it. Could lower to ~500, but WAF managed rules + cheap 404s make this optional.
9. Update `AWS_INFRASTRUCTURE.md` — still describes the EC2/WordPress architecture.

---

## Implementation Log — 2026-08-07

| # | Item | Status |
|---|------|--------|
| 1 | Delete 3 dead EC2 alarms (High-CPU/Memory/Disk) | ✅ Done |
| 2 | New alarms: ECS-High-CPU, ECS-High-Memory, RDS-Low-Storage (<2GB), RDS-Low-Memory (<50MB), Target-5xx-Errors (≥10/5min) — all → `bmi-calculator-alerts` SNS | ✅ Done (all OK state) |
| 3 | CloudFront caching: behaviors `/bmi/icons/*`, `*.css`, `*.js` → CachingOptimized + AllViewer; default behavior stays CachingDisabled (dynamic PHP) | ✅ Done |
| 4 | Logging: new S3 bucket `aazamm-bmi-logs-eu-central-1` (private, 90-day expiry) — ALB logs → `alb-logs/`, CloudFront logs → `cf-logs/` | ✅ Done |
| 5 | HTTP/3 enabled on CloudFront | ✅ Done |
| 6 | IAM user `aazamm_bmi_ro` + ReadOnlyAccess, CLI profile `bmi-ro` (`aws --profile bmi-ro …`) | ✅ Done |
| 7 | RDS backup verification | ⏳ Pending |

**Caveat:** with `*.js`/`*.css` cached (default TTL 24h since the origin sends no Cache-Control), a deploy that changes JS/CSS may serve stale assets for up to a day. Fix by adding a CloudFront invalidation step to CI, or by setting Cache-Control headers in the image's Apache config.
