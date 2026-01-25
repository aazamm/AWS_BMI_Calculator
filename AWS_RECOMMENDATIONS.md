# AWS Infrastructure Recommendations - BMI Calculator

## Assessment Date: 2026-01-25

### Current Status Summary

| Component | Status | Health |
|-----------|--------|--------|
| CloudFront | Deployed | ✅ Healthy |
| ALB | Active | ✅ Healthy |
| Target Group | Healthy | ✅ 1/1 targets |
| EC2 Instance | Running | ✅ Healthy |

---

## Issues Found

### 🔴 Critical Issues

| # | Issue | Risk |
|---|-------|------|
| 1 | **Single EC2 instance** - No redundancy, single point of failure | Service outage if instance fails |
| 2 | **No backups** - No EBS snapshots or AWS Backup configured | Data loss risk |
| 3 | **SSH open to 0.0.0.0/0** - Port 22 accessible from anywhere | Security vulnerability |
| 4 | **EC2 port 80 open to world** - Should only accept traffic from ALB | Bypass ALB/CloudFront possible |
| 5 | **EBS volume not encrypted** | Data security risk |

### 🟠 High Priority Issues

| # | Issue | Impact |
|---|-------|--------|
| 6 | **No CloudWatch alarms** | No alerting on failures |
| 7 | **No Auto Scaling** | Cannot handle traffic spikes or self-heal |
| 8 | **ALB deletion protection disabled** | Accidental deletion possible |
| 9 | **No WAF protection** | Vulnerable to DDoS, SQL injection, XSS |
| 10 | **No access logging** (ALB & CloudFront) | No audit trail |

### 🟡 Medium Priority Issues

| # | Issue | Impact |
|---|-------|--------|
| 11 | **Detailed monitoring disabled** on EC2 | 5-min metrics only (vs 1-min) |
| 12 | **No custom error pages** in CloudFront | Poor user experience on errors |
| 13 | **No Origin Shield** | Higher origin load, more latency |
| 14 | **PriceClass_100** | Limited to NA/EU edge locations |

---

## Recommendations

### Immediate (Security)

**1. Restrict EC2 Security Group - Remove public port 80 access:**
```bash
aws ec2 revoke-security-group-ingress \
  --group-id sg-0415fab6c3b564196 \
  --protocol tcp --port 80 --cidr 0.0.0.0/0 \
  --region eu-central-1
```
✅ **IMPLEMENTED: 2026-01-25**

**2. Restrict SSH access to your IP only:**
```bash
# First, find your IP
MY_IP=$(curl -s ifconfig.me)

# Remove 0.0.0.0/0 SSH rule
aws ec2 revoke-security-group-ingress \
  --group-id sg-0415fab6c3b564196 \
  --protocol tcp --port 22 --cidr 0.0.0.0/0 \
  --region eu-central-1

# Add your IP only
aws ec2 authorize-security-group-ingress \
  --group-id sg-0415fab6c3b564196 \
  --protocol tcp --port 22 --cidr ${MY_IP}/32 \
  --region eu-central-1
```

**3. Enable ALB deletion protection:**
```bash
aws elbv2 modify-load-balancer-attributes \
  --load-balancer-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:loadbalancer/app/bmi-calculator-alb/3f95ab9e1d02f57f \
  --attributes Key=deletion_protection.enabled,Value=true \
  --region eu-central-1
```

### Short-term (Resilience)

**4. Create EBS snapshot (backup):**
```bash
aws ec2 create-snapshot \
  --volume-id vol-06fafb4b6ab359eb0 \
  --description "BMI Calculator backup $(date +%Y-%m-%d)" \
  --region eu-central-1
```

**5. Enable detailed monitoring:**
```bash
aws ec2 monitor-instances \
  --instance-ids i-02c7b760e6ac58028 \
  --region eu-central-1
```

**6. Create CloudWatch alarms:**
```bash
# CPU utilization alarm
aws cloudwatch put-metric-alarm \
  --alarm-name "BMI-Calculator-High-CPU" \
  --metric-name CPUUtilization \
  --namespace AWS/EC2 \
  --statistic Average \
  --period 300 \
  --threshold 80 \
  --comparison-operator GreaterThanThreshold \
  --dimensions Name=InstanceId,Value=i-02c7b760e6ac58028 \
  --evaluation-periods 2 \
  --alarm-actions arn:aws:sns:eu-central-1:359345324847:alerts \
  --region eu-central-1

# ALB unhealthy targets alarm
aws cloudwatch put-metric-alarm \
  --alarm-name "BMI-Calculator-Unhealthy-Targets" \
  --metric-name UnHealthyHostCount \
  --namespace AWS/ApplicationELB \
  --statistic Average \
  --period 60 \
  --threshold 1 \
  --comparison-operator GreaterThanOrEqualToThreshold \
  --dimensions Name=TargetGroup,Value=targetgroup/bmi-calculator-tg/cfa5023400e7c504 Name=LoadBalancer,Value=app/bmi-calculator-alb/3f95ab9e1d02f57f \
  --evaluation-periods 1 \
  --region eu-central-1
```

### Medium-term (High Availability)

**7. Add second EC2 instance in different AZ** for redundancy

**8. Set up Auto Scaling Group:**
- Min: 1, Max: 3, Desired: 2
- Scale based on CPU or request count
- Across multiple AZs

**9. Enable ALB access logs:**
```bash
# Create S3 bucket first, then:
aws elbv2 modify-load-balancer-attributes \
  --load-balancer-arn arn:aws:elasticloadbalancing:eu-central-1:359345324847:loadbalancer/app/bmi-calculator-alb/3f95ab9e1d02f57f \
  --attributes Key=access_logs.s3.enabled,Value=true Key=access_logs.s3.bucket,Value=your-log-bucket Key=access_logs.s3.prefix,Value=alb-logs \
  --region eu-central-1
```

**10. Add AWS WAF** to protect against common attacks

---

## Implementation Status

| # | Recommendation | Status | Date |
|---|----------------|--------|------|
| 1 | Remove public port 80 from EC2 SG | ✅ Done | 2026-01-25 |
| 2 | Restrict SSH to specific IP | ⏳ Pending | - |
| 3 | Enable ALB deletion protection | ⏳ Pending | - |
| 4 | Create EBS snapshot / AMI | ✅ Done | 2026-01-25 |
| 5 | Enable detailed monitoring | ✅ Done | 2026-01-25 |
| 6 | Create CloudWatch alarms | ✅ Done | 2026-01-25 |
| 7 | Add second EC2 instance (Multi-AZ) | ✅ Done | 2026-01-25 |
| 8 | Set up Auto Scaling (CPU + Memory) | ✅ Done | 2026-01-25 |
| 9 | Enable ALB access logs | ⏳ Pending | - |
| 10 | Add AWS WAF | ⏳ Pending | - |

---

## Cost Estimates

| Recommendation | Estimated Monthly Cost |
|----------------|------------------------|
| EBS Snapshots | ~$0.05/GB |
| Detailed Monitoring | ~$2.10 |
| Second EC2 (t3.micro) | ~$7.60 |
| Auto Scaling | Varies by usage |
| ALB Access Logs (S3) | ~$0.023/GB |
| AWS WAF | ~$5 + $1/million requests |
