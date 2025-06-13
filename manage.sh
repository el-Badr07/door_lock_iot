#!/bin/bash

# Door Lock IoT Management Script
# Usage: ./manage.sh [command]

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        echo -e "${RED}Docker is not installed. Please install Docker first.${NC}"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        echo -e "${RED}Docker Compose is not installed. Please install Docker Compose.${NC}"
        exit 1
    fi
}

# Start the application
start() {
    check_docker
    echo -e "${GREEN}Starting Door Lock IoT application...${NC}"
    
    # Check if .env file exists
    if [ ! -f .env ]; then
        echo -e "${YELLOW}No .env file found. Creating from .env.example...${NC}"
        cp .env.example .env
        # The init.php script will handle secret generation if needed.
    fi
    
    # Start services
    docker-compose up -d
    
    # Wait for services to start
    echo -e "${YELLOW}Waiting for services to start...${NC}"
    sleep 10
    
    # Initialize database and generate secrets if missing
    echo -e "${YELLOW}Initializing database and ensuring secrets are set...${NC}"
    docker-compose exec -T backend php init.php
    
    echo -e "\n${GREEN}Application started successfully!${NC}"
    echo -e "\nAccess the application at: ${YELLOW}http://localhost${NC}"
    echo -e "Access Adminer (database UI) at: ${YELLOW}http://localhost:8080${NC}"
    echo -e "\nDefault admin credentials:"
    echo -e "  Email: ${YELLOW}admin@example.com${NC}"
    echo -e "  Password: ${YELLOW}admin123${NC}"
    echo -e "\nRun ${YELLOW}./manage.sh logs${NC} to view logs"
    echo -e "Run ${YELLOW}./manage.sh stop${NC} to stop the application"
}

# Stop the application
stop() {
    check_docker
    echo -e "${YELLOW}Stopping Door Lock IoT application...${NC}"
    docker-compose down
}

# Restart the application
restart() {
    stop
    start
}

# View logs
logs() {
    check_docker
    docker-compose logs -f
}

# Run database migrations
migrate() {
    check_docker
    echo -e "${YELLOW}Running database migrations...${NC}"
    docker-compose exec -T backend php database/run_migrations.php
}

# Run tests
test() {
    check_docker
    echo -e "${YELLOW}Running API tests...${NC}"
    docker-compose exec -T backend php test_api.php
}

# Show help
help() {
    echo -e "\n${GREEN}Door Lock IoT Management Script${NC}"
    echo -e "\nUsage: ${YELLOW}./manage.sh [command]${NC}\n"
    echo "Available commands:"
    echo "  ${YELLOW}start${NC}     - Start the application"
    echo "  ${YELLOW}stop${NC}      - Stop the application"
    echo "  ${YELLOW}restart${NC}   - Restart the application"
    echo "  ${YELLOW}logs${NC}      - View application logs"
    echo "  ${YELLOW}migrate${NC}   - Run database migrations"
    echo "  ${YELLOW}test${NC}      - Run API tests"
    echo "  ${YELLOW}help${NC}      - Show this help message"
    echo ""
}

# Check if no command provided
if [ $# -eq 0 ]; then
    help
    exit 1
fi

# Parse command
case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    logs)
        logs
        ;;
    migrate)
        migrate
        ;;
    test)
        test
        ;;
    help|--help|-h)
        help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        help
        exit 1
        ;;
esac

exit 0
