# Google Scraper

A powerful web application for scraping and analyzing Google search results with automated data collection capabilities.

## Features

- Automated Google search results scraping
- Bulk keyword processing
- CSV import functionality
- Real-time scraping status monitoring
- Search result analytics and insights
- Proxy support for reliable scraping
- Rate limiting and request management
- Data persistence and history tracking

## Tech Stack

### Backend
- PHP with Laravel Framework
- PostgreSQL Database
- Redis for Queue Management
- Docker containerization

### Frontend
- React.js
- Bootstrap for UI
- Axios for API requests
- State management with React Context

### DevOps & Infrastructure
- Docker & Docker Compose
- AWS deployment ready
- GitHub Actions for CI/CD
- Nginx web server

## Project Structure

```
google-scraper/
├── backend/           # Laravel PHP backend
├── frontend/          # React.js frontend application
├── docker/            # Docker configuration files
├── aws/              # AWS deployment configurations
├── .github/          # GitHub Actions workflows
├── start.sh          # Quick start script for Docker setup
└── docker-compose.yml # Docker compose configuration
```

## Installation

### Using start.sh (Recommended)
The easiest way to get started is using our `start.sh` script:

```bash
chmod +x start.sh
./start.sh
```

This script will:
- Copy the environment file if not exists
- Stop any running containers
- Clean up old containers and volumes
- Build and start the containers
- Run database migrations
- Set up application key

### Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/google-scraper.git
cd google-scraper
```

2. Copy environment files:
```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

3. Install dependencies:
```bash
# Backend dependencies
cd backend
composer install

# Frontend dependencies
cd ../frontend
npm install
```

4. Using Docker:
```bash
docker-compose up -d
```

5. Run migrations:
```bash
docker-compose exec backend php artisan migrate
```

## Running Tests

### Backend Tests
- **Framework**: PHPUnit
- **Test Suites**:
  - Unit tests located in `tests/Unit`
  - Feature tests located in `tests/Feature`
- **Run Tests**:
  ```bash
  docker-compose exec backend ./vendor/bin/phpunit
  ```

### Frontend Tests
- **Framework**: Jest with ts-jest preset
- **Test Environment**: jsdom
- **Test Files**: Located in `__tests__` directories with `.test.ts` or `.test.tsx` extension.
- **Run Tests**:
  ```bash
  docker-compose exec frontend npm test
  ```

## API Endpoints

### Authentication
- POST `/api/login` - User login
- POST `/api/register` - User registration

### Protected Routes (Require Authentication)
- GET `/api/user` - Get authenticated user details
- POST `/api/logout` - User logout

### Keywords
- GET `/api/keywords` - List all keywords
- POST `/api/keywords/upload` - Upload keywords
- GET `/api/keywords/{keyword}` - Get specific keyword details
- DELETE `/api/keywords/{keyword}` - Delete a keyword
- POST `/api/keywords/{keyword}/retry` - Retry processing a keyword

## API Documentation

Detailed API documentation is available at:
- Development: `http://localhost:8000/api/documentation`
- Production: `https://your-domain.com/api/documentation`

## Roadmap

### Improvement plans

- Optimized search and pagination support from backednd
- Rate limiting optimization
- Batch processing improvements
- Image search results extraction
- Advanced SERP feature extraction (Featured snippets, Knowledge panels)
- Custom user agent rotation


The API documentation is powered by Swagger/OpenAPI and provides interactive documentation for all available endpoints.

## Application Access

After installation, you can access:
- Backend: `http://localhost:8000`
- Frontend: `http://localhost:3000`

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
