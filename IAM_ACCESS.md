# IAM Access — CLI Identities (BMI Calculator)

AWS account **359345324847**, region **eu-central-1**.

Two IAM users replace day-to-day root usage (both created 2026-08-07):
**`aazamm_bmi_ro`** (profile `bmi-ro`) for reads, **`aazamm_bmi`** (profile `bmi-admin`) for changes.

## Admin user: `aazamm_bmi`

Created **2026-08-07** so infrastructure changes no longer require the account root user.

| Property | Value |
|----------|-------|
| User ARN | `arn:aws:iam::359345324847:user/aazamm_bmi` |
| Attached policy | `arn:aws:iam::aws:policy/AdministratorAccess` (AWS managed) |
| Credentials | One access key, stored **only** in `~/.aws/credentials` under profile `bmi-admin` |
| Profile config | `~/.aws/config` → `[profile bmi-admin]`, region `eu-central-1`, output `json` |
| Tag | `purpose=admin-cli` |

```bash
aws ecs update-service --cluster bmi-cluster --service bmi-web --force-new-deployment --profile bmi-admin
```

Full admin: can do everything except a handful of root-only account tasks (closing the
account, changing root email/MFA, some billing/tax settings). Use `bmi-ro` unless a
command actually mutates something. Recommended hardening: enable MFA on this user and
delete the root access keys once comfortable (root then remains console-only).

## Read-only user: `aazamm_bmi_ro`

Created **2026-08-07** to stop using the account **root** credentials for day-to-day CLI work
(investigations, metrics, log reading, describe/list calls).

| Property | Value |
|----------|-------|
| User ARN | `arn:aws:iam::359345324847:user/aazamm_bmi_ro` |
| Attached policy | `arn:aws:iam::aws:policy/ReadOnlyAccess` (AWS managed) |
| Credentials | One access key, stored **only** in `~/.aws/credentials` under profile `bmi-ro` |
| Profile config | `~/.aws/config` → `[profile bmi-ro]`, region `eu-central-1`, output `json` |
| Tag | `purpose=readonly-cli` |

### Usage

```bash
aws ecs describe-services --cluster bmi-cluster --services bmi-web --profile bmi-ro
# or make it the default for a shell session:
export AWS_PROFILE=bmi-ro
```

### What it can and cannot do

- ✅ All read operations across all services: `describe-*`, `list-*`, `get-*`,
  CloudWatch metrics/alarms/logs, ECS/ALB/RDS/CloudFront/WAF inspection.
- ❌ Any mutation: deployments, alarm changes, CloudFront updates, SSM writes, IAM changes.
  Verified at creation: `cloudwatch:DeleteAlarms` → `AccessDenied`.
- ❌ `aws ssm get-parameter --with-decryption` on `/bmi/*` secrets — ReadOnlyAccess includes
  `ssm:GetParameter*` but **not** `kms:Decrypt`, so SecureString values stay unreadable. This is
  intentional (see SSM_SECRETS.md for who reads secrets).

### Key rotation / revocation

```bash
aws iam create-access-key  --user-name aazamm_bmi_ro                       # new key → update ~/.aws/credentials
aws iam list-access-keys   --user-name aazamm_bmi_ro
aws iam delete-access-key  --user-name aazamm_bmi_ro --access-key-id <old>
```

(Key management itself requires an admin identity, not `bmi-ro`.)

## Remaining identities

- **root** — still the default CLI profile as of 2026-08-07, but now redundant:
  `bmi-admin` covers all infrastructure work. Next steps: enable MFA on root, then delete
  the root access keys (`aws iam delete-access-key` for each key listed by
  `aws iam list-access-keys` under the root identity) and remove the `[default]` entry
  from `~/.aws/credentials`.
- **`bmi-ci-oidc`** (role) — GitHub Actions CI via OIDC; ECR push + ECS deploy only.
- **`bmi-ecs-exec`** (role) — ECS task execution; pulls `/bmi/*` SSM secrets at container start.
