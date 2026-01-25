# BMI Calculator - AWS Architecture

## High-Level Architecture

```
                                    ┌─────────────────────────────────────────────────────────────┐
                                    │                        INTERNET                             │
                                    └─────────────────────────────────────────────────────────────┘
                                                              │
                                                              ▼
                                    ┌─────────────────────────────────────────────────────────────┐
                                    │                     CLOUDFLARE DNS                          │
                                    │                                                             │
                                    │   aaronzammit.com ──CNAME──▶ d2392ulp4il11k.cloudfront.net │
                                    └─────────────────────────────────────────────────────────────┘
                                                              │
                                                              ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                              AWS CLOUD                                                       │
│                                                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────────────────────────────────────┐  │
│  │                                    CLOUDFRONT (Global Edge Network)                                    │  │
│  │                                                                                                        │  │
│  │   Distribution: E16YYJ4DT46N17                                                                        │  │
│  │   Domain: d2392ulp4il11k.cloudfront.net                                                               │  │
│  │   CNAME: aaronzammit.com                                                                              │  │
│  │                                                                                                        │  │
│  │   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │  │
│  │   │  Edge POP    │  │  Edge POP    │  │  Edge POP    │  │  Edge POP    │  │  Edge POP    │           │  │
│  │   │  (Milan)     │  │  (Frankfurt) │  │  (London)    │  │  (Paris)     │  │  (Amsterdam) │  ...      │  │
│  │   └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘           │  │
│  │                                                                                                        │  │
│  │   SSL: ACM Certificate (arn:aws:acm:us-east-1:...:certificate/df802f98-5faa-4328-a927-571382bd00e5)  │  │
│  │   Protocol: HTTPS (TLSv1.2_2021) → HTTP to origin                                                     │  │
│  │   Cache: Disabled (CachingDisabled policy)                                                            │  │
│  └───────────────────────────────────────────────────────────────────────────────────────────────────────┘  │
│                                                    │                                                         │
│                                                    │ HTTP (Port 80)                                         │
│                                                    ▼                                                         │
│  ┌───────────────────────────────────────────────────────────────────────────────────────────────────────┐  │
│  │                                         EU-CENTRAL-1 (Frankfurt)                                       │  │
│  │                                                                                                        │  │
│  │   ┌─────────────────────────────────────────────────────────────────────────────────────────────┐     │  │
│  │   │                        APPLICATION LOAD BALANCER (bmi-calculator-alb)                        │     │  │
│  │   │                                                                                              │     │  │
│  │   │   DNS: bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com                         │     │  │
│  │   │   Scheme: internet-facing                                                                    │     │  │
│  │   │   Security Group: sg-0d78cc17352915ee6 (Inbound: 80, 443 from 0.0.0.0/0)                    │     │  │
│  │   │                                                                                              │     │  │
│  │   │   ┌────────────────────┐  ┌────────────────────┐  ┌────────────────────┐                    │     │  │
│  │   │   │   eu-central-1a    │  │   eu-central-1b    │  │   eu-central-1c    │                    │     │  │
│  │   │   │   subnet-0c534...  │  │   subnet-0b643...  │  │   subnet-03418...  │                    │     │  │
│  │   │   └────────────────────┘  └────────────────────┘  └────────────────────┘                    │     │  │
│  │   │                                                                                              │     │  │
│  │   │   Listener: HTTP:80 ──▶ Forward to Target Group                                             │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                                    │                                                   │  │
│  │                                                    │ HTTP (Port 80)                                   │  │
│  │                                                    ▼                                                   │  │
│  │   ┌─────────────────────────────────────────────────────────────────────────────────────────────┐     │  │
│  │   │                           TARGET GROUP (bmi-calculator-tg)                                   │     │  │
│  │   │                                                                                              │     │  │
│  │   │   Protocol: HTTP:80                          Targets: 2 instances (Multi-AZ)                │     │  │
│  │   │   Health Check: GET /bmi/ (HTTP 200)                                                        │     │  │
│  │   │   Interval: 10s | Timeout: 5s | Healthy: 2 | Unhealthy: 2                                   │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                           │                 │                                          │  │
│  │                              ┌────────────┘                 └────────────┐                             │  │
│  │                              ▼                                           ▼                             │  │
│  │   ┌─────────────────────────────────────────────────────────────────────────────────────────────┐     │  │
│  │   │                                      VPC (vpc-04c3e303f1975de6c)                             │     │  │
│  │   │                                                                                              │     │  │
│  │   │  ┌──────────────────────────────────────┐   ┌──────────────────────────────────────┐        │     │  │
│  │   │  │   SUBNET: eu-central-1a              │   │   SUBNET: eu-central-1b              │        │     │  │
│  │   │  │   172.31.16.0/20                     │   │   172.31.32.0/20                     │        │     │  │
│  │   │  │                                      │   │                                      │        │     │  │
│  │   │  │  ┌────────────────────────────────┐  │   │  ┌────────────────────────────────┐  │        │     │  │
│  │   │  │  │  EC2: bmi-calculator-2         │  │   │  │  EC2: bmi-calculator           │  │        │     │  │
│  │   │  │  │  i-09a84abf4fb782f8e           │  │   │  │  i-02c7b760e6ac58028           │  │        │     │  │
│  │   │  │  │                                │  │   │  │                                │  │        │     │  │
│  │   │  │  │  Type: t3.micro                │  │   │  │  Type: t3.micro                │  │        │     │  │
│  │   │  │  │  Private: 172.31.31.207        │  │   │  │  Private: 172.31.47.199        │  │        │     │  │
│  │   │  │  │  Status: ✅ Healthy            │  │   │  │  Status: ✅ Healthy            │  │        │     │  │
│  │   │  │  │                                │  │   │  │                                │  │        │     │  │
│  │   │  │  │  ┌──────────────────────────┐  │  │   │  │  ┌──────────────────────────┐  │  │        │     │  │
│  │   │  │  │  │ Apache 2.4 → PHP → SQLite│  │  │   │  │  │ Apache 2.4 → PHP → SQLite│  │  │        │     │  │
│  │   │  │  │  └──────────────────────────┘  │  │   │  │  └──────────────────────────┘  │  │        │     │  │
│  │   │  │  │                                │  │   │  │                                │  │        │     │  │
│  │   │  │  │  EBS: gp3, 8GB                 │  │   │  │  EBS: gp3, 8GB                 │  │        │     │  │
│  │   │  │  └────────────────────────────────┘  │   │  └────────────────────────────────┘  │        │     │  │
│  │   │  │                                      │   │                                      │        │     │  │
│  │   │  └──────────────────────────────────────┘   └──────────────────────────────────────┘        │     │  │
│  │   │                                                                                              │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                                                                                        │  │
│  └───────────────────────────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                                              │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## Request Flow (with Load Balancing)

```
┌──────────┐     ┌───────────┐     ┌────────────┐     ┌─────┐     ┌──────────────┐
│  User    │────▶│ Cloudflare│────▶│ CloudFront │────▶│ ALB │────▶│ Target Group │
│ Browser  │     │    DNS    │     │    CDN     │     │     │     │  (2 targets) │
└──────────┘     └───────────┘     └────────────┘     └─────┘     └──────────────┘
                                                                    │           │
                                                         ┌──────────┘           └──────────┐
                                                         ▼                                  ▼
                                                  ┌─────────────┐                   ┌─────────────┐
                                                  │    EC2 #1   │                   │    EC2 #2   │
                                                  │ eu-cent-1a  │                   │ eu-cent-1b  │
                                                  │  (Active)   │                   │  (Active)   │
                                                  └─────────────┘                   └─────────────┘
```

## Health Check Configuration

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              ALB HEALTH CHECK                                    │
│                                                                                  │
│   ┌─────────────────────────────────────────────────────────────────────────┐   │
│   │                                                                          │   │
│   │   Protocol:        HTTP                                                  │   │
│   │   Path:            /bmi/                                                 │   │
│   │   Port:            80 (traffic-port)                                     │   │
│   │   Expected:        HTTP 200                                              │   │
│   │                                                                          │   │
│   │   ┌─────────────────────────────────────────────────────────────────┐   │   │
│   │   │  TIMING                                                          │   │   │
│   │   │                                                                  │   │   │
│   │   │  Interval:     Every 10 seconds                                  │   │   │
│   │   │  Timeout:      5 seconds                                         │   │   │
│   │   │                                                                  │   │   │
│   │   │  Healthy after:    2 consecutive successes (20 seconds)         │   │   │
│   │   │  Unhealthy after:  2 consecutive failures (20 seconds)          │   │   │
│   │   └─────────────────────────────────────────────────────────────────┘   │   │
│   │                                                                          │   │
│   │   ┌─────────────────────────────────────────────────────────────────┐   │   │
│   │   │  FAILOVER BEHAVIOR                                               │   │   │
│   │   │                                                                  │   │   │
│   │   │  If EC2 #1 fails:                                                │   │   │
│   │   │    → Detected in ~20 seconds                                     │   │   │
│   │   │    → Traffic routed 100% to EC2 #2                              │   │   │
│   │   │    → Zero downtime for users                                     │   │   │
│   │   │                                                                  │   │   │
│   │   │  If both fail:                                                   │   │   │
│   │   │    → ALB returns HTTP 503                                        │   │   │
│   │   └─────────────────────────────────────────────────────────────────┘   │   │
│   │                                                                          │   │
│   └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## Security Groups

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                SECURITY GROUP FLOW                                   │
│                                                                                      │
│   INTERNET                                                                           │
│       │                                                                              │
│       ▼                                                                              │
│   ┌───────────────────────────────────────────────────────────────────┐             │
│   │              ALB Security Group (sg-0d78cc17352915ee6)            │             │
│   │                                                                    │             │
│   │   INBOUND:                          OUTBOUND:                      │             │
│   │   ┌──────────────────────┐          ┌──────────────────────┐      │             │
│   │   │ TCP 80  │ 0.0.0.0/0  │          │ All     │ 0.0.0.0/0  │      │             │
│   │   │ TCP 443 │ 0.0.0.0/0  │          │         │            │      │             │
│   │   └──────────────────────┘          └──────────────────────┘      │             │
│   └───────────────────────────────────────────────────────────────────┘             │
│       │                                                                              │
│       │ Only from ALB SG                                                            │
│       ▼                                                                              │
│   ┌───────────────────────────────────────────────────────────────────┐             │
│   │              EC2 Security Group (sg-0415fab6c3b564196)            │             │
│   │              (Applied to both instances)                           │             │
│   │                                                                    │             │
│   │   INBOUND:                          OUTBOUND:                      │             │
│   │   ┌──────────────────────────────┐  ┌──────────────────────┐      │             │
│   │   │ TCP 80  │ sg-0d78cc17352915ee6│  │ All     │ 0.0.0.0/0  │      │             │
│   │   │ TCP 22  │ 0.0.0.0/0 ⚠️        │  │         │            │      │             │
│   │   │ TCP 443 │ 0.0.0.0/0           │  └──────────────────────┘      │             │
│   │   └──────────────────────────────┘                                 │             │
│   └───────────────────────────────────────────────────────────────────┘             │
│                                                                                      │
│   ⚠️  SSH open to world - Recommend restricting to specific IP                      │
│                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

## Component Details

| Component | Identifier | Key Configuration |
|-----------|------------|-------------------|
| CloudFront | E16YYJ4DT46N17 | HTTPS termination, TLSv1.2, redirect HTTP→HTTPS |
| ACM Certificate | df802f98-5faa-4328-a927-571382bd00e5 | aaronzammit.com, *.aaronzammit.com |
| ALB | bmi-calculator-alb | 3 AZs, HTTP listener on port 80 |
| Target Group | bmi-calculator-tg | HTTP:80, health check /bmi/, 2 targets |
| EC2 #1 | i-02c7b760e6ac58028 | t3.micro, eu-central-1b, bmi-calculator |
| EC2 #2 | i-09a84abf4fb782f8e | t3.micro, eu-central-1a, bmi-calculator-2 |
| AMI | ami-04f362ea12d5ad433 | Custom AMI with Apache+PHP+App |

## EC2 Instances

| Name | Instance ID | AZ | Private IP | Status |
|------|-------------|-----|------------|--------|
| bmi-calculator | i-02c7b760e6ac58028 | eu-central-1b | 172.31.47.199 | ✅ Healthy |
| bmi-calculator-2 | i-09a84abf4fb782f8e | eu-central-1a | 172.31.31.207 | ✅ Healthy |

## Health Check Settings

| Setting | Value | Description |
|---------|-------|-------------|
| Protocol | HTTP | Health check protocol |
| Path | /bmi/ | Endpoint to check |
| Port | 80 | Port to check |
| Interval | 10s | Time between checks |
| Timeout | 5s | Time to wait for response |
| Healthy Threshold | 2 | Consecutive successes to mark healthy |
| Unhealthy Threshold | 2 | Consecutive failures to mark unhealthy |
| Success Code | 200 | Expected HTTP response code |

## Resiliency Features

| Feature | Status | Description |
|---------|--------|-------------|
| Multi-AZ Deployment | ✅ | Instances in eu-central-1a and eu-central-1b |
| Load Balancing | ✅ | ALB distributes traffic across instances |
| Health Checks | ✅ | Automatic detection of failed instances (20s) |
| Automatic Failover | ✅ | Traffic rerouted to healthy instances |
| Cross-Zone LB | ✅ | ALB can route to any AZ |

## Network Details

| Resource | CIDR/IP | Notes |
|----------|---------|-------|
| VPC | vpc-04c3e303f1975de6c | Default VPC |
| Subnet 1a | 172.31.16.0/20 | eu-central-1a (EC2 #2) |
| Subnet 1b | 172.31.32.0/20 | eu-central-1b (EC2 #1) |
| Subnet 1c | 172.31.0.0/20 | eu-central-1c (available) |
