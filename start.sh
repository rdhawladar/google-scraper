#!/bin/bash

# Copy .env file if it doesn't exist
if [ ! -f ./backend/.env ]; then
    cp ./backend/.env.example ./backend/.env
fi

# Stop any running containers
docker compose down

# Remove all containers and volumes
docker compose rm -f
docker volume prune -f

# Build and start the containers
docker compose up --build -d

# Wait for the database to be ready
echo "Waiting for database to be ready..."
sleep 10

# Generate a new application key and set it in the .env file
docker compose exec backend php artisan key:generate --force

# Run migrations
docker compose exec backend php artisan migrate --force

echo "Application is ready!"
echo "Backend: http://localhost:8000"
echo "Frontend: http://localhost:3000"
