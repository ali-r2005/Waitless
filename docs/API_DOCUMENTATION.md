# Queue API: CRUD Operations

This section documents only the CRUD (Create, Read, Update, Delete) endpoints for queues, as implemented in `QueueController`. All endpoints require authentication and role-based access control.

---

## List Queues
- **Endpoint:** `GET /api/queues`
- **Description:** Returns a list of queues filtered by the user's role and optional query parameters (`branch_id`, `is_active`).
- **Response:**
  - `status`: `success` or `error`
  - `data`: array of queue objects
  - `latecomer_queues`: array of latecomer queue objects

---

## Create Queue
- **Endpoint:** `POST /api/queues`
- **Description:** Creates a new queue. Only staff and branch managers can create queues for their branch.
- **Body:**
  ```json
  {
    "name": "string (required)",
    "scheduled_date": "YYYY-MM-DD (optional)",
    "is_active": true|false (optional),
    "start_time": "HH:MM (optional)",
    "preferences": "json string (optional)"
  }
  ```
- **Response:**
  - `status`: `success` or `error`
  - `message`: string
  - `data`: created queue object

---

## Show Queue
- **Endpoint:** `GET /api/queues/{id}`
- **Description:** Returns details of a specific queue, including branch, staff, and users. Access is restricted by role.
- **Response:**
  - `status`: `success` or `error`
  - `data`: queue object
  - `latecomer_queues`: latecomer queue object for this queue

---

## Update Queue
- **Endpoint:** `PUT /api/queues/{id}`
- **Description:** Updates a queue. Only staff and branch managers can update queues they have access to.
- **Body:**
  ```json
  {
    "name": "string (optional)",
    "scheduled_date": "YYYY-MM-DD (optional)",
    "is_active": true|false (optional)",
    "start_time": "HH:MM (optional)",
    "preferences": "json string (optional)"
  }
  ```
- **Response:**
  - `status`: `success` or `error`
  - `message`: string
  - `data`: updated queue object

---

## Delete Queue
- **Endpoint:** `DELETE /api/queues/{id}`
- **Description:** Deletes a queue. Only staff and branch managers can delete queues they have access to.
- **Response:**
  - `status`: `success` or `error`
  - `message`: string

---

## Notes
- All endpoints return JSON responses.
- Error responses include a `status: error` and a `message` field.
- Role-based access is enforced; users without the correct role will receive a 403 Forbidden response.
- For more details on request/response formats, see the controller code or ask the backend team.
