#!/usr/bin/env bash

if [ "$TRAVIS_PULL_REQUEST" = "false" ] && [ "$TRAVIS_BRANCH" = "master" ]; then
    pip install -r requirements.txt
    mkdocs gh-deploy
fi
