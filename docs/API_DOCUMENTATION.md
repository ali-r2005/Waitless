# Waitless API Documentation

## Base URL
```
http://your-api-domain.com
```

## Authentication
The API uses Laravel Sanctum for authentication. All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Register User
Register a new user account.

**URL:** `/register`  
**Method:** `POST`  
**Auth Required:** No

#### Request Body
```json
{
    "name": "string",
    "email": "string",
    "phone": "string",
    "password": "string",
    "password_confirmation": "string"
}
```

#### Business Owner Registration
```json
{
    "name": "string",
    "email": "string",
    "phone": "string",
    "password": "string",
    "password_confirmation": "string",
    "role": "business_owner",
    "business_name": "string",
    "industry": "string",
    "logo": "file" // optional, max 2MB
}
```

#### Success Response
```json
{
    "status": "success",
    "message": "User registered successfully",
    "access_token": "string",
    "token_type": "Bearer",
    "user": {
        "id": "integer",
        "name": "string",
        "email": "string",
        "phone": "string",
        "role": "string"
    }
}
```

### 2. Login
Authenticate user and get access token.

**URL:** `/login`  
**Method:** `POST`  
**Auth Required:** No

#### Request Body
```json
{
    "email": "string",
    "password": "string"
}
```

#### Success Response
```json
{
    "access_token": "string",
    "token_type": "Bearer",
    "user": {
        "id": "integer",
        "name": "string",
        "email": "string",
        "phone": "string",
        "role": "string"
    },
    "status": "Login successful"
}
```

### 3. Logout
Invalidate the current access token.

**URL:** `/logout`  
**Method:** `POST`  
**Auth Required:** Yes

#### Success Response
```json
{
    "message": "Logout successful"
}
```

### 4. Forgot Password
Request password reset link.

**URL:** `/forgot-password`  
**Method:** `POST`  
**Auth Required:** No

#### Request Body
```json
{
    "email": "string"
}
```

#### Success Response
```json
{
    "status": "string" // Password reset link sent message
}
```

### 5. Reset Password
Reset password using token from email.

**URL:** `/reset-password`  
**Method:** `POST`  
**Auth Required:** No

#### Request Body
```json
{
    "token": "string",
    "email": "string",
    "password": "string",
    "password_confirmation": "string"
}
```

#### Success Response
```json
{
    "status": "string" // Password reset success message
}
```

### 6. Email Verification
Send email verification link.

**URL:** `/email/verification-notification`  
**Method:** `POST`  
**Auth Required:** Yes

#### Success Response
```json
{
    "status": "verification-link-sent"
}
```

### 7. Verify Email
Verify email using the link from email.

**URL:** `/verify-email/{id}/{hash}`  
**Method:** `GET`  
**Auth Required:** Yes

#### Success Response
Redirects to frontend with verification status.

## Error Responses

### Validation Error (422)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "field": ["error message"]
    }
}
```

### Authentication Error (401)
```json
{
    "message": "Invalid login credentials"
}
```

### Email Verification Error (409)
```json
{
    "message": "Your email address is not verified."
}
```

## Important Notes

1. All API requests should include:
   ```
   Accept: application/json
   Content-Type: application/json
   ```

2. For protected routes, include the Bearer token:
   ```
   Authorization: Bearer {token}
   ```

3. Password requirements:
   - Minimum 8 characters
   - Must contain at least one letter
   - Must contain at least one number
   - Must contain at least one special character

4. Rate limiting:
   - Login attempts are limited to 5 attempts per minute
   - Email verification links are limited to 6 attempts per minute

5. Token expiration:
   - Access tokens do not expire by default
   - Password reset tokens expire after 60 minutes

6. CORS is configured to allow requests from:
   - `http://localhost:3000` (development)
   - Your configured `FRONTEND_URL` (production)

## User Roles
The system supports the following user roles:
- `system_admin`: System administrator
- `business_owner`: Business owner
- `branch_manager`: Branch manager
- `staff`: Regular staff member
- `customer`: Customer
- `guest`: Default role for new users

## Security Considerations

1. Always use HTTPS in production
2. Store tokens securely in the frontend application
3. Implement proper token refresh mechanism
4. Handle token expiration gracefully
5. Implement proper error handling for all API responses
6. Follow security best practices for password storage and transmission

## Getting Started

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy `.env.example` to `.env` and configure your environment variables
4. Generate application key:
   ```bash
   php artisan key:generate
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```
6. Start the development server:
   ```bash
   php artisan serve
   ```

## Support

For any questions or issues, please contact the development team or create an issue in the repository. 