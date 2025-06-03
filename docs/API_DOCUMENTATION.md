# Waitless Dashboard Implementation Guide

This document provides guidelines for implementing role-specific dashboards in the Waitless queue management system. It includes dashboard components, data requirements, and API endpoints for each user role.

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Real-time Updates](#real-time-updates)
4. [Business Owner Dashboard](#business-owner-dashboard)
5. [Branch Manager Dashboard](#branch-manager-dashboard)
6. [Staff Dashboard](#staff-dashboard)
7. [Common Components](#common-components)
8. [Implementation Best Practices](#implementation-best-practices)
9. [API Reference](#api-reference)

## Overview

The Waitless system features three distinct dashboard interfaces tailored to specific user roles:

- **Business Owner Dashboard**: High-level overview of all branches and business performance
- **Branch Manager Dashboard**: Detailed view of branch operations and staff performance
- **Staff Dashboard**: Queue management tools and real-time customer service interface

Each dashboard interfaces with the Waitless API to retrieve data and perform operations. This document outlines the components, data requirements, and corresponding API endpoints for each dashboard.

## Authentication

All API endpoints require authentication using Laravel Sanctum. Include the authentication token in the request header:

```
Authorization: Bearer {your_token}
```

Frontend implementation should:
1. Store the token securely (HttpOnly cookies recommended)
2. Include the token in all API requests
3. Handle token refresh when needed
4. Redirect to login when authentication fails

## Real-time Updates

Dashboard components should update in real-time using Laravel Echo and Pusher. Two main event types are broadcast:

### 1. Customer Updates (`SendUpdate` Event)
- **Channel**: `private-user.{user_id}`
- **Use**: Customer-specific updates about queue position and waiting time

### 2. Staff Queue Updates (`StaffQueueUpdate` Event)
- **Channel**: `private-staff.queue.{queue_id}`
- **Use**: Queue statistics for staff and branch managers
- **Data Format**:

```json
{
  "queue_id": 1,
  "queue_name": "Customer Service Queue",
  "is_active": true,
  "is_paused": false,
  "queue_state": "active",
  "current_serving": {
    "id": 15,
    "name": "Jane Smith",
    "phone": "1234567890",
    "email": "jane@example.com",
    "ticket_number": "TICKET-1",
    "status": "serving"
  },
  "total_customers": 5,
  "average_service_time": 180,
  "waiting_customers": 4,
  "next_available_customer": {
    "id": 16,
    "name": "John Doe",
    "ticket_number": "TICKET-2"
  },
  "timestamp": "2025-06-01T22:30:00.000000Z"
}
```

### Echo Client Setup

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: process.env.NEXT_PUBLIC_PUSHER_APP_KEY,
  cluster: process.env.NEXT_PUBLIC_PUSHER_APP_CLUSTER,
  forceTLS: true,
  authorizer: (channel) => {
    return {
      authorize: (socketId, callback) => {
        fetch('/api/broadcasting/auth', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name
          })
        })
        .then(response => response.json())
        .then(data => {
          callback(false, data);
        })
        .catch(error => {
          callback(true, error);
        });
      }
    };
  }
});
```

## Business Owner Dashboard

### Purpose
Provide a high-level overview of all branches and business performance metrics to inform strategic decisions.

### Main Components

#### 1. Business Overview Card
- **Data Elements**:
  - Total branches
  - Total active queues
  - Total customers served today
  - Business-wide average waiting time
- **API Endpoints**:
  - `GET /api/branches` - Get all branches
  - `GET /api/queues?is_active=true` - Get all active queues
  - Custom aggregation needed (combine data from multiple endpoints)

#### 2. Branch Performance Comparison
- **Data Elements**:
  - Branch ranking by customer volume
  - Branch average waiting times
  - Branch staff efficiency
- **API Endpoints**:
  - `GET /api/branches` - Get all branches
  - `GET /api/branches/{branch}/hierarchy` - For branch structure
  - Custom server-side aggregation of served customer data per branch needed

#### 3. Queue Utilization Chart
- **Data Elements**:
  - Queue usage across branches
  - Active vs. inactive queues
  - Peak hours visualization
- **API Endpoints**:
  - `GET /api/queues` - Get all queues
  - Custom time-based analysis needed

#### 4. Branch Management Panel
- **Data Elements**:
  - Branch hierarchy
  - Branch manager assignments
  - Create/edit branch capabilities
- **API Endpoints**:
  - `GET /api/branches` - List branches
  - `POST /api/branches` - Create branch
  - `PUT /api/branches/{branch}` - Update branch
  - `DELETE /api/branches/{branch}` - Delete branch
  - `GET /api/branches/{branch}/hierarchy` - Get branch hierarchy
  - `POST /api/branches/{branch}/move-sub-branches` - Reorganize branches
  - `GET /api/branch-managers` - List branch managers
  - `POST /api/users/{user}/branch-manager` - Assign branch manager
  - `DELETE /api/branch-managers/{user}` - Remove branch manager

#### 5. Alert Dashboard
- **Data Elements**:
  - Branches with excessive wait times
  - Paused queues
  - Inactive scheduled queues
- **API Endpoints**:
  - `GET /api/queues?is_paused=true` - Get paused queues
  - Custom threshold analysis needed

### Sample Layout
```
+----------------------------------+----------------------------------+
|                                  |                                  |
|      Business Overview           |    Branch Performance Chart      |
|                                  |                                  |
+----------------------------------+----------------------------------+
|                                                                     |
|                     Queue Utilization Chart                         |
|                                                                     |
+----------------------------------+----------------------------------+
|                                  |                                  |
|    Branch Management Panel       |         Alert Dashboard          |
|                                  |                                  |
+----------------------------------+----------------------------------+
```

## Branch Manager Dashboard

### Purpose
Provide detailed information about branch operations, queue management, and staff performance to optimize daily operations.

### Main Components

#### 1. Branch Overview Card
- **Data Elements**:
  - Total active queues in branch
  - Total customers waiting
  - Customers served today
  - Average waiting time today
- **API Endpoints**:
  - `GET /api/queues?branch_id={id}` - Get branch queues
  - `GET /api/queue-management/customers?queue_id={id}` - For each queue
  - `GET /api/queue-management/customers/served-today?queue_id={id}` - For each queue

#### 2. Active Queues Management
- **Data Elements**:
  - List of all active queues
  - Queue status (active/paused)
  - Current queue length
  - Staff assignment
- **API Endpoints**:
  - `GET /api/queues?branch_id={id}&is_active=true` - Get active queues
  - `POST /api/queue-management/activate` - Activate/deactivate queue
  - `POST /api/queue-management/pause` - Pause queue
  - `POST /api/queue-management/resume` - Resume queue

#### 3. Staff Performance Metrics
- **Data Elements**:
  - Staff list
  - Customers served per staff
  - Average service time per staff
  - Queue handling metrics
- **API Endpoints**:
  - `GET /api/staff` - Get staff list
  - `GET /api/staff/{user}` - Get staff details
  - Custom aggregation of served customer data by staff member needed

#### 4. Queue Creation Panel
- **Data Elements**:
  - Create new queues
  - Schedule future queues
  - Configure queue auto-activation
- **API Endpoints**:
  - `POST /api/queues` - Create queue

#### 5. Late Customer Management
- **Data Elements**:
  - List of late customers
  - Options to reinsert customers
- **API Endpoints**:
  - `GET /api/queue-management/customers/late?queue_id={id}` - Get late customers
  - `POST /api/queue-management/customers/reinsert` - Reinsert late customer

### Sample Layout
```
+----------------------------------+----------------------------------+
|                                  |                                  |
|      Branch Overview             |    Active Queues Management      |
|                                  |                                  |
+----------------------------------+----------------------------------+
|                                  |                                  |
|    Staff Performance Metrics     |    Queue Creation Panel          |
|                                  |                                  |
+----------------------------------+----------------------------------+
|                                                                     |
|                     Late Customer Management                        |
|                                                                     |
+----------------------------------+----------------------------------+
```

## Staff Dashboard

### Purpose
Provide focused tools for managing assigned queues and serving customers efficiently.

### Main Components

#### 1. Current Queue Status Card
- **Data Elements**:
  - Queue state (active/paused/inactive)
  - Currently serving customer
  - Next customer in line
  - Waiting customers count
- **API Endpoints**:
  - `GET /api/queues/{id}` - Get queue details
  - **Real-time Updates**: Subscribe to `private-staff.queue.{queue_id}` channel

#### 2. Customer Service Panel
- **Data Elements**:
  - Current customer details
  - Call next customer button
  - Complete service button
  - Mark as late button
- **API Endpoints**:
  - `POST /api/queue-management/call-next` - Call next customer
  - `POST /api/queue-management/complete-serving` - Complete service
  - `POST /api/queue-management/customers/late` - Mark customer as late

#### 3. Queue Control Panel
- **Data Elements**:
  - Pause/resume queue controls
  - Customer reordering interface
  - Add walk-in customer
- **API Endpoints**:
  - `POST /api/queue-management/pause` - Pause queue
  - `POST /api/queue-management/resume` - Resume queue
  - `PATCH /api/queue-management/customers/{id}/move` - Move customer
  - `POST /api/queue-management/add-customer` - Add customer

#### 4. Today's Performance Stats
- **Data Elements**:
  - Customers served today
  - Average service time
  - Average waiting time
- **API Endpoints**:
  - `GET /api/queue-management/customers/served-today?queue_id={id}` - Get served customers with stats

#### 5. Queue Customer List
- **Data Elements**:
  - List of all customers in queue
  - Status (waiting/serving)
  - Position and ticket number
- **API Endpoints**:
  - `GET /api/queue-management/customers?queue_id={id}` - Get queue customers
  - **Real-time Updates**: Subscribe to `private-staff.queue.{queue_id}` channel

### Sample Layout
```
+----------------------------------+----------------------------------+
|                                  |                                  |
|    Current Queue Status          |    Customer Service Panel        |
|                                  |                                  |
+----------------------------------+----------------------------------+
|                                  |                                  |
|    Queue Control Panel           |    Today's Performance Stats     |
|                                  |                                  |
+----------------------------------+----------------------------------+
|                                                                     |
|                     Queue Customer List                             |
|                                                                     |
+----------------------------------+----------------------------------+
```

## Common Components

These components can be shared across multiple dashboards:

### 1. Queue Status Indicator
- **Description**: Visual indicator showing queue state (active, paused, inactive)
- **Data Source**: Queue state from API or real-time updates
- **Implementation**: Color-coded badge with text

### 2. Notification Center
- **Description**: Display system and user notifications
- **Data Source**: User notifications API
- **Implementation**: Dropdown menu with unread count

### 3. User Profile Menu
- **Description**: User information and logout
- **Data Source**: Current user data
- **Implementation**: Dropdown menu with avatar

## Implementation Best Practices

### State Management
- Use Redux or React Context for global state
- Implement optimistic UI updates for better UX
- Cache data where appropriate to reduce API calls

### Real-time Updates
- Establish Echo connections early in the application lifecycle
- Implement reconnection logic for network interruptions
- Use skeleton loaders while waiting for initial data

### Responsive Design
- Implement mobile-first approach
- Use responsive grid layouts
- Consider different device capabilities

### Error Handling
- Implement global error boundary
- Show user-friendly error messages
- Log errors to server for monitoring

### Performance
- Implement pagination for large data sets
- Use virtualized lists for long customer queues
- Optimize re-renders with memoization

## API Reference

### Queue Resource Endpoints

#### 1. List Queues
- **URL**: `/api/queues`
- **Method**: `GET`
- **Query Parameters**:
  - `branch_id` (optional): Filter queues by branch
  - `is_active` (optional): Filter by active status
  - `is_paused` (optional): Filter by paused status

#### 2. Create Queue
- **URL**: `/api/queues`
- **Method**: `POST`

#### 3. Get Queue Details
- **URL**: `/api/queues/{id}`
- **Method**: `GET`

#### 4. Update Queue
- **URL**: `/api/queues/{id}`
- **Method**: `PUT` or `PATCH`

#### 5. Delete Queue
- **URL**: `/api/queues/{id}`
- **Method**: `DELETE`

### Queue Management Endpoints

#### 1. Add Customer to Queue
- **URL**: `/api/queue-management/add-customer`
- **Method**: `POST`

#### 2. Remove Customer from Queue
- **URL**: `/api/queue-management/remove-customer`
- **Method**: `DELETE`

#### 3. Get Queue Customers
- **URL**: `/api/queue-management/customers`
- **Method**: `GET`

#### 4. Move Customer in Queue
- **URL**: `/api/queue-management/customers/{id}/move`
- **Method**: `PATCH`

#### 5. Activate Queue
- **URL**: `/api/queue-management/activate`
- **Method**: `POST`

#### 6. Call Next Customer
- **URL**: `/api/queue-management/call-next`
- **Method**: `POST`

#### 7. Complete Serving
- **URL**: `/api/queue-management/complete-serving`
- **Method**: `POST`

#### 8. Pause Queue
- **URL**: `/api/queue-management/pause`
- **Method**: `POST`

#### 9. Resume Queue
- **URL**: `/api/queue-management/resume`
- **Method**: `POST`

### Late Customer Management

#### 1. Mark Customer as Late
- **URL**: `/api/queue-management/customers/late`
- **Method**: `POST`

#### 2. Get Late Customers
- **URL**: `/api/queue-management/customers/late`
- **Method**: `GET`

#### 3. Reinsert Late Customer
- **URL**: `/api/queue-management/customers/reinsert`
- **Method**: `POST`

### Analytics Endpoints

#### 1. Get Customers Served Today
- **URL**: `/api/queue-management/customers/served-today`
- **Method**: `GET`

### Branch Management Endpoints

#### 1. List Branches
- **URL**: `/api/branches`
- **Method**: `GET`

#### 2. Get Branch Hierarchy
- **URL**: `/api/branches/{branch}/hierarchy`
- **Method**: `GET`

#### 3. Move Sub-branches
- **URL**: `/api/branches/{branch}/move-sub-branches`
- **Method**: `POST`

### Staff Management Endpoints

#### 1. List Staff
- **URL**: `/api/staff`
- **Method**: `GET`

#### 2. Get Staff Details
- **URL**: `/api/staff/{user}`
- **Method**: `GET`

#### 3. Search Users
- **URL**: `/api/users/search`
- **Method**: `GET`
- **Query Parameters**:
  - `name` (optional): Search users by name

---

This implementation guide provides a comprehensive framework for building role-specific dashboards in the Waitless queue management system. Frontend developers should adapt these recommendations based on specific project requirements and design guidelines. 