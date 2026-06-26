variable "aws_region" {
  type    = string
  default = "eu-central-1"
}

variable "name_prefix" {
  type    = string
  default = "bmi"
}

# ---------------------------------------------------------------------------
# Existing (CLI-built) resources — referenced read-only.
# Defaults match account 359345324847 / eu-central-1.
# ---------------------------------------------------------------------------
variable "vpc_id" {
  type    = string
  default = "vpc-04c3e303f1975de6c"
}

variable "subnet_ids" {
  type = list(string)
  default = [
    "subnet-0c5344028089efd43", # eu-central-1a
    "subnet-0b64315e299a84d68", # eu-central-1b
    "subnet-03418c30648f106f2", # eu-central-1c
  ]
}

variable "alb_listener_arn" {
  type    = string
  default = "arn:aws:elasticloadbalancing:eu-central-1:359345324847:listener/app/bmi-calculator-alb/3f95ab9e1d02f57f/ed70a36931540f52"
}

variable "alb_security_group_id" {
  type    = string
  default = "sg-0d78cc17352915ee6" # bmi-calculator-alb-sg
}

variable "rds_security_group_id" {
  type    = string
  default = "sg-0ede2c2e7914e2994" # shared RDS SG (financial-rss-db, hosts the bmi_calculator DB)
}

# ---------------------------------------------------------------------------
# Application / container
# ---------------------------------------------------------------------------
variable "container_port" {
  type    = number
  default = 80
}

variable "image_tag" {
  type    = string
  default = "latest"
}

variable "web_cpu" {
  type    = number
  default = 256
}

variable "web_memory" {
  type    = number
  default = 512
}

variable "web_desired_count" {
  type    = number
  default = 1
}

variable "web_max_count" {
  type    = number
  default = 3
}

variable "health_check_path" {
  type    = string
  default = "/bmi/health.php"
}

variable "base_url" {
  type    = string
  default = "https://aaronzammit.com/bmi"
}

# Google OAuth client id (public, not a secret). Set the real value before cutover.
variable "google_client_id" {
  type    = string
  default = ""
}

variable "admin_user" {
  type    = string
  default = "admin"
}

variable "app_host" {
  type    = string
  default = "aaronzammit.com"
}

variable "staging_host" {
  type    = string
  default = "bmi-staging.aaronzammit.com"
}

# ---------------------------------------------------------------------------
# Cutover toggle. When true, adds a host rule routing aaronzammit.com to the
# Fargate target group (priority 15, ahead of the default rule that points at
# the EC2 instance TG). Flip to true to go live; false to roll back.
# ---------------------------------------------------------------------------
variable "cutover_to_fargate" {
  type    = bool
  default = false
}
