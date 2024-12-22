# Google Scraper

A powerful web application for scraping and analyzing Google search results with automated data collection capabilities.

## Demo Links

- Frontend Demo: [http://googlescraper.s3-website-ap-southeast-1.amazonaws.com](http://googlescraper.s3-website-ap-southeast-1.amazonaws.com)
- Backend API: [http://ec2-18-138-248-220.ap-southeast-1.compute.amazonaws.com](http://ec2-18-138-248-220.ap-southeast-1.compute.amazonaws.com)
- API Documentation: [http://ec2-18-138-248-220.ap-southeast-1.compute.amazonaws.com/api/documentation](http://ec2-18-138-248-220.ap-southeast-1.compute.amazonaws.com/api/documentation)

### Test Account
```
Email: test@example.com
Password: 1234
```

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

### System Requirements
- PHP >= 8.2
- Docker (Latest version with Docker Compose V2 support)
- Node.js >= 16

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
  - Automated frontend deployment with version tags (F-v*)
  - Continuous deployment to S3 bucket
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

5. Run migrations and seed the database:
```bash
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed  # This will populate the database with sample keywords
```

## Deployment

### Release Tags
The project uses separate tagging conventions for frontend and backend deployments:

- Frontend releases: Use `F-v*` tags (e.g., `F-v1.0.0`)
  - Requires passing test suite
  - Must pass ESLint code quality checks
- Backend releases: Use `B-v*` tags (e.g., `B-v1.0.0`)

To deploy a new version:

```bash
# For frontend deployment
git tag F-v1.0.0
git push origin F-v1.0.0  # This will trigger tests and linting before deployment

# For backend deployment
git tag B-v1.0.0
git push origin B-v1.0.0
```

This separation allows for independent versioning and deployment of frontend and backend components. Frontend deployments include automated quality checks to ensure code reliability and consistency.

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

- Optimized search and pagination support from backend
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
