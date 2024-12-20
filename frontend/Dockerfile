FROM node:18-alpine as base
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy the rest of the application
COPY . .

FROM base as development
EXPOSE 3000
ENV NODE_ENV=development
ENV HOST=0.0.0.0
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]

FROM base as production
ENV NODE_ENV=production
CMD ["npm", "run", "build"]
