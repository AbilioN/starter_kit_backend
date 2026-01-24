# API Documentation - dashboard_addresses

## Base URL
```
http://localhost:8006/api
```

## Authentication

### Login
**POST** `/login`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "email": "user@email.com",
    "password": "password123"
}
```

**Success Response (200):**
```json
{
    "user": {
        "id": 1,
        "name": "Test User",
        "email": "user@email.com",
        "email_verified_at": "2025-06-25 17:00:00" // or null if not verified
    },
    "token": "1|Au4n3gtBscC77IrxUj8OlyyC1eVQc6JKyFoDyCxE6324c4fd"
}
```

**Error Response (401):**
```json
{
    "message": "Invalid credentials" // or "Email not verified"
}
```

### Register
**POST** `/register`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "name": "John Doe",
    "email": "john@email.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Success Response (201):**
```json
{
    "message": "User registered successfully. Please check your email for verification code.",
    "user": {
        "id": 2,
        "name": "John Doe",
        "email": "john@email.com"
    }
}
```

**Validation Error (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": [
            "The name field is required."
        ],
        "email": [
            "The email field is required.",
            "The email must be a valid email address.",
            "This email is already in use."
        ],
        "password": [
            "The password field is required.",
            "The password must be at least 8 characters.",
            "The password confirmation does not match."
        ]
    }
}
```

**Email Already in Use (422):**
```json
{
    "message": "This email is already in use.",
    "errors": {
        "email": [
            "This email is already in use."
        ]
    }
}
```

---

## Email Verification

### Verify Email
**POST** `/verify-email`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "email": "user@email.com",
    "code": "123456"
}
```

**Success Response (200):**
```json
{
    "message": "Email verified successfully",
    "email": "user@email.com"
}
```

**Error Response (422):**
```json
{
    "message": "Invalid or expired verification code"
}
```

### Resend Verification Code
**POST** `/resend-verification-code`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "email": "user@email.com"
}
```

**Success Response (200):**
```json
{
    "message": "Verification code sent successfully",
    "email": "user@email.com"
}
```

**Error Response (422):**
```json
{
    "message": "User not found" // or "Email already verified"
}
```

---

## Example Response Objects

### Login Response Objects

**Successful Login (200):**
```json
{
    "user": {
        "id": 1,
        "name": "Test User",
        "email": "user@email.com",
        "email_verified_at": "2025-06-25T17:00:00.000000Z",
        "created_at": "2025-06-25T16:30:00.000000Z",
        "updated_at": "2025-06-25T17:00:00.000000Z"
    },
    "token": "1|Au4n3gtBscC77IrxUj8OlyyC1eVQc6JKyFoDyCxE6324c4fd"
}
```

**Login with Unverified Email (401):**
```json
{
    "message": "Email not verified"
}
```

**Invalid Credentials (401):**
```json
{
    "message": "Invalid credentials"
}
```

### Register Response Objects

**Successful Registration (201):**
```json
{
    "message": "User registered successfully. Please check your email for verification code.",
    "user": {
        "id": 2,
        "name": "John Doe",
        "email": "john@email.com",
        "email_verified_at": null,
        "created_at": "2025-06-25T18:00:00.000000Z",
        "updated_at": "2025-06-25T18:00:00.000000Z"
    }
}
```

**Validation Errors (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": [
            "The name field is required."
        ],
        "email": [
            "The email field is required.",
            "The email must be a valid email address.",
            "This email is already in use."
        ],
        "password": [
            "The password field is required.",
            "The password must be at least 8 characters.",
            "The password confirmation does not match."
        ]
    }
}
```

**Email Already in Use (422):**
```json
{
    "message": "This email is already in use.",
    "errors": {
        "email": [
            "This email is already in use."
        ]
    }
}
```

### Email Verification Response Objects

**Successful Verification (200):**
```json
{
    "message": "Email verified successfully",
    "email": "user@email.com"
}
```

**Invalid Code (422):**
```json
{
    "message": "Invalid or expired verification code"
}
```

**Missing Email (422):**
```json
{
    "message": "The email field is required."
}
```

**Missing Code (422):**
```json
{
    "message": "The code field is required."
}
```

### Resend Verification Code Response Objects

**Successful Resend (200):**
```json
{
    "message": "Verification code sent successfully",
    "email": "user@email.com"
}
```

**User Not Found (422):**
```json
{
    "message": "User not found"
}
```

**Email Already Verified (422):**
```json
{
    "message": "Email already verified"
}
```

**Missing Email (422):**
```json
{
    "message": "The email field is required."
}
```

---

## Test Users

| Email            | Password     | Name         |
|------------------|-------------|--------------|
| user@email.com   | password123 | Test User    |
| john@email.com   | password123 | John Doe     |
| maria@email.com  | password123 | Maria Santos |

---

## Example Usage

### JavaScript/Fetch - Login
```javascript
const loginUser = async (email, password) => {
    const response = await fetch('http://localhost:8006/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });
    const data = await response.json();
    if (response.ok) {
        // Success
        return data;
    } else {
        throw new Error(data.message);
    }
};
```

### JavaScript/Fetch - Register
```javascript
const registerUser = async (name, email, password, passwordConfirmation) => {
    const response = await fetch('http://localhost:8006/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password, password_confirmation: passwordConfirmation })
    });
    const data = await response.json();
    if (response.ok) {
        // Success
        return data;
    } else {
        throw new Error(data.message);
    }
};
```

### JavaScript/Fetch - Verify Email
```javascript
const verifyEmail = async (email, code) => {
    const response = await fetch('http://localhost:8006/api/verify-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, code })
    });
    const data = await response.json();
    if (response.ok) {
        return data;
    } else {
        throw new Error(data.message);
    }
};
```

### JavaScript/Fetch - Resend Verification Code
```javascript
const resendVerificationCode = async (email) => {
    const response = await fetch('http://localhost:8006/api/resend-verification-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });
    const data = await response.json();
    if (response.ok) {
        return data;
    } else {
        throw new Error(data.message);
    }
};
```

---

### Axios Example
```javascript
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:8006/api'
});

const loginUser = async (email, password) => {
    const response = await api.post('/login', { email, password });
    return response.data;
};

const registerUser = async (name, email, password, passwordConfirmation) => {
    const response = await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation
    });
    return response.data;
};

const verifyEmail = async (email, code) => {
    const response = await api.post('/verify-email', { email, code });
    return response.data;
};

const resendVerificationCode = async (email) => {
    const response = await api.post('/resend-verification-code', { email });
    return response.data;
};
```

## Scripts Úteis

### Executar Seeder de Usuários de Teste
```bash
./seed-test-users.sh
```

### Executar Todos os Testes
```bash
./vendor/bin/phpunit
```

### Executar Testes Específicos
```bash
# Apenas testes de feature (API)
./vendor/bin/phpunit --testsuite=Feature

# Apenas testes unitários
./vendor/bin/phpunit --testsuite=Unit

# Apenas testes de integração
./vendor/bin/phpunit --testsuite=Integration
``` 