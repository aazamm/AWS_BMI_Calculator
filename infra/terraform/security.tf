resource "aws_security_group" "fargate" {
  name        = "${var.name_prefix}-fargate-sg"
  description = "BMI calculator Fargate tasks - inbound from ALB only"
  vpc_id      = var.vpc_id

  tags = { Name = "${var.name_prefix}-fargate-sg" }
}

resource "aws_vpc_security_group_egress_rule" "fargate_all" {
  security_group_id = aws_security_group.fargate.id
  description       = "All outbound (RDS, Google OAuth)"
  ip_protocol       = "-1"
  cidr_ipv4         = "0.0.0.0/0"
}

resource "aws_vpc_security_group_ingress_rule" "fargate_from_alb" {
  security_group_id            = aws_security_group.fargate.id
  description                  = "HTTP from the shared ALB"
  ip_protocol                  = "tcp"
  from_port                    = var.container_port
  to_port                      = var.container_port
  referenced_security_group_id = var.alb_security_group_id
}

# Allow the BMI Fargate tasks to reach the shared RDS instance (bmi_calculator DB).
resource "aws_vpc_security_group_ingress_rule" "rds_from_fargate" {
  security_group_id            = var.rds_security_group_id
  description                  = "PostgreSQL from BMI Fargate tasks"
  ip_protocol                  = "tcp"
  from_port                    = 5432
  to_port                      = 5432
  referenced_security_group_id = aws_security_group.fargate.id
}
