# AWS Infrastructure - BMI Calculator

## Architecture

```
User → CloudFront (CDN/HTTPS) → ALB → Auto Scaling Group (2-4 t3.micro)
              ↓                              ↓
        aaronzammit.com              Google Analytics (G-3B3EDTZ0JT)
```

## Resources

### Auto Scaling Group
- **Name:** bmi-calculator-asg
- **Min/Desired/Max:** 2 / 2 / 4
- **Launch Template:** lt-00e9af6830a1fb15c (v6)
- **AMI:** ami-0b5a0f88c6904cf64
- **Instance Type:** t3.micro
- **Availability Zones:** eu-central-1a, eu-central-1b, eu-central-1c
- **Scaling Policies:** CPU > 70%, Memory > 70%
- **Health Check:** ELB, 120s grace period

### Launch Template
- **ID:** lt-00e9af6830a1fb15c
- **Version:** 6 (CloudWatch Agent aggregation_dimensions fix)
- **AMI:** ami-0b5a0f88c6904cf64
- **IAM Profile:** CloudWatchAgentProfile
- **Monitoring:** Detailed (1-minute)
- **User Data:** CloudWatch Agent installation with full metrics and aggregation

### Google Analytics
- **Measurement ID:** G-3B3EDTZ0JT
- **Property:** BMI Calculator
- **Tracking:** Page views, demographics, traffic sources
- **Dashboard:** https://analytics.google.com/

### WordPress Configuration
- **Site URL:** https://aaronzammit.com
- **Home URL:** https://aaronzammit.com
- **Admin URL:** https://aaronzammit.com/wp-admin/
- **Database:** MariaDB (wordpress)
- **Theme:** Twenty Twenty-Five
- **HTTPS Detection:** Via `X-CloudFront-Forwarded-Proto` header in wp-config.php

### ACM Certificate (us-east-1)
- **ARN:** `arn:aws:acm:us-east-1:359345324847:certificate/df802f98-5faa-4328-a927-571382bd00e5`
- **Domain:** aaronzammit.com, *.aaronzammit.com
- **Validation:** DNS (CNAME record in Cloudflare)

### Application Load Balancer (eu-central-1)
- **Name:** bmi-calculator-alb
- **ARN:** `arn:aws:elasticloadbalancing:eu-central-1:359345324847:loadbalancer/app/bmi-calculator-alb/3f95ab9e1d02f57f`
- **DNS:** `bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com`
- **Security Group:** `sg-0d78cc17352915ee6` (bmi-calculator-alb-sg)
- **Subnets:** subnet-0c5344028089efd43, subnet-0b64315e299a84d68, subnet-03418c30648f106f2

### Target Group
- **Name:** bmi-calculator-tg
- **ARN:** `arn:aws:elasticloadbalancing:eu-central-1:359345324847:targetgroup/bmi-calculator-tg/cfa5023400e7c504`
- **Health Check Path:** /bmi/
- **Protocol:** HTTP:80

### CloudFront Distribution
- **Distribution ID:** `E16YYJ4DT46N17`
- **Domain:** `d2392ulp4il11k.cloudfront.net`
- **Alternate Domain:** aaronzammit.com
- **Origin:** bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com
- **Origin Protocol Policy:** HTTP-only
- **Custom Origin Header:** `X-CloudFront-Forwarded-Proto: https`
- **Cache Policy:** CachingDisabled (4135ea2d-6df8-44a3-9df3-4b5a84be39ad)
- **Origin Request Policy:** AllViewer (216adef6-5c7f-47e4-b989-5492eafa07d3)
- **SSL:** TLSv1.2_2021, SNI

## CloudWatch Agent Monitoring

### Configuration
- **Namespace:** BMICalculator
- **Collection Interval:** 60 seconds
- **Agent Version:** 1.300062.1
- **Config Location:** `/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json`

### Metrics Collected

| Category | Metrics | Description |
|----------|---------|-------------|
| **Memory** | `mem_used_percent`, `mem_available`, `mem_used`, `mem_total` | Memory utilization |
| **Disk** | `disk_used_percent`, `disk_used`, `disk_total` | Root filesystem usage |
| **CPU** | `cpu_usage_idle`, `cpu_usage_user`, `cpu_usage_system` | CPU utilization breakdown |
| **Network** | `netstat_tcp_established`, `netstat_tcp_time_wait` | TCP connection states |

### Dimensions
All metrics include these dimensions for filtering:
- `InstanceId` - EC2 instance ID
- `AutoScalingGroupName` - ASG name (bmi-calculator-asg)
- Disk metrics also include: `path`, `device`, `fstype`

### Aggregation Dimensions
Metrics are also published aggregated by `AutoScalingGroupName` only (without `InstanceId`). This is required for the ASG target tracking scaling policies, which query metrics at the ASG level rather than per-instance.

### Agent Configuration File
```json
{
  "agent": {
    "metrics_collection_interval": 60,
    "run_as_user": "root"
  },
  "metrics": {
    "namespace": "BMICalculator",
    "metrics_collected": {
      "mem": {
        "measurement": ["mem_used_percent", "mem_available", "mem_used", "mem_total"],
        "metrics_collection_interval": 60
      },
      "disk": {
        "measurement": ["used_percent", "used", "total"],
        "metrics_collection_interval": 60,
        "resources": ["/"]
      },
      "cpu": {
        "measurement": ["cpu_usage_idle", "cpu_usage_user", "cpu_usage_system"],
        "metrics_collection_interval": 60,
        "totalcpu": true
      },
      "netstat": {
        "measurement": ["tcp_established", "tcp_time_wait"],
        "metrics_collection_interval": 60
      }
    },
    "append_dimensions": {
      "InstanceId": "${aws:InstanceId}",
      "AutoScalingGroupName": "${aws:AutoScalingGroupName}"
    },
    "aggregation_dimensions": [["AutoScalingGroupName"]]
  }
}
```

### Installation Commands

```bash
# Install CloudWatch Agent via SSM
aws ssm send-command \
  --instance-ids i-065a560e87bfe8ab6 i-06da8757b133a9679 \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=["sudo yum install -y amazon-cloudwatch-agent"]'

# Write config file (base64 encoded to handle JSON)
CONFIG_B64="ewogICJhZ2VudCI6IHsKICAgICJtZXRyaWNzX2NvbGxlY3Rpb25faW50ZXJ2YWwiOiA2MCwKICAgICJydW5fYXNfdXNlciI6ICJyb290IgogIH0sCiAgIm1ldHJpY3MiOiB7CiAgICAibmFtZXNwYWNlIjogIkJNSUNhbGN1bGF0b3IiLAogICAgIm1ldHJpY3NfY29sbGVjdGVkIjogewogICAgICAibWVtIjogewogICAgICAgICJtZWFzdXJlbWVudCI6IFsibWVtX3VzZWRfcGVyY2VudCIsICJtZW1fYXZhaWxhYmxlIiwgIm1lbV91c2VkIiwgIm1lbV90b3RhbCJdLAogICAgICAgICJtZXRyaWNzX2NvbGxlY3Rpb25faW50ZXJ2YWwiOiA2MAogICAgICB9LAogICAgICAiZGlzayI6IHsKICAgICAgICAibWVhc3VyZW1lbnQiOiBbInVzZWRfcGVyY2VudCIsICJ1c2VkIiwgInRvdGFsIl0sCiAgICAgICAgIm1ldHJpY3NfY29sbGVjdGlvbl9pbnRlcnZhbCI6IDYwLAogICAgICAgICJyZXNvdXJjZXMiOiBbIi8iXQogICAgICB9LAogICAgICAiY3B1IjogewogICAgICAgICJtZWFzdXJlbWVudCI6IFsiY3B1X3VzYWdlX2lkbGUiLCAiY3B1X3VzYWdlX3VzZXIiLCAiY3B1X3VzYWdlX3N5c3RlbSJdLAogICAgICAgICJtZXRyaWNzX2NvbGxlY3Rpb25faW50ZXJ2YWwiOiA2MCwKICAgICAgICAidG90YWxjcHUiOiB0cnVlCiAgICAgIH0sCiAgICAgICJuZXRzdGF0IjogewogICAgICAgICJtZWFzdXJlbWVudCI6IFsidGNwX2VzdGFibGlzaGVkIiwgInRjcF90aW1lX3dhaXQiXSwKICAgICAgICAibWV0cmljc19jb2xsZWN0aW9uX2ludGVydmFsIjogNjAKICAgICAgfQogICAgfSwKICAgICJhcHBlbmRfZGltZW5zaW9ucyI6IHsKICAgICAgIkluc3RhbmNlSWQiOiAiJHthd3M6SW5zdGFuY2VJZH0iLAogICAgICAiQXV0b1NjYWxpbmdHcm91cE5hbWUiOiAiJHthd3M6QXV0b1NjYWxpbmdHcm91cE5hbWV9IgogICAgfQogIH0KfQo="

aws ssm send-command \
  --instance-ids i-065a560e87bfe8ab6 i-06da8757b133a9679 \
  --document-name "AWS-RunShellScript" \
  --parameters "commands=[\"echo $CONFIG_B64 | base64 -d > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json\"]"

# Start the agent
aws ssm send-command \
  --instance-ids i-065a560e87bfe8ab6 i-06da8757b133a9679 \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=["sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl -a fetch-config -m ec2 -s -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json"]'
```

### Verification Commands

```bash
# Check agent status on instances
aws ssm send-command \
  --instance-ids i-065a560e87bfe8ab6 i-06da8757b133a9679 \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=["sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl -a status"]'

# List available metrics
aws cloudwatch list-metrics --namespace BMICalculator --query 'Metrics[*].MetricName' --output text

# Get memory usage (last hour)
aws cloudwatch get-metric-statistics \
  --namespace BMICalculator \
  --metric-name mem_used_percent \
  --dimensions Name=InstanceId,Value=i-065a560e87bfe8ab6 Name=AutoScalingGroupName,Value=bmi-calculator-asg \
  --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 300 \
  --statistics Average

# Get disk usage
aws cloudwatch get-metric-statistics \
  --namespace BMICalculator \
  --metric-name disk_used_percent \
  --dimensions Name=InstanceId,Value=i-065a560e87bfe8ab6 Name=AutoScalingGroupName,Value=bmi-calculator-asg Name=path,Value=/ Name=device,Value=nvme0n1p1 Name=fstype,Value=xfs \
  --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 300 \
  --statistics Average
```

### Typical Resource Usage (Baseline)
| Metric | Instance 1 | Instance 2 |
|--------|-----------|-----------|
| Memory | ~39-45% | ~39-42% |
| Disk | ~29% | ~29% |
| CPU | <1% idle | <1% idle |

## Security Groups

### ALB Security Group (sg-0d78cc17352915ee6)
| Direction | Protocol | Port | Source |
|-----------|----------|------|--------|
| Inbound | TCP | 80 | 0.0.0.0/0 |
| Inbound | TCP | 443 | 0.0.0.0/0 |

### EC2 Security Group (sg-0415fab6c3b564196)
| Direction | Protocol | Port | Source |
|-----------|----------|------|--------|
| Inbound | TCP | 80 | sg-0d78cc17352915ee6 (ALB) |
| Inbound | TCP | 22 | Your IP |

## DNS Configuration (Cloudflare)

### aaronzammit.com
- **Type:** CNAME
- **Target:** d2392ulp4il11k.cloudfront.net
- **Proxy:** OFF (DNS only)

### ACM Validation Record
- **Name:** _285e5820b20d770ed626f69b8a503121
- **Type:** CNAME
- **Value:** _c0d302850a9da7a6e910cd64c752a869.jkddzztszm.acm-validations.aws

## Setup Commands

### 1. Request ACM Certificate (us-east-1)
```bash
aws acm request-certificate \
  --domain-name aaronzammit.com \
  --subject-alternative-names "*.aaronzammit.com" \
  --validation-method DNS \
  --region us-east-1
```

### 2. Create ALB Security Group
```bash
aws ec2 create-security-group \
  --group-name bmi-calculator-alb-sg \
  --description "Security group for BMI Calculator ALB" \
  --vpc-id vpc-04c3e303f1975de6c \
  --region eu-central-1

aws ec2 authorize-security-group-ingress \
  --group-id sg-0d78cc17352915ee6 \
  --protocol tcp --port 80 --cidr 0.0.0.0/0 \
  --region eu-central-1

aws ec2 authorize-security-group-ingress \
  --group-id sg-0d78cc17352915ee6 \
  --protocol tcp --port 443 --cidr 0.0.0.0/0 \
  --region eu-central-1
```

### 3. Create Target Group
```bash
aws elbv2 create-target-group \
  --name bmi-calculator-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id vpc-04c3e303f1975de6c \
  --target-type instance \
  --health-check-path /bmi/ \
  --region eu-central-1

aws elbv2 register-targets \
  --target-group-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:targetgroup/bmi-calculator-tg/cfa5023400e7c504 \
  --targets Id=i-02c7b760e6ac58028 \
  --region eu-central-1
```

### 4. Create Application Load Balancer
```bash
aws elbv2 create-load-balancer \
  --name bmi-calculator-alb \
  --subnets subnet-0c5344028089efd43 subnet-0b64315e299a84d68 subnet-03418c30648f106f2 \
  --security-groups sg-0d78cc17352915ee6 \
  --region eu-central-1

aws elbv2 create-listener \
  --load-balancer-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:loadbalancer/app/bmi-calculator-alb/3f95ab9e1d02f57f \
  --protocol HTTP \
  --port 80 \
  --default-actions Type=forward,TargetGroupArn=arn:aws:elasticloadbalancing:eu-central-1:359345324847:targetgroup/bmi-calculator-tg/cfa5023400e7c504 \
  --region eu-central-1
```

### 5. Update EC2 Security Group
```bash
aws ec2 authorize-security-group-ingress \
  --group-id sg-0415fab6c3b564196 \
  --protocol tcp \
  --port 80 \
  --source-group sg-0d78cc17352915ee6 \
  --region eu-central-1
```

### 6. Create CloudFront Distribution
```bash
aws cloudfront create-distribution \
  --distribution-config '{
    "CallerReference": "bmi-calculator-2026-01-25",
    "Aliases": {"Quantity": 1, "Items": ["aaronzammit.com"]},
    "Origins": {
      "Quantity": 1,
      "Items": [{
        "Id": "bmi-calculator-alb",
        "DomainName": "bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com",
        "CustomOriginConfig": {
          "HTTPPort": 80,
          "HTTPSPort": 443,
          "OriginProtocolPolicy": "http-only",
          "OriginSslProtocols": {"Quantity": 1, "Items": ["TLSv1.2"]}
        }
      }]
    },
    "DefaultCacheBehavior": {
      "TargetOriginId": "bmi-calculator-alb",
      "ViewerProtocolPolicy": "redirect-to-https",
      "AllowedMethods": {"Quantity": 7, "Items": ["GET","HEAD","OPTIONS","PUT","POST","PATCH","DELETE"], "CachedMethods": {"Quantity": 2, "Items": ["GET","HEAD"]}},
      "CachePolicyId": "4135ea2d-6df8-44a3-9df3-4b5a84be39ad",
      "OriginRequestPolicyId": "216adef6-5c7f-47e4-b989-5492eafa07d3",
      "Compress": true
    },
    "Comment": "BMI Calculator CloudFront Distribution",
    "Enabled": true,
    "ViewerCertificate": {
      "ACMCertificateArn": "arn:aws:acm:us-east-1:359345324847:certificate/df802f98-5faa-4328-a927-571382bd00e5",
      "SSLSupportMethod": "sni-only",
      "MinimumProtocolVersion": "TLSv1.2_2021"
    },
    "HttpVersion": "http2",
    "IsIPV6Enabled": true,
    "PriceClass": "PriceClass_100"
  }'
```

## Verification Commands

```bash
# Check certificate status
aws acm describe-certificate \
  --certificate-arn arn:aws:acm:us-east-1:359345324847:certificate/df802f98-5faa-4328-a927-571382bd00e5 \
  --region us-east-1 \
  --query 'Certificate.Status'

# Check target health
aws elbv2 describe-target-health \
  --target-group-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:targetgroup/bmi-calculator-tg/cfa5023400e7c504 \
  --region eu-central-1

# Check CloudFront status
aws cloudfront get-distribution --id E16YYJ4DT46N17 --query 'Distribution.Status'

# Test endpoints
curl -I http://bmi-calculator-alb-1620953438.eu-central-1.elb.amazonaws.com/bmi/
curl -I https://d2392ulp4il11k.cloudfront.net/bmi/
curl -I https://aaronzammit.com/bmi/
```

## Cost Estimate (Monthly)
- **ALB:** ~$16 + data processing (~$0.008/GB)
- **CloudFront:** Pay per request/transfer (Free tier: 1TB/month, 10M requests)
- **ACM:** Free
- **EC2:** t3.micro (existing)

## Troubleshooting

### WordPress wp-admin Redirect Loop (Resolved)

**Issue:** Accessing `https://aaronzammit.com/wp-admin/` caused an infinite redirect loop.

**Root Cause:**
```
User (HTTPS) → CloudFront → ALB (HTTP) → EC2
                                ↓
                    ALB sets X-Forwarded-Proto: http
                                ↓
                    WordPress sees "http", redirects to HTTPS
                                ↓
                    Infinite redirect loop
```

CloudFront connects to the ALB using HTTP (origin protocol policy: http-only). The ALB then sets `X-Forwarded-Proto: http` based on the connection it received. WordPress checks this header, sees `http`, and redirects to HTTPS - creating an infinite loop.

**Solution:**
1. Added a custom CloudFront origin header `X-CloudFront-Forwarded-Proto: https` that the ALB cannot overwrite
2. Updated `wp-config.php` to check for this new header:

```php
/* CloudFront HTTPS detection */
if (isset($_SERVER['HTTP_X_CLOUDFRONT_FORWARDED_PROTO']) && $_SERVER['HTTP_X_CLOUDFRONT_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

**Fix Applied:**
```bash
# Add custom origin header to CloudFront
aws cloudfront get-distribution-config --id E16YYJ4DT46N17 --output json > cf-config.json
# Edit to add CustomHeaders: X-CloudFront-Forwarded-Proto: https
aws cloudfront update-distribution --id E16YYJ4DT46N17 --distribution-config file://cf-config.json --if-match <ETag>
```

### 4xx Errors from Vulnerability Scanners (Expected)

**Issue:** CloudWatch shows intermittent 4xx error rates (sometimes 40-100%) on CloudFront.

**Root Cause:** Automated vulnerability scanners/bots probing for known PHP exploits:
- PHPUnit RCE (CVE-2017-9841): `/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php`
- ThinkPHP RCE: `/public/index.php?s=/index/\think\app/invokefunction`
- PHP PEAR command injection: `/index.php?lang=...pearcmd`
- Docker API probing: `/containers/json`

**Resolution:** These return 404 (not vulnerable). This is normal internet background noise. No action required.

**Investigation Commands:**
```bash
# Check CloudFront 4xx error rate
aws cloudwatch get-metric-statistics \
  --region us-east-1 \
  --namespace AWS/CloudFront \
  --metric-name 4xxErrorRate \
  --dimensions Name=DistributionId,Value=E16YYJ4DT46N17 Name=Region,Value=Global \
  --start-time $(date -u -d '48 hours ago' +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 3600 \
  --statistics Average

# Check ALB target 4xx errors
aws cloudwatch get-metric-statistics \
  --namespace AWS/ApplicationELB \
  --metric-name HTTPCode_Target_4XX_Count \
  --dimensions Name=LoadBalancer,Value=app/bmi-calculator-alb/3f95ab9e1d02f57f \
  --start-time $(date -u -d '48 hours ago' +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 3600 \
  --statistics Sum

# View Apache access logs via SSM
aws ssm send-command \
  --instance-ids i-065a560e87bfe8ab6 \
  --document-name "AWS-RunShellScript" \
  --parameters 'commands=["tail -100 /var/log/httpd/access_log | grep -v ELB-HealthChecker"]'
```

**Optional Mitigation:** Add AWS WAF to block known scanner patterns.

## Cleanup Commands

To tear down the infrastructure:

```bash
# Delete CloudFront distribution (must disable first)
aws cloudfront get-distribution-config --id E16YYJ4DT46N17 > cf-config.json
# Edit cf-config.json: set Enabled to false, then update
aws cloudfront update-distribution --id E16YYJ4DT46N17 --distribution-config file://cf-config.json --if-match <ETag>
# Wait for status to be Deployed, then delete
aws cloudfront delete-distribution --id E16YYJ4DT46N17 --if-match <ETag>

# Delete ALB
aws elbv2 delete-listener --listener-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:listener/app/bmi-calculator-alb/3f95ab9e1d02f57f/ed70a36931540f52 --region eu-central-1
aws elbv2 delete-load-balancer --load-balancer-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:loadbalancer/app/bmi-calculator-alb/3f95ab9e1d02f57f --region eu-central-1

# Delete target group
aws elbv2 delete-target-group --target-group-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:targetgroup/bmi-calculator-tg/cfa5023400e7c504 --region eu-central-1

# Delete ALB security group
aws ec2 delete-security-group --group-id sg-0d78cc17352915ee6 --region eu-central-1

# Delete certificate
aws acm delete-certificate --certificate-arn arn:aws:acm:us-east-1:359345324847:certificate/df802f98-5faa-4328-a927-571382bd00e5 --region us-east-1
```
