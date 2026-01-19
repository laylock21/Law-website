# Law Firm Consultation System

A complete database-driven consultation booking system for law firms, built with PHP, MySQL, and modern web technologies.

## Features

- **Consultation Booking Form**: Collect client information, practice area, and preferred dates
- **Database Storage**: Secure storage of all consultation requests
- **Admin Panel**: Manage and track consultation requests
- **Responsive Design**: Works on all devices
- **Calendar Integration**: Date selection for appointments
- **Status Management**: Track consultation status (pending, confirmed, cancelled, completed)

## System Requirements

- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

## Installation & Setup

### 1. Start XAMPP

1. Open XAMPP Control Panel
2. Start Apache and MySQL services
3. Ensure both services are running (green status)

### 2. Database Setup

1. Open your web browser and go to: `http://localhost/phpmyadmin`
2. Click on "New" to create a new database
3. Enter database name: `lawfirm_db`
4. Click "Create"
5. Select the `lawfirm_db` database
6. Go to "Import" tab
7. Click "Choose File" and select `database.sql`
8. Click "Go" to import the database structure

**Alternative Method (Command Line):**
```bash
mysql -u root -p < database.sql
```

### 3. File Setup

1. Copy all project files to your XAMPP htdocs folder:
   - Windows: `C:\xampp\htdocs\LAWFIRM NEW\`
   - Mac: `/Applications/XAMPP/htdocs/LAWFIRM NEW/`
   - Linux: `/opt/lampp/htdocs/LAWFIRM NEW/`

2. Ensure the file structure is correct:
   ```
   LAWFIRM NEW/
   ├── index.html
   ├── script.js
   ├── styles.css
   ├── process_consultation.php
   ├── test_connection.php
   ├── database.sql
   ├── config/
   │   └── database.php
   ├── admin/
   │   ├── login.php
   │   ├── consultations.php
   │   └── logout.php
   └── src/
       └── img/
   ```

### 4. Database Configuration

1. Open `config/database.php`
2. Update database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lawfirm_db');
   define('DB_USER', 'root');        // Default XAMPP username
   define('DB_PASS', '');            // Default XAMPP password (empty)
   ```

### 5. Test the System

1. Open your browser and go to: `http://localhost/LAWFIRM%20NEW/`
2. Test the database connection: `http://localhost/LAWFIRM%20NEW/test_connection.php`
3. Submit a test consultation through the form
4. Access admin panel: `http://localhost/LAWFIRM%20NEW/admin/login.php`

## Admin Panel Access

- **URL**: `http://localhost/LAWFIRM%20NEW/admin/login.php`
- **Username**: `admin`
- **Password**: `admin123`

## Database Structure

### Tables Created

1. **consultations** - Main consultation requests
   - id, full_name, email, phone, practice_area, case_description
   - selected_lawyer, selected_date, status, created_at, updated_at

2. **users** - Admin user accounts
   - id, username, password, email, role, created_at

3. **practice_areas** - Available legal practice areas
   - id, area_name, description, is_active

4. **lawyers** - Available lawyers
   - id, name, specialization, email, phone, is_active

## Usage

### For Clients

1. Visit the website
2. Fill out the consultation form
3. Select practice area and preferred date
4. Submit the form
5. Receive confirmation message

### For Administrators

1. Login to admin panel
2. View all consultation requests
3. Update consultation status
4. Track pending, confirmed, and completed consultations
5. Manage client information

## Security Features

- Input validation and sanitization
- SQL injection prevention using prepared statements
- XSS protection
- Session-based authentication for admin panel

## Customization

### Adding New Practice Areas

1. Edit `database.sql` and add new areas
2. Update the form options in `index.html`
3. Re-run the SQL or manually add to database

### Adding New Lawyers

1. Edit `database.sql` and add new lawyers
2. Update the lawyer cards in `index.html`
3. Re-run the SQL or manually add to database

### Modifying Form Fields

1. Update the form in `index.html`
2. Modify `process_consultation.php` to handle new fields
3. Update the database schema if needed

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check if XAMPP MySQL is running
   - Verify database name and credentials
   - Test connection with `test_connection.php`

2. **Form Not Submitting**
   - Check browser console for JavaScript errors
   - Verify PHP file paths are correct
   - Check Apache error logs

3. **Admin Panel Not Working**
   - Ensure sessions are enabled in PHP
   - Check file permissions
   - Verify login credentials

### Error Logs

- Apache logs: `C:\xampp\apache\logs\error.log`
- PHP logs: Check XAMPP Control Panel → Apache → Config → PHP (php.ini)

## File Descriptions

- **index.html** - Main website with consultation form
- **script.js** - Frontend functionality and form handling
- **styles.css** - Website styling
- **process_consultation.php** - Backend form processor
- **config/database.php** - Database connection configuration
- **admin/login.php** - Admin authentication
- **admin/consultations.php** - Consultation management panel
- **admin/logout.php** - Admin logout
- **test_connection.php** - Database connection tester
- **database.sql** - Database structure and sample data

## Support

For issues or questions:
1. Check the troubleshooting section
2. Verify all setup steps were completed
3. Check XAMPP and PHP error logs
4. Ensure file permissions are correct

## License

This project is provided as-is for educational and business use.



<!-- Instructions -->
<div class="section" style="
   background: #f8f9fa;
   border-left: 4px solid #17a2b8;
   padding: 16px 20px;
   border-radius: 8px;
   margin-top: 20px;
   box-shadow: 0 2px 8px rgba(0,0,0,0.04);
">
   <h3 style="margin: 0 0 10px 0;"><i class="fas fa-info-circle"></i> How It Works</h3>
   <ol style="margin: 0 0 0 18px; padding: 0;">
         <li><strong>Automatic:</strong> Emails are sent automatically when lawyers block dates</li>
         <li><strong>Manual:</strong> Use this page to send any pending emails manually</li>
         <li><strong>Monitoring:</strong> Check the notification queue for failed emails</li>
         <li><strong>Retry:</strong> Failed emails are automatically retried up to 3 times</li>
   </ol>
</div>

<!-- Gmail Setup Instructions -->
<div class="section" style="
   background: #f8f9fa;
   border-left: 4px solid #17a2b8;
   padding: 16px 20px;
   border-radius: 8px;
   margin-top: 20px;
   box-shadow: 0 2px 8px rgba(0,0,0,0.04);
">
   <h3 style="margin: 0 0 10px 0;"><i class="fas fa-envelope-circle-check"></i> How to Enable Email Notifications</h3>
   <ol style="margin: 0 0 0 18px; padding: 0;">
         <li>
            <strong>Get Gmail App Password:</strong>
            <ul style="margin-top: 6px; list-style: disc; margin-left: 18px;">
               <li>Go to Google Account → Security</li>
               <li>Enable 2-Step Verification</li>
               <li>Generate App Password for "Mail"</li>
            </ul>
         </li>
         <li>Edit <code>/includes/EmailNotification.php</code></li>
         <li>Add your Gmail credentials to <code>$smtp_config</code></li>
         <li>Set <code>$smtp_enabled = true</code></li>
         <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
         <li><strong>Done:</strong> Notifications will be sent automatically!</li>
   </ol>
</div>