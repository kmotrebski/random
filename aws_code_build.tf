
resource "aws_codecommit_repository" "infratifacts" {
  repository_name = "infratifacts"
  description     = "Otrebski infratifacts repository"
  default_branch  = "master"
}

output "git_infratifacts_ssh" {
  value = "${aws_codecommit_repository.infratifacts.clone_url_ssh}}"
}

output "git_infratifacts_http" {
  value = "${aws_codecommit_repository.infratifacts.clone_url_http}}"
}

resource "aws_codebuild_project" "php_base" {
  name         = "php_base"
  description  = "Build pipeline for baseline PHP image."

  service_role = "${aws_iam_role.code_build.arn}"

  artifacts {
    type = "NO_ARTIFACTS"
  }

  cache = {
    type = "NO_CACHE"
  }

  environment {
    compute_type = "BUILD_GENERAL1_SMALL"
    image        = "aws/codebuild/docker:17.09.0"
    type         = "LINUX_CONTAINER"
    privileged_mode = true

    environment_variable {
      "name"  = "OTREBSKI_AWS_ACCOUNT_ID"
      "value" = "${var.kmotrebski_aws_account_id}"
    }

    environment_variable {
      "name"  = "OTREBSKI_AWS_REGION"
      "value" = "${var.kmotrebski_aws_region}"
    }
  }

  source {
    type            = "GITHUB"
    location        = "https://github.com/otrebski/infratifacts.git"
    git_clone_depth = 1
    buildspec       = "buildspec_phpbase.yml"
  }
}

resource "aws_codebuild_project" "fluentd" {
  name         = "fluentd"
  description  = "Build pipeline for Fluentd Docker images."

  service_role = "${aws_iam_role.code_build.arn}"

  artifacts {
    type = "NO_ARTIFACTS"
  }

  cache = {
    type = "NO_CACHE"
  }

  environment {
    compute_type = "BUILD_GENERAL1_LARGE"
    image        = "aws/codebuild/docker:17.09.0"
    type         = "LINUX_CONTAINER"
    privileged_mode = true

    environment_variable {
      "name"  = "OTREBSKI_AWS_ACCOUNT_ID"
      "value" = "${var.kmotrebski_aws_account_id}"
    }

    environment_variable {
      "name"  = "OTREBSKI_AWS_REGION"
      "value" = "${var.kmotrebski_aws_region}"
    }
  }

  source {
    type            = "GITHUB"
    location        = "https://github.com/github/sds.git"
    git_clone_depth = 1
    buildspec       = "buildspec_fluentd.yml"
  }
}

resource "aws_codebuild_webhook" "fluentd" {
  project_name = "${aws_codebuild_project.fluentd.name}"
}

resource "aws_iam_role" "code_build" {
  name = "code_build"

  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": [
          "codebuild.amazonaws.com"
        ]
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF
}

resource "aws_iam_role_policy_attachment" "attachment" {
  role = "${aws_iam_role.code_build.name}"
  policy_arn = "arn:aws:iam::aws:policy/AWSCodeBuildAdminAccess"
}

resource "aws_iam_role_policy_attachment" "code_build_logs" {
  role = "${aws_iam_role.code_build.name}"
  policy_arn = "arn:aws:iam::aws:policy/CloudWatchLogsFullAccess"
}

resource "aws_iam_role_policy_attachment" "code_build_ecr" {
  role = "${aws_iam_role.code_build.name}"
  policy_arn = "arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryFullAccess"
}

resource "aws_iam_role_policy_attachment" "code_build_s3" {
  role = "${aws_iam_role.code_build.name}"
  policy_arn = "arn:aws:iam::aws:policy/AmazonS3FullAccess"
}

resource "aws_kms_key" "kms_key_for_ssh" {
  description             = "KMS key 1"
  deletion_window_in_days = 7
}

resource "aws_s3_bucket" "ssh_private_key_bucket" {
  bucket = "otrebski-ci-cd-pipeline-2"
  acl    = "private"
}

resource "aws_s3_bucket_object" "ssh_private_key_object" {
  key        = "id_rsa"
  bucket     = "${aws_s3_bucket.ssh_private_key_bucket.id}"
  source     = "${path.root}/ci-cd-keys/kmotrebski-bot"
//  kms_key_id = "${aws_kms_key.kms_key_for_ssh.arn}"
}
