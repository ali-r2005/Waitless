# Waitless API Documentation

## Queue Management API

This documentation provides details about the queue management API endpoints available in the Waitless application. These endpoints allow frontend developers to interact with the queue system, manage customers in queues, and handle queue operations.

### Authentication

All API endpoints require authentication using Laravel Sanctum. Include the authentication token in the request header:

```
Authorization: Bearer {your_token}
```

### Response Format

All API responses follow a consistent format:

```json
{
  "status": "success|error",
  "message": "Description of the operation result (optional)",
  "data": { ... } // Response data (optional)
}
```

### Error Handling

Error responses include appropriate HTTP status codes and error details:

```json
{
  "status": "error",
  "message": "Error description",
  "errors": { ... } // Validation errors (if applicable)
}
```

---

## Queue Management Endpoints

### Queue Resource Endpoints

#### 1. List Queues

Retrieves a list of queues based on the user's role.

- **URL**: `/api/queues`
- **Method**: `GET`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager, business_owner
- **Query Parameters**:
  - `branch_id` (optional): Filter queues by branch
  - `is_active` (optional): Filter by active status

**Response Example**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "branch_id": 5,
      "staff_id": 10,
      "name": "Customer Service Queue",
      "scheduled_date": "2025-05-24",
      "is_active": true,
      "start_time": "09:00",
      "preferences": null,
      "created_at": "2025-05-20T10:30:00.000000Z",
      "updated_at": "2025-05-20T10:30:00.000000Z",
      "branch": {
        "id": 5,
        "name": "Downtown Branch",
        "address": "123 Main St"
      },
      "staff": {
        "id": 10,
        "name": "John Doe"
      }
    }
  ],
  "latecomer_queues": [
    {
      "id": 1,
      "queue_id": 1
    }
  ]
}
```

#### 2. Create Queue

Creates a new queue.

- **URL**: `/api/queues`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "name": "Customer Service Queue",
    "scheduled_date": "2025-05-24",
    "is_active": true,
    "start_time": "09:00",
    "preferences": null
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Queue created successfully",
  "data": {
    "id": 1,
    "branch_id": 5,
    "staff_id": 10,
    "name": "Customer Service Queue",
    "scheduled_date": "2025-05-24",
    "is_active": true,
    "start_time": "09:00",
    "preferences": null,
    "created_at": "2025-05-20T10:30:00.000000Z",
    "updated_at": "2025-05-20T10:30:00.000000Z"
  }
}
```

#### 3. Get Queue Details

Retrieves details of a specific queue including users in the queue.

- **URL**: `/api/queues/{id}`
- **Method**: `GET`
- **Auth Required**: Yes
- **Permissions**: staff (own queues), branch_manager (branch queues), business_owner (business queues)

**Response Example**:
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "branch_id": 5,
    "staff_id": 10,
    "name": "Customer Service Queue",
    "scheduled_date": "2025-05-24",
    "is_active": true,
    "start_time": "09:00",
    "preferences": null,
    "created_at": "2025-05-20T10:30:00.000000Z",
    "updated_at": "2025-05-20T10:30:00.000000Z",
    "branch": {
      "id": 5,
      "name": "Downtown Branch",
      "address": "123 Main St"
    },
    "staff": {
      "id": 10,
      "name": "John Doe"
    },
    "users": [
      {
        "id": 15,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "pivot": {
          "queue_id": 1,
          "user_id": 15,
          "status": "waiting",
          "ticket_number": "TICKET-1",
          "position": 1
        }
      }
    ]
  },
  "latecomer_queues": {
    "id": 1,
    "queue_id": 1,
    "created_at": "2025-05-20T10:30:00.000000Z",
    "updated_at": "2025-05-20T10:30:00.000000Z"
  }
}
```

#### 4. Update Queue

Updates an existing queue.

- **URL**: `/api/queues/{id}`
- **Method**: `PUT` or `PATCH`
- **Auth Required**: Yes
- **Permissions**: staff (own queues), branch_manager (branch queues)
- **Request Body**:
  ```json
  {
    "name": "Updated Queue Name",
    "scheduled_date": "2025-05-25",
    "is_active": true,
    "start_time": "10:00",
    "preferences": null
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Queue updated successfully",
  "data": {
    "id": 1,
    "branch_id": 5,
    "staff_id": 10,
    "name": "Updated Queue Name",
    "scheduled_date": "2025-05-25",
    "is_active": true,
    "start_time": "10:00",
    "preferences": null,
    "created_at": "2025-05-20T10:30:00.000000Z",
    "updated_at": "2025-05-23T15:45:00.000000Z"
  }
}
```

#### 5. Delete Queue

Deletes a queue.

- **URL**: `/api/queues/{id}`
- **Method**: `DELETE`
- **Auth Required**: Yes
- **Permissions**: staff (own queues), branch_manager (branch queues)

**Response Example**:
```json
{
  "status": "success",
  "message": "Queue deleted successfully"
}
```

### Queue Customer Management

#### 1. Add Customer to Queue

Adds a customer to a queue.
use this endpoint to search for a customer and add it to a queue
url `/api/users/search?name={name}`
- **Access**: staff, branch_manager, business_owner
- **Description**: Search for users to add to queue
- **Response**: Array of matching users with his id

- **URL**: `/api/queue-management/add-customer`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "user_id": 15
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer added to queue successfully"
}
```

#### 2. Remove Customer from Queue

Removes a customer from a queue.

- **URL**: `/api/queue-management/remove-customer`
- **Method**: `DELETE`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "user_id": 15
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer removed from queue successfully"
}
```

#### 3. Get Queue Customers

Retrieves all customers in a specific queue.

- **URL**: `/api/queue-management/customers`
- **Method**: `GET`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager, business_owner
- **Query Parameters**:
  - `queue_id` (required): ID of the queue

**Response Example**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 15,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "pivot": {
        "queue_id": 1,
        "user_id": 15,
        "status": "waiting",
        "ticket_number": "TICKET-1",
        "position": 1,
        "served_at": null,
        "late_at": null
      }
    }
  ]
}
```

#### 4. Move Customer in Queue

Changes a customer's position in the queue.

- **URL**: `/api/queue-management/customers/{id}/move`
- **Method**: `PATCH`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager, business_owner
- **URL Parameters**:
  - `id`: The QueueUser pivot ID (not the user ID)
- **Request Body**:
  ```json
  {
    "new_position": 3
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer position updated successfully"
}
```

### Queue Operations

#### 1. Activate Queue

Activates a queue to start serving customers.

- **URL**: `/api/queue-management/activate`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "is_active": true
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Queue activated successfully"
}
```

#### 2. Call Next Customer

Calls the next customer in the queue.

- **URL**: `/api/queue-management/call-next`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "queue_id": 1
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Next customer called successfully",
  "data": {
    "user": {
      "id": 15,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "ticket_number": "TICKET-1"
  }
}
```

#### 3. Complete Serving

Marks the current customer as served and removes them from the queue.

- **URL**: `/api/queue-management/complete-serving`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "user_id": 15,
    "notes": "Customer service completed successfully"
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer service completed successfully"
}
```

### Late Customer Management

#### 1. Mark Customer as Late

Marks a customer as late and moves them to the latecomer queue.

- **URL**: `/api/queue-management/customers/late`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "user_id": 15
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer marked as late successfully"
}
```

#### 2. Get Late Customers

Retrieves all late customers for a specific queue.

- **URL**: `/api/queue-management/customers/late`
- **Method**: `GET`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Query Parameters**:
  - `queue_id` (required): ID of the queue

**Response Example**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 15,
      "name": "Jane Smith",
      "email": "jane@example.com"
    }
  ]
}
```

#### 3. Reinsert Late Customer

Reinserts a late customer back into the main queue at a specified position.

- **URL**: `/api/queue-management/customers/reinsert`
- **Method**: `POST`
- **Auth Required**: Yes
- **Permissions**: staff, branch_manager
- **Request Body**:
  ```json
  {
    "queue_id": 1,
    "user_id": 15,
    "position": 2
  }
  ```

**Response Example**:
```json
{
  "status": "success",
  "message": "Customer reinserted in the queue successfully"
}
```

## Real-time Updates

The Waitless system broadcasts queue updates in real-time to keep customers informed about their position and queue status. Frontend applications should implement event listeners with pusher to receive these updates.

### Queue Update Event

When queue changes occur (customer added, removed, moved, etc.), the system broadcasts updates to all connected clients.

- **Event Name**: `SendUpdate`
- **Channel**: `queue.{queue_id}`
- **Data Format**:
  ```json
  {
    "queue": {
      "id": 1,
      "name": "Customer Service Queue",
      "is_active": true
    },
    "customers": [
      {
        "id": 15,
        "name": "Jane Smith",
        "position": 1,
        "ticket_number": "TICKET-1",
        "status": "waiting"
      }
    ],
    "current_serving": {
      "user_id": null,
      "ticket_number": null
    }
  }
  ```

## Implementation Notes

1. **Position Management**: When a customer is removed or moved in a queue, the system automatically normalizes positions to ensure continuous numbering.

2. **Ticket Numbers**: Ticket numbers are generated automatically when a customer is added to a queue in the format "TICKET-{position}".

3. **Customer Status**: Customers can have the following statuses:
   - `waiting`: In queue waiting to be served
   - `being_served`: Currently being served
   - `served`: Service completed
   - `late`: Missed their turn and moved to latecomer queue

4. **Notifications**: The system sends notifications to customers when they are:
   - Added to a queue
   - Called for service
   - Marked as late

5. **Role-Based Access**: Different endpoints have different permission requirements based on user roles:
   - `staff`: Can manage their own queues
   - `branch_manager`: Can manage all queues in their branch
   - `business_owner`: Can view all queues in the business