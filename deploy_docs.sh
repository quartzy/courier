#!/usr/bin/env bash

#if [ "$TRAVIS_PULL_REQUEST" = "false" ] && [ "$TRAVIS_BRANCH" = "master" ]; then
if [ "$TRAVIS_BRANCH" = "master" ]; then
  git config user.name ${GH_USER_NAME}
  git config user.email ${GH_USER_EMAIL}
  git remote add gh-token "https://${GH_TOKEN}@github.com/${TRAVIS_REPO_SLUG}.git"
  git fetch gh-token && git fetch gh-token gh-pages:gh-pages
  pip install --user -r requirements.txt
  mkdocs gh-deploy -v --clean --remote-name gh-token
fi
