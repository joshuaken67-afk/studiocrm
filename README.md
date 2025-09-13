# StudioCRM - Complete Studio Management System

## Overview
StudioCRM is a comprehensive Customer Relationship Management system designed specifically for creative studios. It provides complete management capabilities for staff, clients, projects, bookings, payments, and reporting.

## Features

### Core Functionality
- **Role-Based Access Control**: Admin, Manager, Staff, and Client roles with appropriate permissions
- **Staff Management**: Complete staff directory with role assignments and status tracking
- **Client Management**: Comprehensive client database with contact information and project history
- **Project Management**: Full project lifecycle management with file uploads and collaboration
- **Booking System**: Advanced booking management with calendar integration
- **Payment Processing**: Invoice generation, payment tracking, and financial reporting
- **Clarity Forms**: Custom forms for project requirements and client onboarding
- **Reporting**: Comprehensive analytics and branded report generation

### Technical Features
- **Secure Authentication**: Session-based authentication with CSRF protection
- **Audit Logging**: Complete activity tracking for compliance and security
- **File Management**: Secure file upload and download with access controls
- **Export Capabilities**: CSV and PDF export for all data types
- **Responsive Design**: Mobile-friendly interface using Bootstrap 5
- **Database Security**: Prepared statements and input validation

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled

### Installation Steps

1. **Upload Files**
   \`\`\`bash
   # Upload all files to your web server directory
   # Ensure proper file permissions (755 for directories, 644 for files)
   \`\`\`

2. **Set Permissions**
   \`\`\`bash
   chmod 755 uploads/
   chmod 755 exports/
   chmod 644 config/app.php
   \`\`\`

3. **Run Installer**
   - Navigate to `http://yourdomain.com/install.php`
   - Follow the installation wizard
   - Provide database connection details
   - Create admin account
   - The installer will automatically create all required tables

4. **Post-Installation**
   - Delete or rename `install.php` for security
   - Update `config/app.php` with your domain and settings
   - Configure email settings if needed

### Default Login
After installation, use the admin credentials you created during setup.

## Configuration

### Database Configuration
Edit `config/app.php` to update database settings:
\`\`\`php
define('DB_HOST', 'localhost');
define('DB_NAME', 'studiocrm');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
\`\`\`

### Application Settings
\`\`\`php
define('SITE_NAME', 'Your Studio Name');
define('SITE_URL', 'https://yourdomain.com');
define('UPLOAD_MAX_SIZE', 10485760); // 10MB
\`\`\`

## User Roles & Permissions

### Admin
- Full system access
- User management
- System configuration
- All reports and exports

### Manager
- Staff and client management
- Project oversight
- Financial reports
- Booking management

### Staff
- View assigned projects
- Update project status
- Client communication
- Time tracking

### Client
- View own projects
- Download files
- Submit clarity forms
- View invoices

## File Structure
\`\`\`
studiocrm/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   └── app.php
├── includes/
│   ├── auth.php
│   ├── database.php
│   ├── file_handler.php
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── install/
│   └── schema.sql
├── uploads/
├── exports/
├── ajax/
├── index.php
├── dashboard.php
├── staff.php
├── clients.php
├── projects.php
├── bookings.php
├── clarity-forms.php
├── payments.php
├── reports.php
└── README.md
\`\`\`

## Security Features

### Authentication
- Secure password hashing using PHP's password_hash()
- Session management with regeneration
- CSRF token protection on all forms
- Role-based access control

### Data Protection
- SQL injection prevention using prepared statements
- XSS protection through input sanitization
- File upload validation and restrictions
- Secure file download with access controls

### Audit Trail
- Complete activity logging
- User action tracking
- Login/logout monitoring
- Data modification history

## Backup & Maintenance

### Database Backup
\`\`\`sql
mysqldump -u username -p studiocrm > backup_$(date +%Y%m%d).sql
\`\`\`

### File Backup
- Regularly backup the uploads/ directory
- Include configuration files in backups
- Test restore procedures periodically

### Maintenance Tasks
- Monitor log files for errors
- Update PHP and MySQL regularly
- Review user access permissions
- Clean up old export files

## Troubleshooting

### Common Issues

**Installation Problems**
- Check PHP version compatibility
- Verify database connection settings
- Ensure proper file permissions
- Check Apache/Nginx configuration

**Login Issues**
- Clear browser cache and cookies
- Check session configuration
- Verify database connectivity
- Review error logs

**File Upload Problems**
- Check upload directory permissions
- Verify PHP upload limits
- Review file size restrictions
- Check available disk space

### Error Logs
Check the following locations for error information:
- PHP error log
- Apache/Nginx error log
- Application logs in logs/ directory

## Support

For technical support or feature requests:
1. Check the documentation thoroughly
2. Review error logs for specific issues
3. Ensure all requirements are met
4. Contact your system administrator

## License
This software is provided as-is for studio management purposes. Modify and distribute according to your needs.

## Version History
- v1.0.0 - Initial release with core CRM functionality
- Comprehensive staff, client, and project management
- Advanced reporting and export capabilities
- Role-based security system
