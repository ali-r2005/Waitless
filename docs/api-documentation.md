# Waitless API Documentation

## Authentication

All API endpoints require authentication using Laravel Sanctum tokens. Include the token in the Authorization header:
```
Authorization: Bearer {token}
```

## Role-Based Access Control

The API implements role-based access control with the following roles:
- `business_owner`: Full access to business management
- `branch_manager`: Access to branch-specific operations
- `staff`: Limited access to assigned resources
- `guest`: Basic access

## API Endpoints

### Authentication

#### Register User
- **POST** `/api/register`
- **Description**: Register a new user
- **Request Body**:
  ```json
  {
    "name": "string",
    "email": "string",
    "phone": "string",
    "password": "string",
    "password_confirmation": "string",
    "role": "business_owner", // Optional, defaults to 'guest'
    "business_name": "string", // Required if role is business_owner
    "industry": "string", // Required if role is business_owner
    "logo": "file" // Optional
  }
  ```
- **Response**: User object with authentication token

### Business Management

////#### Get Business Information
- **GET** `/api/business`
- **Access**: business_owner
- **Description**: Get business details
- **Response**: Business object with branches and staff

### Branch Management

///////#### List Branches
- **GET** `/api/branches`
- **Access**: business_owner
- **Description**: List all branches
- **Response**: Array of branch objects

//////#### Create Branch
- **POST** `/api/branches`
- **Access**: business_owner
- **Request Body**:
  ```json
  {
    "name": "string",
    "parent_id": "integer" // Optional
  }
  ```
- **Response**: Created branch object

//////#### Get Branch Hierarchy
- **GET** `/api/branches/{branch}/hierarchy`
- **Access**: business_owner
- **Description**: Get branch hierarchy structure
- **Response**: Nested branch structure

/////#### Move Sub-branches
- **POST** `/api/branches/{branch}/move-sub-branches`
- **Access**: business_owner
- **Request Body**:
  ```json
  {
    "target_branch_id": "integer"
  }
  ```
- **Response**: Success message

### Staff Management

#### Search Users
- **GET** `/api/users/search?name={name}`
- **Access**: branch_manager, business_owner
- **Description**: Search for users to add as staff
- **Response**: Array of matching users

#### Add User to Staff
- **POST** `/api/users/{user}/add-to-staff`
- **Access**: branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "role_id": "integer",
    "branch_id": "integer",
    "business_id": "integer"
  }
  ```
- **Response**: Created staff record

#### Remove User from Staff
- **DELETE** `/api/users/{user}/remove-from-staff`
- **Access**: branch_manager, business_owner
- **Response**: Success message

#### Assign Branch Manager
- **POST** `/api/users/{user}/branch-manager`
- **Access**: business_owner
- **Response**: Success message

#### List Branch Managers
- **GET** `/api/branch-managers`
- **Access**: business_owner
- **Response**: Array of branch managers

#### Remove Branch Manager
- **DELETE** `/api/branch-managers/{user}`
- **Access**: business_owner
- **Response**: Success message

### Role Management

#### List Roles
- **GET** `/api/roles`
- **Access**: business_owner
- **Response**: Array of role objects

#### Create Role
- **POST** `/api/roles`
- **Access**: business_owner
- **Request Body**:
  ```json
  {
    "name": "string",
    "business_id": "integer"
  }
  ```
- **Response**: Created role object

#### Update Role
- **PUT** `/api/roles/{role}`
- **Access**: business_owner
- **Request Body**:
  ```json
  {
    "name": "string",
    "business_id": "integer"
  }
  ```
- **Response**: Updated role object

#### Delete Role
- **DELETE** `/api/roles/{role}`
- **Access**: business_owner
- **Response**: Success message

### Queue Management

#### List Queues
- **GET** `/api/queues`
- **Access**: staff, branch_manager, business_owner
- **Description**: Lists queues based on user role
  - Staff: Only their created queues
  - Branch Manager: All queues in their branch
  - Business Owner: All queues in their business
- **Response**: Array of queue objects

#### Create Queue
- **POST** `/api/queues`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "name": "string",
    "branch_id": "integer",
    "description": "string", // Optional
    "max_capacity": "integer" // Optional
  }
  ```
- **Response**: Created queue object

#### Get Queue Details
- **GET** `/api/queues/{queue}`
- **Access**: staff, branch_manager, business_owner
- **Description**: Get detailed queue information
- **Response**: Queue object with related data

#### Update Queue
- **PUT** `/api/queues/{queue}`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "name": "string",
    "description": "string",
    "max_capacity": "integer",
    "status": "string"
  }
  ```
- **Response**: Updated queue object

#### Delete Queue
- **DELETE** `/api/queues/{queue}`
- **Access**: staff, branch_manager, business_owner
- **Response**: Success message

### Queue Operations

#### Search Users in Queue
- **GET** `/api/queue-management/search`
- **Access**: staff, branch_manager, business_owner
- **Description**: Search for users in the queue system
- **Response**: Array of matching users

#### Add Customer to Queue
- **POST** `/api/queue-management/add-customer`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer",
    "customer_id": "integer",
    "service_type": "string" // Optional
  }
  ```
- **Response**: Queue entry object

#### Remove Customer from Queue
- **DELETE** `/api/queue-management/remove-customer`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer",
    "customer_id": "integer"
  }
  ```
- **Response**: Success message

#### Get Queue Customers
- **GET** `/api/queue-management/customers`
- **Access**: staff, branch_manager, business_owner
- **Description**: Get list of customers in a queue
- **Response**: Array of customer objects with queue positions

#### Activate Queue
- **POST** `/api/queue-management/activate`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer"
  }
  ```
- **Response**: Updated queue status

#### Call Next Customer
- **POST** `/api/queue-management/call-next`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer"
  }
  ```
- **Response**: Called customer details

#### Complete Serving
- **POST** `/api/queue-management/complete-serving`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer",
    "customer_id": "integer"
  }
  ```
- **Response**: Success message

#### Move Customer Position
- **PATCH** `/api/queue-management/customers/{id}/move`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "new_position": "integer"
  }
  ```
- **Response**: Updated customer position

#### Reinsert Customer
- **POST** `/api/queue-management/customers/reinsert`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer",
    "customer_id": "integer",
    "position": "integer" // Optional
  }
  ```
- **Response**: Updated queue entry

#### Mark Customer as Late
- **POST** `/api/queue-management/customers/late`
- **Access**: staff, branch_manager, business_owner
- **Request Body**:
  ```json
  {
    "queue_id": "integer",
    "customer_id": "integer"
  }
  ```
- **Response**: Updated customer status

#### Get Late Customers
- **GET** `/api/queue-management/customers/late`
- **Access**: staff, branch_manager, business_owner
- **Description**: Get list of customers marked as late
- **Response**: Array of late customer objects

## Error Responses

The API uses standard HTTP status codes and returns JSON responses in the following format:

```json
{
  "status": "error",
  "message": "Error description",
  "errors": {
    "field": ["Error message"]
  }
}
```

Common status codes:
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error 