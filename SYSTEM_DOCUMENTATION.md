# TRINIDADV9 Law Firm Consultation System - Complete Documentation

## 📋 Table of Contents
1. [System Overview](#system-overview)
2. [Directory Structure](#directory-structure)
3. [Core Features](#core-features)
4. [Database Architecture](#database-architecture)
5. [User Roles & Authentication](#user-roles--authentication)
6. [API Endpoints](#api-endpoints)
7. [Key Components](#key-components)
8. [Configuration Files](#configuration-files)
9. [Security Features](#security-features)
10. [Installation & Setup](#installation--setup)

---

## 🎯 System Overview

**TRINIDADV9** is a comprehensive web-based law firm consultation management system built with PHP, MySQL, HTML5, CSS3, and JavaScript. The system provides a complete solution for managing client consultations, lawyer availability, appointment scheduling, and administrative tasks.

### Key Technologies
- **Backend**: PHP 8.0+, MySQL 5.7+/MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Email**: PHPMailer with SMTP support
- **Document Generation**: DOCX generation for consultation reports
- **Authentication**: Session-based with role management
- **Architecture**: MVC-inspired structure with separation of concerns

---

## 📁 Directory Structure

### Root Directory (`/`)
```
TRINIDADV9/
├── admin/                  # Admin panel files
├── api/                    # REST API endpoints
├── config/                 # Configuration files
├── includes/               # Shared PHP classes and utilities
├── lawyer/                 # Lawyer dashboard files
├── logs/                   # Application logs
├── migrations/             # Database migration scripts
├── src/                    # Static assets (images, etc.)
├── uploads/                # File upload directories
├── vendor/                 # Composer dependencies (PHPMailer)
├── index.html              # Main client-facing page
├── login.php               # Authentication page
├── process_consultation.php # Form processing endpoint
└── COMPLETE_DATABASE_IMPORT.sql # Full database schema
```

### `/admin/` - Administrative Interface
- **`dashboard.php`** - Admin overview with statistics
- **`consultations.php`** - Manage all consultations
- **`view_consultation.php`** - Detailed consultation view
- **`manage_lawyers.php`** - Lawyer profile management
- **`manage_lawyer_schedule.php`** - Lawyer availability management
- **`notification_queue.php`** - Email notification management
- **`process_emails.php`** - Email processing utility
- **`get_blocked_dates.php`** - API for blocked dates
- **`styles.css`** - Admin-specific styling

### `/api/` - REST API Endpoints
- **`get_all_lawyers.php`** - Retrieve all lawyers with specializations
- **`get_lawyer_availability.php`** - Get lawyer's available dates
- **`get_lawyers_by_specialization.php`** - Filter lawyers by practice area
- **`get_time_slots.php`** - Get available time slots for specific dates

### `/config/` - System Configuration
- **`database.php`** - Database connection and configuration
- **`Auth.php`** - Authentication utilities
- **`ErrorHandler.php`** - Centralized error handling
- **`Logger.php`** - Application logging system
- **`upload_config.php`** - File upload configuration

### `/includes/` - Shared Components
- **`EmailNotification.php`** - Email notification system with PHPMailer
- **`DocxGenerator.php`** - DOCX document generation for reports

### `/lawyer/` - Lawyer Dashboard
- **`dashboard.php`** - Lawyer overview and statistics
- **`consultations.php`** - Manage assigned consultations
- **`view_consultation.php`** - Detailed consultation view
- **`availability.php`** - Manage personal availability and schedules
- **`edit_profile.php`** - Profile editing interface
- **`process_profile_edit.php`** - Profile update processing
- **`upload_profile_picture.php`** - Profile picture upload
- **`get_blocked_dates_lawyer.php`** - API for lawyer's blocked dates
- **`styles.css`** - Lawyer dashboard styling

### `/migrations/` - Database Evolution
- **`001_add_flexible_scheduling.sql`** - Weekly/one-time scheduling support
- **`002_add_notification_system.sql`** - Email notification infrastructure
- **`003_add_cancellation_fields.sql`** - Cancellation reason tracking
- **`004_add_time_slots.sql`** - Time slot booking system
- **`005_add_blocked_schedule_type.sql`** - Date blocking functionality
- **`006_add_consultation_id_to_notifications.sql`** - Enhanced notifications
- **`add_lawyer_date_preferences.sql`** - Lawyer booking preferences
- **`create_lawyer_settings_table.sql`** - Lawyer-specific settings

---

## 🚀 Core Features

### 1. Client Consultation Booking
- **Public booking form** on main page (`index.html`)
- **Dynamic lawyer selection** with specialization filtering
- **Calendar integration** with availability checking
- **Time slot selection** for precise appointment booking
- **Real-time validation** and form processing
- **Email confirmations** to clients and lawyers

### 2. Lawyer Availability Management
- **Weekly recurring schedules** (e.g., "Every Monday 9 AM - 5 PM")
- **One-time specific dates** (e.g., "December 25, 2024 10 AM - 2 PM")
- **Date blocking** for vacations or unavailable periods
- **Time slot customization** with flexible intervals
- **Bulk schedule management** with calendar interface
- **Date range preferences** for booking windows

### 3. Administrative Control Panel
- **Dashboard with statistics** (total, pending, confirmed consultations)
- **Consultation management** with status updates
- **Lawyer profile management** including specializations
- **Email notification queue** with manual processing
- **System-wide schedule management**
- **User role management** (admin/lawyer)

### 4. Email Notification System
- **Automated email alerts** for new consultations
- **Status change notifications** (confirmed, cancelled, completed)
- **DOCX attachment generation** for completed consultations
- **Queue-based processing** to prevent timeouts
- **SMTP integration** ready for Gmail/other providers
- **Template-based emails** with professional formatting

### 5. Document Management
- **DOCX report generation** for completed consultations
- **Profile picture uploads** for lawyers
- **File organization** with secure upload directories
- **Document attachment** to email notifications

### 6. Advanced Scheduling Features
- **Conflict detection** to prevent double-booking
- **Bulk date operations** for efficient schedule management
- **Calendar visualization** with availability indicators
- **Time zone support** and date formatting
- **Responsive design** for mobile and desktop access

---

## 🗄️ Database Architecture

### Core Tables

#### `users` - User Management
```sql
- id (Primary Key)
- username (Unique)
- password (Hashed)
- email
- role (admin/lawyer)
- full_name
- profile_picture
- bio
- created_at, updated_at
```

#### `consultations` - Client Appointments
```sql
- id (Primary Key)
- first_name, middle_name, last_name
- email, phone
- service (Practice area)
- message (Client's inquiry)
- lawyer (Assigned lawyer name)
- date, selected_time
- status (pending/confirmed/cancelled/completed)
- cancellation_reason
- created_at, updated_at
```

#### `lawyer_availability` - Schedule Management
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- schedule_type (weekly/one_time/blocked)
- specific_date (For one-time schedules)
- day_of_week (For weekly schedules)
- start_time, end_time
- max_appointments
- is_active
- created_at, updated_at
```

#### `practice_areas` - Legal Specializations
```sql
- id (Primary Key)
- name (e.g., "Family Law", "Criminal Defense")
- description
- created_at, updated_at
```

#### `lawyer_specializations` - Many-to-Many Relationship
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- practice_area_id (Foreign Key to practice_areas)
- created_at
```

#### `notification_queue` - Email Management
```sql
- id (Primary Key)
- consultation_id (Foreign Key to consultations)
- recipient_email
- notification_type (appointment_cancelled/schedule_changed/reminder/confirmation/appointment_completed)
- subject, message
- status (pending/sent/failed)
- created_at, sent_at
```

### Advanced Features Tables

#### `lawyer_settings` - Individual Preferences
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- default_booking_weeks
- max_booking_weeks
- booking_window_enabled
- created_at, updated_at
```

### Database Relationships
- **Users ↔ Lawyer Availability**: One-to-Many (One lawyer has many availability slots)
- **Users ↔ Lawyer Specializations**: One-to-Many (One lawyer has many specializations)
- **Practice Areas ↔ Lawyer Specializations**: One-to-Many (One practice area has many lawyers)
- **Consultations ↔ Notification Queue**: One-to-Many (One consultation can have many notifications)

---

## 👥 User Roles & Authentication

### Admin Role (`role = 'admin'`)
**Capabilities:**
- View all consultations across all lawyers
- Manage lawyer profiles and specializations
- Access system-wide statistics and reports
- Process email notification queue
- Manage lawyer schedules and availability
- Update consultation statuses
- Access admin dashboard with comprehensive overview

**Default Admin Account:**
- Username: `admin`
- Password: `admin123`

### Lawyer Role (`role = 'lawyer'`)
**Capabilities:**
- View only assigned consultations
- Manage personal availability and schedules
- Update own profile and specializations
- Upload profile pictures
- Set personal booking preferences
- View personal statistics and appointments
- Update consultation statuses for assigned cases

### Authentication System
- **Session-based authentication** with PHP sessions
- **Role-based access control** (RBAC)
- **Secure password hashing** with PHP's `password_hash()`
- **Login attempt protection** and session management
- **Automatic logout** on session expiry
- **Cross-site request forgery (CSRF) protection**

---

## 🔌 API Endpoints

### Public APIs (No Authentication Required)

#### `GET /api/get_all_lawyers.php`
**Purpose:** Retrieve all active lawyers with their specializations
**Response:**
```json
{
  "success": true,
  "lawyers": [
    {
      "id": 1,
      "name": "Atty. John Doe",
      "specializations": ["Family Law", "Criminal Defense"],
      "profile_picture": "/uploads/profile_pictures/john_doe.jpg",
      "bio": "Experienced lawyer with 10+ years..."
    }
  ]
}
```

#### `GET /api/get_lawyer_availability.php?lawyer=John+Doe&weeks=52`
**Purpose:** Get available dates for a specific lawyer
**Parameters:**
- `lawyer` (required): Lawyer's name
- `weeks` (optional): Number of weeks to fetch (default: 52)
- `start_date` (optional): Custom start date
- `end_date` (optional): Custom end date

**Response:**
```json
{
  "success": true,
  "available_dates": ["2024-12-01", "2024-12-02", "2024-12-08"],
  "date_range": {
    "start_date": "2024-12-01",
    "end_date": "2024-12-31",
    "total_days": 365
  }
}
```

#### `GET /api/get_time_slots.php?lawyer=John+Doe&date=2024-12-01`
**Purpose:** Get available time slots for a specific lawyer and date
**Response:**
```json
{
  "success": true,
  "time_slots": [
    {
      "time": "09:00",
      "time_24h": "09:00:00",
      "display": "9:00 AM",
      "available": true
    }
  ]
}
```

#### `GET /api/get_lawyers_by_specialization.php?specialization=Family+Law`
**Purpose:** Filter lawyers by practice area
**Response:**
```json
{
  "success": true,
  "lawyers": [
    {
      "id": 1,
      "name": "Atty. John Doe",
      "specializations": ["Family Law"]
    }
  ]
}
```

### Admin APIs (Authentication Required)

#### `GET /admin/get_blocked_dates.php?lawyer_id=1`
**Purpose:** Get blocked dates for schedule management

#### `POST /admin/process_emails.php`
**Purpose:** Process pending email notifications

---

## 🔧 Key Components

### 1. EmailNotification Class (`/includes/EmailNotification.php`)
**Features:**
- **PHPMailer integration** for SMTP email sending
- **Template-based emails** with HTML formatting
- **DOCX attachment support** for completed consultations
- **Queue management** for reliable email delivery
- **Multiple notification types** (confirmation, cancellation, completion)
- **Error handling and logging** for debugging

**Key Methods:**
- `queueNotification()` - Add email to queue
- `processQueue()` - Send pending emails
- `notifyAppointmentCompleted()` - Send completion emails with DOCX
- `notifyAppointmentCancelled()` - Send cancellation notifications

### 2. DocxGenerator Class (`/includes/DocxGenerator.php`)
**Features:**
- **DOCX document creation** for consultation reports
- **Template-based generation** with client and consultation data
- **Professional formatting** with law firm branding
- **Automatic file naming** and organization
- **Error handling** for document generation failures

### 3. Authentication System (`/config/Auth.php`)
**Features:**
- **Session management** with secure configuration
- **Password hashing** with bcrypt
- **Role-based access control** (admin/lawyer)
- **Login attempt tracking** and protection
- **Automatic session cleanup**

### 4. Error Handling (`/config/ErrorHandler.php`)
**Features:**
- **Centralized error management** across the application
- **JSON error responses** for API endpoints
- **Logging integration** for debugging
- **User-friendly error messages**
- **Security-focused error disclosure**

### 5. Logger System (`/config/Logger.php`)
**Features:**
- **File-based logging** with daily rotation
- **Multiple log levels** (INFO, WARNING, ERROR)
- **Structured log format** for easy parsing
- **Automatic log cleanup** to prevent disk space issues

---

## ⚙️ Configuration Files

### Database Configuration (`/config/database.php`)
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'lawfirm_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Upload Configuration (`/config/upload_config.php`)
- **Profile picture uploads** with size and type validation
- **Document storage** with organized directory structure
- **Security measures** to prevent malicious uploads
- **File naming conventions** to avoid conflicts

### Email Configuration (`/includes/EmailNotification.php`)
```php
private $smtp_config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'your-email@gmail.com',
    'password' => 'your-app-password',
    'encryption' => 'tls'
];
```

---

## 🔒 Security Features

### 1. Input Validation & Sanitization
- **Server-side validation** for all form inputs
- **SQL injection prevention** with prepared statements
- **XSS protection** with HTML entity encoding
- **CSRF token validation** for state-changing operations

### 2. Authentication Security
- **Secure password hashing** with bcrypt
- **Session hijacking protection** with regeneration
- **Role-based access control** with strict enforcement
- **Login attempt rate limiting**

### 3. File Upload Security
- **File type validation** with whitelist approach
- **File size limitations** to prevent abuse
- **Secure file naming** to prevent directory traversal
- **Upload directory protection** with .htaccess

### 4. HTTP Security Headers
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

### 5. Database Security
- **Foreign key constraints** to maintain data integrity
- **Proper indexing** for performance and security
- **Connection encryption** support
- **Backup and recovery** procedures

---

## 🚀 Installation & Setup

### Prerequisites
- **PHP 8.0+** with extensions: PDO, MySQLi, cURL, GD, Zip
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache/Nginx** web server
- **Composer** for dependency management

### Installation Steps

1. **Clone/Download** the TRINIDADV9 system to your web directory
2. **Install Dependencies:**
   ```bash
   composer install
   ```
3. **Database Setup:**
   - Create MySQL database: `lawfirm_db`
   - Import: `COMPLETE_DATABASE_IMPORT.sql`
   - Verify tables are created correctly

4. **Configuration:**
   - Update `/config/database.php` with your database credentials
   - Configure email settings in `/includes/EmailNotification.php`
   - Set proper file permissions for `/uploads/` and `/logs/`

5. **Web Server Configuration:**
   - Ensure mod_rewrite is enabled (Apache)
   - Configure virtual host or directory access
   - Set appropriate PHP memory and execution limits

6. **Testing:**
   - Access the main page: `http://localhost/TRINIDADV9/`
   - Login to admin panel: `http://localhost/TRINIDADV9/login.php`
   - Test consultation booking and email notifications

### Default Credentials
- **Admin:** username: `admin`, password: `admin123`
- **Sample Lawyer:** username: `lawyer1`, password: `lawyer123`

---

## 📈 System Statistics & Performance

### Database Performance
- **Optimized queries** with proper indexing
- **Foreign key relationships** for data integrity
- **Connection pooling** for better resource management
- **Query caching** where appropriate

### Frontend Performance
- **Responsive design** for mobile and desktop
- **Lazy loading** for images and content
- **Minified CSS/JS** for faster loading
- **Service worker** for offline capability

### Scalability Features
- **Modular architecture** for easy expansion
- **API-first design** for future integrations
- **Queue-based email processing** to handle high volume
- **Configurable booking windows** for load management

---

## 🔄 Future Enhancement Opportunities

### Planned Features
1. **Multi-language support** for international clients
2. **Payment integration** for consultation fees
3. **Video conferencing** integration for remote consultations
4. **Mobile app** development (iOS/Android)
5. **Advanced reporting** and analytics dashboard
6. **Client portal** for case tracking and document access
7. **Calendar synchronization** with Google Calendar/Outlook
8. **SMS notifications** in addition to email
9. **Document management system** for case files
10. **Time tracking** and billing integration

### Technical Improvements
1. **API rate limiting** for better security
2. **Redis caching** for improved performance
3. **Docker containerization** for easier deployment
4. **Automated testing** suite implementation
5. **CI/CD pipeline** setup
6. **Database replication** for high availability
7. **CDN integration** for static assets
8. **Advanced logging** with centralized monitoring

---

## 📞 Support & Maintenance

### Logging & Monitoring
- Application logs stored in `/logs/` directory
- Error tracking with detailed stack traces
- Performance monitoring capabilities
- Database query logging for optimization

### Backup Procedures
- Regular database backups recommended
- File system backups for uploaded documents
- Configuration backup before updates
- Migration rollback procedures available

### Update Process
1. Backup current system and database
2. Test updates in staging environment
3. Run database migrations if required
4. Update configuration files as needed
5. Verify all functionality post-update

---

*This documentation covers the complete TRINIDADV9 Law Firm Consultation System. For technical support or custom modifications, refer to the individual file comments and code documentation within the system.*
