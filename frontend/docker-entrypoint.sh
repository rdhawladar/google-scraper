#!/bin/sh

# Install dependencies if node_modules doesn't exist or if package.json has changed
npm install --include=dev
# Then run the actual command
exec "$@"
