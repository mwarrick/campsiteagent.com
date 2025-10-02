# CampsiteAgent.com API Documentation

**Version 2.0** - October 2025

This document provides comprehensive documentation for all API endpoints in CampsiteAgent.com.

## Base URL

```
Production: https://campsiteagent.com
Development: http://127.0.0.1:8080
```

## Authentication

CampsiteAgent.com uses a passwordless authentication system via Gmail API. Users receive login links via email.

### Authentication Flow

1. **Register**: `POST /api/register`
2. **Login**: `POST /api/login` (sends email with login link)
3. **Verify**: `GET /api/auth/callback?token=...` (completes login)
4. **Session**: Subsequent requests use session-based authentication

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": { ... }
}
```

## Authentication Endpoints

### Register User

**POST** `/api/register`

Register a new user account.

**Request Body:**
```json
{
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Doe"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful. Please check your email for verification."
}
```

**Error Codes:**
- `EMAIL_EXISTS`: Email already registered
- `INVALID_EMAIL`: Invalid email format
- `MISSING_FIELDS`: Required fields missing

### Send Login Email

**POST** `/api/login`

Send a login link to the user's email address.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login link sent to your email"
}
```

**Error Codes:**
- `USER_NOT_FOUND`: Email not registered
- `INVALID_EMAIL`: Invalid email format

### Complete Login

**GET** `/api/auth/callback?token={token}`

Complete the login process using the token from the email.

**Parameters:**
- `token` (string, required): Login token from email

**Response:**
```json
{
  "success": true,
  "message": "Login successful"
}
```

**Error Codes:**
- `INVALID_TOKEN`: Token is invalid or expired
- `TOKEN_USED`: Token has already been used

### Get Current User

**GET** `/api/me`

Get information about the currently authenticated user.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "firstName": "John",
      "lastName": "Doe",
      "role": "user",
      "verifiedAt": "2025-10-01T12:00:00Z"
    }
  }
}
```

**Error Codes:**
- `UNAUTHORIZED`: User not authenticated

### Logout

**POST** `/api/logout`

Logout the current user and destroy the session.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

## Availability Endpoints

### Get Latest Availability

**GET** `/api/availability/latest`

Get the latest campsite availability data.

**Query Parameters:**
- `parkId` (integer, optional): Filter by specific park ID
- `weekendOnly` (boolean, optional): Show only weekend availability (default: false)
- `dateRange` (integer, optional): Days to look ahead (default: 30)
- `page` (integer, optional): Page number (default: 1)
- `pageSize` (integer, optional): Items per page (default: 20)
- `sortBy` (string, optional): Sort by 'date' or 'site' (default: 'date')
- `sortDir` (string, optional): Sort direction 'asc' or 'desc' (default: 'asc')

**Example:**
```
GET /api/availability/latest?parkId=1&weekendOnly=true&dateRange=60&page=1&pageSize=10
```

**Response:**
```json
{
  "success": true,
  "data": {
    "availability": [
      {
        "parkId": 1,
        "parkName": "San Onofre State Beach",
        "siteNumber": "12",
        "siteName": "Bluff #12",
        "siteType": "Standard",
        "facilityName": "Bluff Camp (sites 1-23)",
        "weekendDates": [
          {
            "startDate": "2025-10-10",
            "endDate": "2025-10-12"
          }
        ]
      }
    ],
    "pagination": {
      "currentPage": 1,
      "totalPages": 5,
      "totalItems": 50,
      "itemsPerPage": 10
    },
    "filters": {
      "parkId": 1,
      "weekendOnly": true,
      "dateRange": 60
    }
  }
}
```

### Export Availability as CSV

**GET** `/api/availability/export.csv`

Export availability data as a CSV file.

**Query Parameters:**
- `parkId` (integer, optional): Filter by specific park ID
- `weekendOnly` (boolean, optional): Show only weekend availability
- `dateRange` (integer, optional): Days to look ahead

**Response:**
- Content-Type: `text/csv`
- File download with availability data

**CSV Format:**
```csv
Park Name,Site Number,Site Name,Site Type,Facility Name,Start Date,End Date
San Onofre State Beach,12,Bluff #12,Standard,Bluff Camp (sites 1-23),2025-10-10,2025-10-12
```

### Trigger Manual Check

**POST** `/api/check-now`

Trigger a manual availability check (Admin only).

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Query Parameters:**
- `parkId` (integer, optional): Check specific park only
- `weekendOnly` (boolean, optional): Check only weekend availability
- `dateRange` (integer, optional): Months to check ahead

**Response:**
- Content-Type: `text/event-stream`
- Server-Sent Events stream with real-time progress

**Event Types:**
- `started`: Scraping started
- `info`: General information
- `park_start`: Park processing started
- `park_complete`: Park processing completed
- `error`: Error occurred
- `completed`: Scraping completed

**Example Event:**
```
data: {"type": "info", "message": "Checking San Onofre State Beach..."}
```

## Admin Endpoints

All admin endpoints require authentication and admin role.

### List Parks

**GET** `/api/admin/parks`

Get list of all parks with their status.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Response:**
```json
{
  "success": true,
  "data": {
    "parks": [
      {
        "id": 1,
        "name": "San Onofre State Beach",
        "parkNumber": "712",
        "active": true,
        "facilityCount": 14,
        "lastScraped": "2025-10-01T12:00:00Z"
      }
    ]
  }
}
```

### Create/Update Park

**POST** `/api/admin/parks`

Create a new park or update an existing one.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Request Body:**
```json
{
  "name": "San Onofre State Beach",
  "parkNumber": "712",
  "active": true,
  "facilityFilter": [668, 674, 675]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "park": {
      "id": 1,
      "name": "San Onofre State Beach",
      "parkNumber": "712",
      "active": true,
      "facilityFilter": [668, 674, 675]
    }
  }
}
```

### Get Park Facilities

**GET** `/api/admin/parks/{id}/facilities`

Get all facilities for a specific park.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Path Parameters:**
- `id` (integer, required): Park ID

**Response:**
```json
{
  "success": true,
  "data": {
    "facilities": [
      {
        "id": 1,
        "name": "Bluff Camp (sites 1-23)",
        "facilityId": "674",
        "active": true,
        "siteCount": 23
      }
    ]
  }
}
```

### Toggle Facility Status

**POST** `/api/admin/facilities/{id}/toggle`

Toggle the active status of a facility.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Path Parameters:**
- `id` (integer, required): Facility ID

**Response:**
```json
{
  "success": true,
  "data": {
    "facility": {
      "id": 1,
      "name": "Bluff Camp (sites 1-23)",
      "active": false
    }
  }
}
```

### Bulk Toggle Facilities

**POST** `/api/admin/facilities/bulk-toggle`

Toggle multiple facilities at once.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Request Body:**
```json
{
  "facilityIds": [1, 2, 3],
  "active": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "updated": 3,
    "facilities": [
      {
        "id": 1,
        "name": "Bluff Camp (sites 1-23)",
        "active": true
      }
    ]
  }
}
```

### Sync Facilities

**POST** `/api/admin/sync-facilities`

Synchronize facility data from ReserveCalifornia API.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Response:**
```json
{
  "success": true,
  "data": {
    "parksProcessed": 13,
    "facilitiesSynced": 46,
    "errors": 0
  }
}
```

### Sync Metadata

**POST** `/api/admin/sync-metadata`

Synchronize park and site metadata.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)
- User must have admin role

**Response:**
```json
{
  "success": true,
  "data": {
    "parksProcessed": 13,
    "sitesUpdated": 1250,
    "errors": 0
  }
}
```

## User Preferences Endpoints

### Get User Preferences

**GET** `/api/user/preferences`

Get user's alert preferences.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Response:**
```json
{
  "success": true,
  "data": {
    "preferences": [
      {
        "id": 1,
        "parkId": 1,
        "parkName": "San Onofre State Beach",
        "startDate": "2025-10-01",
        "endDate": "2025-12-31",
        "frequency": "immediate",
        "weekendOnly": true,
        "enabled": true
      }
    ]
  }
}
```

### Create Preference

**POST** `/api/user/preferences`

Create a new alert preference.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Request Body:**
```json
{
  "parkId": 1,
  "startDate": "2025-10-01",
  "endDate": "2025-12-31",
  "frequency": "immediate",
  "weekendOnly": true,
  "enabled": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "preference": {
      "id": 1,
      "parkId": 1,
      "parkName": "San Onofre State Beach",
      "startDate": "2025-10-01",
      "endDate": "2025-12-31",
      "frequency": "immediate",
      "weekendOnly": true,
      "enabled": true
    }
  }
}
```

### Update Preference

**PUT** `/api/user/preferences/{id}`

Update an existing preference.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Path Parameters:**
- `id` (integer, required): Preference ID

**Request Body:**
```json
{
  "enabled": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "preference": {
      "id": 1,
      "enabled": false
    }
  }
}
```

### Delete Preference

**DELETE** `/api/user/preferences/{id}`

Delete a preference.

**Headers:**
- `Cookie: PHPSESSID={session_id}` (required)

**Path Parameters:**
- `id` (integer, required): Preference ID

**Response:**
```json
{
  "success": true,
  "message": "Preference deleted successfully"
}
```

## Error Handling

### HTTP Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server error

### Error Response Format

```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {
    "field": "Specific field error"
  }
}
```

### Common Error Codes

- `VALIDATION_ERROR`: Input validation failed
- `AUTHENTICATION_REQUIRED`: User not authenticated
- `INSUFFICIENT_PERMISSIONS`: User lacks required permissions
- `RESOURCE_NOT_FOUND`: Requested resource doesn't exist
- `RATE_LIMIT_EXCEEDED`: Too many requests
- `EXTERNAL_API_ERROR`: Error from external service
- `DATABASE_ERROR`: Database operation failed

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **Authentication endpoints**: 5 requests per minute per IP
- **Availability endpoints**: 60 requests per minute per user
- **Admin endpoints**: 30 requests per minute per user
- **User preferences**: 20 requests per minute per user

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```

## Pagination

List endpoints support pagination:

**Query Parameters:**
- `page`: Page number (default: 1)
- `pageSize`: Items per page (default: 20, max: 100)

**Response Format:**
```json
{
  "data": [...],
  "pagination": {
    "currentPage": 1,
    "totalPages": 10,
    "totalItems": 200,
    "itemsPerPage": 20
  }
}
```

## Filtering and Sorting

Many endpoints support filtering and sorting:

**Filtering:**
- Use query parameters to filter results
- Multiple filters can be combined
- Filter values are validated

**Sorting:**
- `sortBy`: Field to sort by
- `sortDir`: Sort direction (`asc` or `desc`)
- Default sorting is applied if not specified

## Webhooks (Future)

Webhook support is planned for future versions:

- **Availability alerts**: Notify when weekend availability is found
- **System events**: Notify of system status changes
- **User events**: Notify of user account changes

## SDKs and Libraries

Official SDKs are planned for:

- **PHP**: For server-side integrations
- **JavaScript**: For client-side applications
- **Python**: For data analysis and automation
- **Node.js**: For server-side applications

## Support

For API support:

- **Documentation**: This file and inline code comments
- **GitHub Issues**: Bug reports and feature requests
- **Email**: api-support@campsiteagent.com
- **Community**: GitHub Discussions

---

**Last Updated**: October 2025

**API Version**: 2.0

**Maintainer**: CampsiteAgent Development Team
