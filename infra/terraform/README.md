# BMI Calculator — ECS Fargate (Terraform)

Runs the BMI calculator (`aaronzammit.com/bmi/`) as a container on ECS Fargate behind
the existing shared `bmi-calculator-alb`, replacing the EC2 Auto Scaling Group and
retiring WordPress + local MariaDB.

- **Region:** eu-central-1 · **Account:** 359345324847
- **State:** S3 backend `aazamm-tf-state-eu-central-1`, key `bmi-fargate/terraform.tfstate`
- **Requires:** Terraform ≥ 1.10 (or OpenTofu ≥ 1.10).

## What this creates

ECR repo · ECS cluster · web service (php:8.4-apache, CPU autoscaling) · `bmi-fargate-tg`
(ip target group with session stickiness) · CloudWatch log group `/ecs/bmi` · SSM
SecureString params · IAM roles · `bmi-fargate-sg` (+ one ingress rule on the shared RDS SG).

It **references** (never manages) the VPC, subnets, ALB listener, ALB SG, and RDS SG. The app
data already lives on the shared RDS (`bmi_calculator` database) — no data migration.

## First-time setup

```bash
cd infra/terraform
terraform init
terraform plan      # should only CREATE resources + add 1 RDS SG rule
terraform apply     # cutover_to_fargate defaults to false → no traffic change yet
```

### Set the secrets (out-of-band)

Pull values from the instance `/var/www/html/bmi/.env`, then:

```bash
aws ssm put-parameter --overwrite --type SecureString --name /bmi/DATABASE_URL         --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/SESSION_SECRET        --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/GOOGLE_CLIENT_SECRET  --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/ADMIN_PASS            --value '...'   # new admin password
```

### Build & push the first image / run migrations

The web service references `:latest`; it stays in pull-retry until an image exists. CI
(`.github/workflows/deploy.yml`) builds → ECR → `update-service`. There is no DB migration
step (schema already exists on RDS).

### Smoke test, then go live

```bash
# test via the staging host (no real traffic)
curl -H 'Host: bmi-staging.aaronzammit.com' http://<alb-dns>/bmi/health.php

terraform apply -var=cutover_to_fargate=true -var=google_client_id=<id>   # aaronzammit.com -> Fargate
```

**Rollback:** `terraform apply -var=cutover_to_fargate=false` (back to the EC2 instance TG).

## Decommission EC2 (after stable)

Back up the WordPress MariaDB + wp-content, then scale the ASG to 0 / delete it, delete the
launch template, `bmi-calculator-tg`, and repoint or remove the ALB default rule.

## Notes

- PHP sessions are kept sticky via the target group's `lb_cookie`.
- `health.php` checks only PostgreSQL in the container (the Apache/disk/MariaDB checks were removed).
- Admin credentials are read from `ADMIN_PASS`/`ADMIN_USER` (SSM), not hardcoded.
