provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project   = "bmi-calculator"
      Component = "fargate"
      ManagedBy = "terraform"
    }
  }
}
