FROM node:18-alpine as base
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies including devDependencies for build
RUN npm install

# Copy the rest of the application
COPY . .

# Set default environment variable
ARG VITE_API_URL=http://localhost:8000
ENV VITE_API_URL=$VITE_API_URL

FROM base as development
EXPOSE 3000
ENV NODE_ENV=development
ENV HOST=0.0.0.0

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]

FROM base as production
ENV NODE_ENV=production
RUN npm run build
