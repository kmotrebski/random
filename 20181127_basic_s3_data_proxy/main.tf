
# This is basic setup that works.
# todo to be refactored into more clean version (modules, reuse)
# todo refactor into policy data source JSON factory

data "aws_caller_identity" "current" {

}

resource "aws_s3_bucket" "csvs" {
  bucket = "com-somebody-csvs"
  acl    = "private"
}

resource "aws_s3_bucket" "events" {
  bucket = "com-somebody-events"
  acl    = "private"
}

resource "aws_iam_user" "marketer" {
  name = "marketer"
}

resource "aws_iam_user_group_membership" "users_marketer" {
  user = "${aws_iam_user.marketer.name}"

  groups = [
    "${aws_iam_group.users.name}",
  ]
}

resource "aws_iam_user" "data_scientist" {
  name = "ds"
}

resource "aws_iam_user_group_membership" "users_data_scientist" {
  user = "${aws_iam_user.data_scientist.name}"

  groups = [
    "${aws_iam_group.users.name}",
  ]
}

resource "aws_iam_user" "production" {
  name = "production"
}

resource "aws_iam_user_group_membership" "users_production" {
  user = "${aws_iam_user.production.name}"

  groups = [
    "${aws_iam_group.users.name}",
  ]
}

resource "aws_iam_group" "users" {
  name = "users"
  path = "/users/"
}

resource "aws_iam_group_policy" "being_self_serviced" {
  name  = "being_self_serviced"
  group = "${aws_iam_group.users.id}"

  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "iam:GetAccountPasswordPolicy",
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": "iam:ChangePassword",
      "Resource": "arn:aws:iam::${data.aws_caller_identity.current.account_id}:user/$${aws:username}"
    },
    {
      "Sid": "ViewAddAccessKeysForUser",
      "Effect": "Allow",
      "Action": [
          "iam:GetUser",
          "iam:CreateAccessKey",
          "iam:ListAccessKeys"
      ],
      "Resource": "arn:aws:iam::${data.aws_caller_identity.current.account_id}:user/$${aws:username}"
    },
    {
      "Sid": "ManageAccessKeysForUser",
      "Effect": "Allow",
      "Action": [
          "iam:GetUser",
          "iam:CreateAccessKey",
          "iam:ListAccessKeys",

          "iam:DeleteAccessKey",
          "iam:GetAccessKeyLastUsed",
          "iam:UpdateAccessKey"
      ],
      "Resource": "arn:aws:iam::${data.aws_caller_identity.current.account_id}:user/$${aws:username}"
    },
    {
      "Sid": "ListUsersInConsole",
      "Effect": "Allow",
      "Action": "iam:ListUsers",
      "Resource": "*"
    }
  ]
}
EOF
}

resource "aws_iam_user_policy" "policy" {

  user = "${aws_iam_user.marketer.name}"
  name        = "marketer_policy"

  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
       "Sid": "ListBuckets",
       "Effect": "Allow",
       "Action": [
          "s3:ListAllMyBuckets"
       ],
       "Resource": [
          "arn:aws:s3:::*"
       ]
    },
    {
       "Sid": "SeeBucketDetails",
       "Effect": "Allow",
       "Action": [
          "s3:GetBucketLocation",
          "s3:ListBucket"
       ],
       "Resource": [
          "arn:aws:s3:::${aws_s3_bucket.csvs.bucket}"
       ]
    },
    {
       "Sid": "GetData",
       "Effect": "Allow",
       "Action": [
           "s3:GetObject"
       ],
       "Resource": [
        "arn:aws:s3:::${aws_s3_bucket.csvs.bucket}/*"
       ]
    }
  ]
}
EOF
}

resource "aws_iam_user_policy" "policy_for_ds" {

  user = "${aws_iam_user.data_scientist.name}"
  name        = "data_scientist_policy"

  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
       "Sid": "ListBuckets",
       "Effect": "Allow",
       "Action": [
          "s3:ListAllMyBuckets"
       ],
       "Resource": [
          "arn:aws:s3:::*"
       ]
    },
    {
       "Sid": "SeeBucketDetails",
       "Effect": "Allow",
       "Action": [
          "s3:GetBucketLocation",
          "s3:ListBucket"
       ],
       "Resource": [
          "arn:aws:s3:::${aws_s3_bucket.events.bucket}"
       ]
    },
    {
       "Sid": "GetData",
       "Effect": "Allow",
       "Action": [
           "s3:GetObject"
       ],
       "Resource": [
        "arn:aws:s3:::${aws_s3_bucket.events.bucket}/*"
       ]
    }
  ]
}
EOF
}

resource "aws_iam_user_policy" "production_policy" {

  user = "${aws_iam_user.production.name}"
  name = "production_policy"

  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
       "Sid": "ListBuckets",
       "Effect": "Allow",
       "Action": [
          "s3:ListAllMyBuckets"
       ],
       "Resource": [
          "arn:aws:s3:::*"
       ]
    },
    {
       "Sid": "SeeBucketDetails",
       "Effect": "Allow",
       "Action": [
          "s3:GetBucketLocation",
          "s3:ListBucket"
       ],
       "Resource": [
          "arn:aws:s3:::${aws_s3_bucket.csvs.bucket}",
          "arn:aws:s3:::${aws_s3_bucket.events.bucket}"
       ]
    },
    {
       "Sid": "GetData",
       "Effect": "Allow",
       "Action": [
           "s3:GetObject"
       ],
       "Resource": [
        "arn:aws:s3:::${aws_s3_bucket.csvs.bucket}/*",
        "arn:aws:s3:::${aws_s3_bucket.events.bucket}/*"
       ]
    },
    {
       "Sid": "PermissionForObjectOperations",
       "Effect": "Allow",
       "Action": [
          "s3:PutObject"
       ],
       "Resource": [
          "arn:aws:s3:::${aws_s3_bucket.csvs.bucket}/*",
          "arn:aws:s3:::${aws_s3_bucket.events.bucket}/*"
       ]
    }
  ]
}
EOF
}
