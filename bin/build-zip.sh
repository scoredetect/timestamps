#!/bin/bash

npm ci
npm run build

rm ./timestamps.zip

git archive --output=timestamps.zip HEAD
zip -ur timestamps.zip dist