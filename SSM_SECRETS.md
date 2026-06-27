# Secrets — AWS SSM Parameter Store (BMI Calculator)

How the Fargate deployment of the BMI calculator (`aaronzammit.com/bmi/`) stores, injects, and
rotates its secrets. Region **eu-central-1**, account **359345324847**.

## Where secrets live

Application secrets are stored as **SSM Parameter Store `SecureString`** parameters, encrypted
with the AWS-managed KMS key `alias/aws/ssm`. They are **never** committed to git and **never**
stored in Terraform state — Terraform only creates empty placeholders (see "Lifecycle" below).

| Parameter | Type | Contents |
|-----------|------|----------|
| `/bmi/DATABASE_URL` | SecureString | PostgreSQL connection string for the `bmi_calculator` DB on the shared RDS (includes password) |
| `/bmi/SESSION_SECRET` | SecureString | PHP session secret |
| `/bmi/GOOGLE_CLIENT_SECRET` | SecureString | Google OAuth client secret |
| `/bmi/ADMIN_PASS` | SecureString | Password for the `/bmi/admin.php` panel (replaces the old hardcoded `bmi2026!`) |

Non-secret configuration is passed as plain task-definition **environment variables**, not SSM:
`BASE_URL`, `GOOGLE_CLIENT_ID` (public), `ADMIN_USER`.

## How the container consumes them

The ECS task definition references each parameter by ARN in its `secrets` block, so ECS injects
the decrypted value as an environment variable at container start. The PHP app reads them with
`getenv()` (`db.php` for `DATABASE_URL`, `google_auth.php` for the OAuth secret, `admin.php` for
`ADMIN_USER`/`ADMIN_PASS`):

```json
"secrets": [
  { "name": "DATABASE_URL",         "valueFrom": "arn:aws:ssm:eu-central-1:359345324847:parameter/bmi/DATABASE_URL" },
  { "name": "SESSION_SECRET",       "valueFrom": "...parameter/bmi/SESSION_SECRET" },
  { "name": "GOOGLE_CLIENT_SECRET", "valueFrom": "...parameter/bmi/GOOGLE_CLIENT_SECRET" },
  { "name": "ADMIN_PASS",           "valueFrom": "...parameter/bmi/ADMIN_PASS" }
]
```

The **ECS task execution role** (`bmi-ecs-exec`) is granted exactly the access needed to fetch and
decrypt them:

```
ssm:GetParameters   on the four /bmi/* parameter ARNs
kms:Decrypt         on alias/aws/ssm
```

The task **role** (the app's own identity) has no SSM access — only the execution role needs it.

## Lifecycle (Terraform)

`infra/terraform/ssm.tf` creates each parameter as a `SecureString` with a placeholder value and
`lifecycle { ignore_changes = [value] }`. This means:

- Terraform owns the parameter's existence and metadata, **not its value**.
- `terraform apply` never overwrites a real secret with the placeholder.
- Real values are set **out-of-band** (below), keeping plaintext secrets out of state and VCS.

## Setting / rotating a secret

```bash
aws ssm put-parameter --overwrite --type SecureString \
  --name /bmi/ADMIN_PASS --value '<new-value>'
```

After rotating a value, restart the service so tasks pick it up (secrets are read at start):

```bash
aws ecs update-service --cluster bmi-cluster --service bmi-web --force-new-deployment
```

## Retrieving a secret

```bash
# e.g. the admin panel password
aws ssm get-parameter --name /bmi/ADMIN_PASS \
  --with-decryption --query Parameter.Value --output text
```

`--with-decryption` requires `kms:Decrypt` on `alias/aws/ssm`.

## Initial population (one-time)

On migration, `DATABASE_URL`, `SESSION_SECRET`, and `GOOGLE_CLIENT_SECRET` were copied from the
old instance's `/var/www/html/bmi/.env`. `ADMIN_PASS` was set to a **new random value** (the old
hardcoded `admin`/`bmi2026!` was removed from `admin.php`). To re-bootstrap from scratch:

```bash
aws ssm put-parameter --overwrite --type SecureString --name /bmi/DATABASE_URL         --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/SESSION_SECRET        --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/GOOGLE_CLIENT_SECRET  --value '...'
aws ssm put-parameter --overwrite --type SecureString --name /bmi/ADMIN_PASS            --value "$(openssl rand -base64 18)"
```

## Security notes

- Plaintext secrets exist only in SSM (encrypted) and in the running container's memory.
- Nothing secret is in git or Terraform state.
- The execution role is least-privilege: `GetParameters` is scoped to these four ARNs only.
- CI authenticates via **GitHub OIDC** (role `bmi-ci-oidc`) and has **no** access to these
  parameters — it only builds images and updates the service.
