# AWS Infrastructure Investigation Report - Last 24 Hours

**Date:** 2026-02-11
**Infrastructure:** BMI Calculator (aaronzammit.com)

## 1. CloudFront (Distribution E16YYJ4DT46N17)

| Metric | Value |
|--------|-------|
| **Total Requests** | ~12,647 |
| **Total Data Transferred** | ~7.4 MB |
| **Peak Hour** | 20:39 UTC+1 — 4,349 requests |
| **4xx Error Rate (avg)** | Highly variable: 0% to **98.4%** |
| **5xx Error Rate** | ~16% in one hour (22:39), otherwise ~0% |
| **Cache Policy** | **CachingDisabled** (every request hits origin) |
| **WAF** | **None** |
| **Access Logging** | **Disabled** |
| **HTTP Version** | HTTP/2 (HTTP/3 not enabled) |

### Key Concern

The 4xx error rates are extreme — peaking at **98.4%** at 11:39 and **97%** at 18:39. These correlate with massive bot/vulnerability scanner waves hitting your site. Combined with caching disabled, every scanner request is forwarded to your ALB and EC2 instances.

---

## 2. Application Load Balancer (bmi-calculator-alb)

| Metric | Value |
|--------|-------|
| **Total Requests** | ~44,547 (includes health checks) |
| **2xx Responses** | ~9,569 |
| **4xx Responses** | **~34,024** (76% of all traffic!) |
| **5xx Responses (target)** | **817** |
| **5xx Responses (ELB)** | 0 |
| **Peak Hour** | 19:39 — **32,831 requests** (29,429 were 4xx!) |
| **Avg Response Time** | 4ms–260ms |
| **Max Response Time** | **1.23s** (during peak load) |
| **Healthy Hosts** | 2/2 — never dropped |

### Key Concern

The **817 target 5xx errors** are concerning — 656 occurred at 19:39 and 159 at 22:39, both correlating directly with massive scanner traffic spikes. Your instances are buckling under the bot load.

---

## 3. EC2 / Auto Scaling Group

| Metric | Instance 1 (i-065a...) | Instance 2 (i-06da...) |
|--------|----------------------|----------------------|
| **Type** | t3.micro | t3.micro |
| **AZ** | eu-central-1c | eu-central-1b |
| **Running Since** | Jan 25 | Jan 25 |
| **Launch Template** | **v2** | **v2** |
| **CPU Avg** | <1% (peak 12.4%) | <1% (peak 12.7%) |
| **CPU Credits** | 288/288 (maxed) | 288/288 (maxed) |
| **Memory Avg** | ~39% | ~39% |
| **Memory Max** | ~42% | ~40% |
| **TCP Connections Avg** | ~1.4 | ~1.4 |
| **TCP Connections Max** | 16 (during peak) | 16 (during peak) |

| ASG Config | Value |
|------------|-------|
| **Desired/Min/Max** | 2/2/4 |
| **Scaling Events (24h)** | None |
| **Scaling Policy - CPU** | Target 70% (never triggered) |
| **Scaling Policy - Memory** | Target 70% (never triggered) |
| **Launch Template Default** | **v6** (instances running v2!) |

---

## Improvement Recommendations

### CRITICAL Priority

#### 1. Deploy AWS WAF on CloudFront

- Your site is absorbing massive scanner/bot traffic (~34K 4xx hits in 24hrs) that's causing 5xx errors on your servers
- Add managed rule groups: `AWSManagedRulesCommonRuleSet`, `AWSManagedRulesBotControlRuleSet`, `AWSManagedRulesKnownBadInputsRuleSet`
- Add a rate-limiting rule (e.g., 100 requests/5min per IP) to throttle scanners
- **Estimated cost:** ~$6/month base + $1/million requests
- **Impact:** Eliminates 5xx errors from scanner floods, reduces EC2 load

#### 2. Enable CloudFront Caching

- Currently using `CachingDisabled` — every request (including static CSS/JS/images) hits your EC2 instances
- Create cache behaviors:
  - `/wp-content/*`, `/wp-includes/*` → Cache with TTL 86400s (static assets)
  - `/wp-admin/*`, `/wp-login.php` → No cache (admin)
  - Default `/*` → Cache with short TTL (60-300s) or use `CachingOptimized` with appropriate headers
- **Impact:** Dramatic reduction in origin load, faster page loads, lower ALB costs

### HIGH Priority

#### 3. Enable CloudFront Access Logging

- Logging is disabled — you can't analyze traffic patterns, identify attacking IPs, or debug issues
- Enable logging to an S3 bucket for forensic analysis
- Consider also enabling ALB access logs

#### 4. Instance Refresh to Launch Template v6

- Both instances are running **v2** but the latest/default template is **v6**
- v6 includes the CloudWatch aggregation_dimensions fix needed for memory-based scaling to work correctly
- Run: `aws autoscaling start-instance-refresh --auto-scaling-group-name bmi-calculator-asg --region eu-central-1`

### MEDIUM Priority

#### 5. Consider Right-Sizing / Cost Optimization

- Your legitimate traffic is very low (~20-30 requests/hour off-peak)
- CPU is <1% average, memory at 39% steady
- CPU credits are perpetually maxed at 288 (never consumed)
- Options:
  - **Drop to 1 instance** (min=1, desired=1) if you can tolerate brief downtime during AZ issues — saves ~$8/month
  - **Switch to t3.nano** ($3.75/month vs $7.59/month per instance) — your workload would still fit
  - **Consider Spot instances** for the ASG — up to 90% savings for fault-tolerant workloads

#### 6. Enable HTTP/3 (QUIC) on CloudFront

- Currently HTTP/2 only — HTTP/3 provides faster connections especially on mobile/poor networks
- Single toggle in CloudFront settings, no cost

### LOW Priority

#### 7. Add CloudWatch Alarms for 5xx errors

- Set up an alarm on `HTTPCode_Target_5XX_Count > 10` to alert you when servers are struggling

#### 8. Add CloudFront Function for Bot Filtering

- Lightweight edge function to block known bad user-agents before they even reach WAF
- Free tier: 2 million invocations/month

---

## Summary: Top 3 Actions for Biggest Impact

| # | Action | Effort | Impact |
|---|--------|--------|--------|
| 1 | Add AWS WAF with rate limiting | 30 min | Eliminates 5xx errors, blocks ~34K daily scanner hits |
| 2 | Enable CloudFront caching | 30 min | Reduces origin load by 60-80%, faster page loads |
| 3 | Instance refresh to v6 | 5 min | Ensures memory scaling works correctly |
