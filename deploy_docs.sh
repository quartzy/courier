#!/usr/bin/env bash

# This is limited to a single version to avoid deploying the docs multiple times in a single build matrix
if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
  git config user.name ${GH_USER_NAME}
  git config user.email ${GH_USER_EMAIL}
  git remote add gh-token "https://${GH_TOKEN}@github.com/${TRAVIS_REPO_SLUG}.git"
  git fetch gh-token && git fetch gh-token gh-pages:gh-pages
  pip install --user -r requirements.txt
  mkdocs gh-deploy -v --clean --remote-name gh-token
fi
