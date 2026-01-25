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
│  │   │   Protocol: HTTP:80                          Targets: Auto Scaling Group (2-4 instances)   │     │  │
│  │   │   Health Check: GET /bmi/ (HTTP 200)                                                        │     │  │
│  │   │   Interval: 10s | Timeout: 5s | Healthy: 2 | Unhealthy: 2                                   │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                                    │                                                   │  │
│  │                                                    ▼                                                   │  │
│  │   ┌─────────────────────────────────────────────────────────────────────────────────────────────┐     │  │
│  │   │                        AUTO SCALING GROUP (bmi-calculator-asg)                               │     │  │
│  │   │                                                                                              │     │  │
│  │   │   Min: 2 | Desired: 2 | Max: 4                                                              │     │  │
│  │   │   Launch Template: lt-00e9af6830a1fb15c (v2)                                                │     │  │
│  │   │   Health Check: ELB | Grace Period: 120s                                                    │     │  │
│  │   │                                                                                              │     │  │
│  │   │   Scaling Policies:                                                                          │     │  │
│  │   │   ┌─────────────────────────────────────────────────────────────────────────────────────┐   │     │  │
│  │   │   │  📈 CPU Target Tracking    │ Scale when CPU > 70%                                   │   │     │  │
│  │   │   │  📈 Memory Target Tracking │ Scale when Memory > 70%                                │   │     │  │
│  │   │   └─────────────────────────────────────────────────────────────────────────────────────┘   │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                           │                 │                                          │  │
│  │                              ┌────────────┘                 └────────────┐                             │  │
│  │                              ▼                                           ▼                             │  │
│  │   ┌─────────────────────────────────────────────────────────────────────────────────────────────┐     │  │
│  │   │                                      VPC (vpc-04c3e303f1975de6c)                             │     │  │
│  │   │                                                                                              │     │  │
│  │   │  ┌───────────────────────────────────┐  ┌───────────────────────────────────┐               │     │  │
│  │   │  │   SUBNET: eu-central-1a/1b/1c     │  │   SUBNET: eu-central-1a/1b/1c     │               │     │  │
│  │   │  │                                   │  │                                   │               │     │  │
│  │   │  │  ┌─────────────────────────────┐  │  │  ┌─────────────────────────────┐  │  ... (up to 4)│     │  │
│  │   │  │  │  EC2 (Auto Scaled)          │  │  │  │  EC2 (Auto Scaled)          │  │               │     │  │
│  │   │  │  │  t3.micro                   │  │  │  │  t3.micro                   │  │               │     │  │
│  │   │  │  │                             │  │  │  │                             │  │               │     │  │
│  │   │  │  │  ┌───────────────────────┐  │  │  │  │  ┌───────────────────────┐  │  │               │     │  │
│  │   │  │  │  │ Apache → PHP → SQLite │  │  │  │  │  │ Apache → PHP → SQLite │  │  │               │     │  │
│  │   │  │  │  └───────────────────────┘  │  │  │  │  └───────────────────────┘  │  │               │     │  │
│  │   │  │  │  ┌───────────────────────┐  │  │  │  │  ┌───────────────────────┐  │  │               │     │  │
│  │   │  │  │  │ CloudWatch Agent      │  │  │  │  │  │ CloudWatch Agent      │  │  │               │     │  │
│  │   │  │  │  │ (Memory Metrics)      │  │  │  │  │  │ (Memory Metrics)      │  │  │               │     │  │
│  │   │  │  │  └───────────────────────┘  │  │  │  │  └───────────────────────┘  │  │               │     │  │
│  │   │  │  │                             │  │  │  │                             │  │               │     │  │
│  │   │  │  │  EBS: gp3, 8GB              │  │  │  │  EBS: gp3, 8GB              │  │               │     │  │
│  │   │  │  └─────────────────────────────┘  │  │  └─────────────────────────────┘  │               │     │  │
│  │   │  │                                   │  │                                   │               │     │  │
│  │   │  └───────────────────────────────────┘  └───────────────────────────────────┘               │     │  │
│  │   │                                                                                              │     │  │
│  │   └─────────────────────────────────────────────────────────────────────────────────────────────┘     │  │
│  │                                                                                                        │  │
│  └───────────────────────────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                                              │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## Auto Scaling Configuration

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                     AUTO SCALING GROUP                                               │
│                                                                                                      │
│   ┌───────────────────────────────────────────────────────────────────────────────────────────┐     │
│   │                              CAPACITY SETTINGS                                             │     │
│   │                                                                                            │     │
│   │   Minimum:     2 instances (always running for high availability)                         │     │
│   │   Desired:     2 instances (normal operation)                                             │     │
│   │   Maximum:     4 instances (peak load capacity)                                           │     │
│   │                                                                                            │     │
│   │   ┌────────────────────────────────────────────────────────────────────────────────────┐  │     │
│   │   │                         SCALING POLICIES                                           │  │     │
│   │   │                                                                                    │  │     │
│   │   │   ┌──────────────────────────────────────────────────────────────────────────┐    │  │     │
│   │   │   │  CPU-BASED SCALING (Target Tracking)                                     │    │  │     │
│   │   │   │                                                                          │    │  │     │
│   │   │   │  Metric:     ASGAverageCPUUtilization                                   │    │  │     │
│   │   │   │  Target:     70%                                                         │    │  │     │
│   │   │   │                                                                          │    │  │     │
│   │   │   │  Behavior:                                                               │    │  │     │
│   │   │   │    • CPU < 70% → Scale in (remove instances)                            │    │  │     │
│   │   │   │    • CPU > 70% → Scale out (add instances)                              │    │  │     │
│   │   │   └──────────────────────────────────────────────────────────────────────────┘    │  │     │
│   │   │                                                                                    │  │     │
│   │   │   ┌──────────────────────────────────────────────────────────────────────────┐    │  │     │
│   │   │   │  MEMORY-BASED SCALING (Target Tracking)                                  │    │  │     │
│   │   │   │                                                                          │    │  │     │
│   │   │   │  Metric:     mem_used_percent (Custom - BMICalculator namespace)        │    │  │     │
│   │   │   │  Target:     70%                                                         │    │  │     │
│   │   │   │                                                                          │    │  │     │
│   │   │   │  Behavior:                                                               │    │  │     │
│   │   │   │    • Memory < 70% → Scale in (remove instances)                         │    │  │     │
│   │   │   │    • Memory > 70% → Scale out (add instances)                           │    │  │     │
│   │   │   │                                                                          │    │  │     │
│   │   │   │  Note: Requires CloudWatch Agent on instances                           │    │  │     │
│   │   │   └──────────────────────────────────────────────────────────────────────────┘    │  │     │
│   │   └────────────────────────────────────────────────────────────────────────────────────┘  │     │
│   │                                                                                            │     │
│   └───────────────────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                                      │
│   ┌───────────────────────────────────────────────────────────────────────────────────────────┐     │
│   │                              LAUNCH TEMPLATE (lt-00e9af6830a1fb15c v2)                     │     │
│   │                                                                                            │     │
│   │   AMI:              ami-04f362ea12d5ad433 (Custom with Apache+PHP+App)                    │     │
│   │   Instance Type:    t3.micro                                                               │     │
│   │   Key Pair:         bmi-calculator-key                                                     │     │
│   │   Security Group:   sg-0415fab6c3b564196                                                  │     │
│   │   IAM Profile:      CloudWatchAgentProfile                                                 │     │
│   │   Monitoring:       Detailed (1-minute metrics)                                            │     │
│   │   User Data:        Installs & configures CloudWatch Agent                                 │     │
│   └───────────────────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## Scaling Scenarios

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                     SCALING SCENARIOS                                                │
│                                                                                                      │
│  SCENARIO 1: Normal Traffic                                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐   │
│  │  CPU: ~30%  Memory: ~40%  →  2 instances running (minimum)                                  │   │
│  │  ┌─────┐  ┌─────┐                                                                           │   │
│  │  │ EC2 │  │ EC2 │                                                                           │   │
│  │  └─────┘  └─────┘                                                                           │   │
│  └─────────────────────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                                      │
│  SCENARIO 2: Moderate Load (CPU or Memory > 70%)                                                    │
│  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐   │
│  │  CPU: ~75%  Memory: ~50%  →  3 instances (scaled out +1)                                    │   │
│  │  ┌─────┐  ┌─────┐  ┌─────┐                                                                  │   │
│  │  │ EC2 │  │ EC2 │  │ EC2 │ ← New instance launched                                          │   │
│  │  └─────┘  └─────┘  └─────┘                                                                  │   │
│  └─────────────────────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                                      │
│  SCENARIO 3: High Load (sustained high CPU/Memory)                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐   │
│  │  CPU: ~85%  Memory: ~80%  →  4 instances (maximum capacity)                                 │   │
│  │  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐                                                         │   │
│  │  │ EC2 │  │ EC2 │  │ EC2 │  │ EC2 │                                                         │   │
│  │  └─────┘  └─────┘  └─────┘  └─────┘                                                         │   │
│  └─────────────────────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                                      │
│  SCENARIO 4: Instance Failure                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐   │
│  │  EC2 #1 fails health check  →  Replaced automatically                                       │   │
│  │  ┌─────┐  ┌─────┐              ┌─────┐  ┌─────┐                                             │   │
│  │  │ ❌  │  │ EC2 │     →        │ EC2 │  │ EC2 │ ← New replacement                           │   │
│  │  └─────┘  └─────┘              └─────┘  └─────┘                                             │   │
│  └─────────────────────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## Health Check Configuration

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              ALB HEALTH CHECK                                    │
│                                                                                  │
│   Protocol:        HTTP                                                          │
│   Path:            /bmi/                                                         │
│   Port:            80 (traffic-port)                                             │
│   Expected:        HTTP 200                                                      │
│                                                                                  │
│   Interval:        10 seconds                                                    │
│   Timeout:         5 seconds                                                     │
│   Healthy after:   2 consecutive successes (20 seconds)                         │
│   Unhealthy after: 2 consecutive failures (20 seconds)                          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## CloudWatch Metrics

| Metric | Namespace | Source | Used For |
|--------|-----------|--------|----------|
| CPUUtilization | AWS/EC2 | EC2 native | CPU scaling policy |
| mem_used_percent | BMICalculator | CloudWatch Agent | Memory scaling policy |
| HealthyHostCount | AWS/ApplicationELB | ALB | Monitoring |
| UnHealthyHostCount | AWS/ApplicationELB | ALB | Alerting |
| RequestCount | AWS/ApplicationELB | ALB | Traffic analysis |

## Component Details

| Component | Identifier | Key Configuration |
|-----------|------------|-------------------|
| CloudFront | E16YYJ4DT46N17 | HTTPS termination, TLSv1.2, redirect HTTP→HTTPS |
| ACM Certificate | df802f98-5faa-4328-a927-571382bd00e5 | aaronzammit.com, *.aaronzammit.com |
| ALB | bmi-calculator-alb | 3 AZs, HTTP listener on port 80 |
| Target Group | bmi-calculator-tg | HTTP:80, health check /bmi/, ELB health |
| Auto Scaling Group | bmi-calculator-asg | Min: 2, Max: 4, CPU+Memory scaling |
| Launch Template | lt-00e9af6830a1fb15c v3 | t3.micro, CloudWatch Agent, detailed monitoring |
| AMI | ami-08cd8dc3fa78b088d | Custom AMI with Apache+PHP+App+GA |
| IAM Role | CloudWatchAgentRole | CloudWatch Agent + SSM permissions |
| Google Analytics | G-3B3EDTZ0JT | Traffic analytics and audience insights |

## Resiliency Features

| Feature | Status | Description |
|---------|--------|-------------|
| Multi-AZ Deployment | ✅ | Instances distributed across 3 AZs |
| Load Balancing | ✅ | ALB distributes traffic across instances |
| Health Checks | ✅ | Automatic detection of failed instances (20s) |
| Automatic Failover | ✅ | Traffic rerouted to healthy instances |
| Auto Scaling | ✅ | Scales 2-4 instances based on CPU/Memory |
| Self-Healing | ✅ | Failed instances automatically replaced |
| Cross-Zone LB | ✅ | ALB can route to any AZ |
| Detailed Monitoring | ✅ | 1-minute CloudWatch metrics |

## Network Details

| Resource | CIDR/IP | Notes |
|----------|---------|-------|
| VPC | vpc-04c3e303f1975de6c | Default VPC |
| Subnet 1a | 172.31.16.0/20 | eu-central-1a |
| Subnet 1b | 172.31.32.0/20 | eu-central-1b |
| Subnet 1c | 172.31.0.0/20 | eu-central-1c |

## Google Analytics

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              GOOGLE ANALYTICS 4                                  │
│                                                                                  │
│   Measurement ID:  G-3B3EDTZ0JT                                                 │
│   Property:        BMI Calculator                                                │
│   Data Stream:     Web (https://aaronzammit.com)                                │
│                                                                                  │
│   ┌─────────────────────────────────────────────────────────────────────────┐   │
│   │                         TRACKED METRICS                                  │   │
│   │                                                                          │   │
│   │   👥 AUDIENCE                    📊 BEHAVIOR                            │   │
│   │   • Demographics (age, gender)   • Page views                           │   │
│   │   • Location (country, city)     • Session duration                     │   │
│   │   • Language                     • Bounce rate                          │   │
│   │   • Device type                  • Pages per session                    │   │
│   │   • Browser/OS                   • User engagement                      │   │
│   │                                                                          │   │
│   │   🔗 ACQUISITION                 ⏱️ REAL-TIME                           │   │
│   │   • Traffic sources              • Active users now                     │   │
│   │   • Referrals                    • Current page views                   │   │
│   │   • Direct/Organic/Social        • Live events                          │   │
│   │   • Campaign tracking            • User locations                       │   │
│   └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│   Dashboard: https://analytics.google.com/                                      │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## Cost Estimate (Monthly)

| Resource | Cost |
|----------|------|
| EC2 (2x t3.micro) | ~$15.20 |
| EC2 (scaling to 4x) | ~$30.40 |
| ALB | ~$16 + data |
| CloudFront | Free tier (1TB) |
| CloudWatch | ~$3 |
| Google Analytics | Free |
| **Total (baseline)** | **~$35** |
