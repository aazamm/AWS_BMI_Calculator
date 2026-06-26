output "ecr_repository_url" {
  description = "Push images here (CI uses :latest and :<sha>)"
  value       = aws_ecr_repository.app.repository_url
}

output "cluster_name" {
  value = aws_ecs_cluster.main.name
}

output "web_service_name" {
  value = aws_ecs_service.web.name
}

output "fargate_target_group_arn" {
  value = aws_lb_target_group.fargate.arn
}

output "fargate_security_group_id" {
  value = aws_security_group.fargate.id
}

output "log_group" {
  value = aws_cloudwatch_log_group.app.name
}

output "secret_param_names" {
  description = "Set real values with: aws ssm put-parameter --overwrite --type SecureString"
  value       = [for p in aws_ssm_parameter.secret : p.name]
}
