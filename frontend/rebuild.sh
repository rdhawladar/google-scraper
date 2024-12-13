#!/bin/sh
rm -rf node_modules
rm package-lock.json
npm install --legacy-peer-deps
npm start
