# Google Scraper

A powerful web application for scraping and analyzing Google search results with automated data collection capabilities.

## Features

- Automated Google search results scraping
- Bulk keyword processing
- CSV import/export functionality
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
- Modern UI components
- Axios for API requests
- State management with React Context/Redux

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
- POST `/api/auth/login` - User login
- POST `/api/auth/register` - User registration
- POST `/api/auth/logout` - User logout

### Scraping
- POST `/api/scrape/start` - Start scraping job
- GET `/api/scrape/status/{jobId}` - Get scraping job status
- POST `/api/scrape/bulk` - Start bulk scraping job
- GET `/api/scrape/results` - Get scraping results

### Keywords
- GET `/api/keywords` - List all keywords
- POST `/api/keywords/import` - Import keywords from CSV
- GET `/api/keywords/export` - Export keywords to CSV

### Results
- GET `/api/results` - Get all scraping results
- GET `/api/results/{id}` - Get specific result
- GET `/api/results/export` - Export results to CSV

## API Documentation

Detailed API documentation is available at:
- Development: `http://localhost:8000/api/documentation`
- Production: `https://your-domain.com/api/documentation`

The API documentation is powered by Swagger/OpenAPI and provides interactive documentation for all available endpoints.

## Application Access

After installation, you can access:
- Backend: `http://localhost:8000`
- Frontend: `http://localhost:3000`

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
