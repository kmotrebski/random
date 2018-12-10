#!/usr/bin/env bash

if [[ "$CODEBUILD_WEBHOOK_TRIGGER" =~ ^tag/ ]]; then
    printf "Build triggered by a tag. Stopping the build!"
    aws codebuild stop-build --id $CODEBUILD_BUILD_ID
fi

# line to be added in CodeBuild spec:
# chmod +x check_tag.sh && ./check_tag.sh
