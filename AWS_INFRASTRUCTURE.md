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
- **Launch Template:** lt-00e9af6830a1fb15c (v3)
- **AMI:** ami-08cd8dc3fa78b088d
- **Instance Type:** t3.micro
- **Availability Zones:** eu-central-1a, eu-central-1b, eu-central-1c
- **Scaling Policies:** CPU > 70%, Memory > 70%
- **Health Check:** ELB, 120s grace period

### Launch Template
- **ID:** lt-00e9af6830a1fb15c
- **Version:** 3 (with Google Analytics)
- **AMI:** ami-08cd8dc3fa78b088d
- **IAM Profile:** CloudWatchAgentProfile
- **Monitoring:** Detailed (1-minute)
- **User Data:** CloudWatch Agent installation

### Google Analytics
- **Measurement ID:** G-3B3EDTZ0JT
- **Property:** BMI Calculator
- **Tracking:** Page views, demographics, traffic sources
- **Dashboard:** https://analytics.google.com/

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
- **Cache Policy:** CachingDisabled (4135ea2d-6df8-44a3-9df3-4b5a84be39ad)
- **Origin Request Policy:** AllViewer (216adef6-5c7f-47e4-b989-5492eafa07d3)
- **SSL:** TLSv1.2_2021, SNI

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
