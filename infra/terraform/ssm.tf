# Secrets are created as placeholders; real values are set out-of-band
# (`aws ssm put-parameter --overwrite ...`). Terraform never stores the secret.
locals {
  secret_names = {
    database_url         = "DATABASE_URL"
    session_secret       = "SESSION_SECRET"
    google_client_secret = "GOOGLE_CLIENT_SECRET"
    admin_pass           = "ADMIN_PASS"
  }
}

resource "aws_ssm_parameter" "secret" {
  for_each = local.secret_names

  name  = "/${var.name_prefix}/${each.value}"
  type  = "SecureString"
  value = "PLACEHOLDER_SET_OUT_OF_BAND"

  lifecycle {
    ignore_changes = [value]
  }

  tags = { Name = "${var.name_prefix}-${each.key}" }
}
