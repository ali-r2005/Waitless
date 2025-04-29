# Waitless - Queue Management System

<p align="center">
<img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

## About Waitless

Waitless is a comprehensive queue management system designed to streamline customer flow in various service environments. Built with Laravel, it provides real-time queue management, customer tracking, and service optimization features.

### Key Features

- **Real-time Queue Management**
  - Add, remove, and reorder customers in queues
  - Track customer positions and waiting times
  - Real-time updates for all queue participants

- **Smart Queue Operations**
  - Automatic position normalization
  - Late customer handling
  - Customer reinsertion capabilities
  - Estimated waiting time calculations

- **Role-Based Access Control**
  - Business Owner access
  - Branch Manager privileges
  - Staff-level operations

- **Customer Notifications**
  - Real-time position updates
  - Service status notifications
  - Estimated waiting time alerts

## System Architecture

### Core Components

1. **Queue Management**
   - Queue creation and activation
   - Customer position tracking
   - Service completion tracking

2. **Customer Management**
   - Customer addition and removal
   - Position movement
   - Late customer handling
   - Reinsertion capabilities

3. **Real-time Updates**
   - WebSocket-based notifications
   - Position updates
   - Service status changes

### Database Structure

- **Queues**: Store queue information and status
- **QueueUser**: Manages customer-queue relationships
- **ServedCustomer**: Tracks completed services
- **LatecomerQueue**: Handles late customer management

## API Documentation

### Queue Management Endpoints

```
POST   /api/queue-management/add-customer
DELETE /api/queue-management/remove-customer
GET    /api/queue-management/customers
POST   /api/queue-management/activate
POST   /api/queue-management/call-next
POST   /api/queue-management/complete-serving
PATCH  /api/queue-management/customers/{id}/move
POST   /api/queue-management/customers/reinsert
POST   /api/queue-management/customers/late
GET    /api/queue-management/customers/late
```

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Configure environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Start the server:
   ```bash
   php artisan serve
   ```

## Requirements

- PHP >= 8.1
- Laravel >= 9.x
- MySQL >= 5.7
- Redis (for real-time features)

## Security

All API endpoints are protected by:
- Laravel Sanctum authentication
- Role-based access control
- Input validation
- Proper error handling

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
