# ScholarSeek - Scholarship Management System

A modern, responsive web application for managing scholarship applications and student information. Built with PHP, MySQL, and contemporary web technologies.

## 🎯 Features

### Core Functionality
- **User Authentication**: Secure login system for students, staff, and administrators
- **Scholarship Management**: Create, edit, and manage scholarship programs
- **Application Processing**: Students can apply for scholarships with document uploads
- **Application Tracking**: Real-time status updates and application management
- **Student Management**: Comprehensive student database and profile management
- **Staff Management**: Manage staff members and their roles
- **Notification System**: Real-time notifications for application updates
- **Document Management**: Secure file upload and viewing system

### User Roles
- **Admin**: Full system access, user management, scholarship creation
- **Staff**: Application review and processing, student management
- **Student**: Apply for scholarships, track applications, manage profile

### Modern UI/UX
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **Modern Dashboard**: Clean, intuitive interface with real-time statistics
- **Dark Mode Support**: Theme toggle for user preference
- **Professional Styling**: Consistent design language across all pages
- **Smooth Animations**: Enhanced user experience with CSS animations
- **Accessibility**: WCAG compliant with proper contrast ratios

## 🛠️ Technology Stack

### Backend
- **PHP 7.4+**: Server-side scripting
- **MySQL 5.7+**: Database management
- **PDO**: Secure database connections

### Frontend
- **HTML5**: Semantic markup
- **CSS3**: Modern styling with Flexbox and Grid
- **JavaScript (ES6+)**: Interactive features
- **Font Awesome 6.4**: Icon library

### Security Features
- **Password Hashing**: bcrypt password encryption
- **Session Management**: Secure session handling with regeneration
- **Rate Limiting**: Login attempt throttling
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Prevention**: Prepared statements throughout
- **CSRF Protection**: Token-based protection
- **HTTPS Support**: SSL/TLS ready

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- 50MB disk space minimum
- Modern web browser (Chrome, Firefox, Safari, Edge)

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/scholarseek.git
cd scholarseek
```

### 2. Set Up Database
```bash
# Import the database schema
mysql -u root -p < scholarseek.sql
```

### 3. Configure Database Connection
Edit `db_connect.php` with your database credentials:
```php
$servername = "localhost";
$username = "root";
$password = "your_password";
$dbname = "scholarseek";
```

### 4. Set File Permissions
```bash
chmod 755 uploads/
chmod 755 logs/
chmod 644 .htaccess
```

### 5. Access the Application
Open your browser and navigate to:
```
http://localhost/scholarseek
```

## 👤 Default Login Credentials

### Admin Account
- **Email**: admin@biliran.edu.ph
- **Password**: 123

### Staff Account
- **Email**: staff@biliran.edu.ph
- **Password**: 123

⚠️ **Important**: Change these credentials immediately in production!

## 📁 Project Structure

```
scholarseek/
├── assets/
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   ├── img/              # Images and logos
│   └── html/             # HTML templates
├── config/               # Configuration files
├── docs/                 # Documentation
├── logs/                 # Application logs
├── uploads/              # User uploads (documents, images)
├── admin_dashboard.php   # Admin interface
├── staff_dashboard.php   # Staff interface
├── student_dashboard.php # Student interface
├── login.php             # Login page
├── register.php          # Registration page
├── db_connect.php        # Database connection
├── scholarseek.sql       # Database schema
└── README.md             # This file
```

## 🔐 Security Considerations

1. **Database Credentials**: Never commit `db_connect.php` with real credentials
2. **File Uploads**: Validate all uploaded files
3. **Session Management**: Sessions expire after inactivity
4. **HTTPS**: Always use HTTPS in production
5. **Backups**: Regular database backups recommended
6. **Updates**: Keep PHP and MySQL updated

## 🎨 Customization

### Color Scheme
Primary colors are defined in CSS variables:
- Primary Color: `#0A06D3` (Blue)
- Secondary Color: `#FCEB10` (Yellow)
- Dark Navy: `#0f172a`

Edit `assets/css/admin_dashboard.css` to customize colors.

### Branding
- Replace `assets/img/logo.png` with your logo
- Update `assets/img/icon.png` for favicon
- Modify site title in HTML headers

## 📱 Responsive Breakpoints

- **Desktop**: 1024px and above
- **Tablet**: 768px - 1023px
- **Mobile**: Below 768px

## 🐛 Troubleshooting

### Database Connection Issues
- Verify MySQL is running
- Check database credentials in `db_connect.php`
- Ensure database exists: `CREATE DATABASE scholarseek;`

### File Upload Issues
- Check `uploads/` directory permissions
- Verify PHP `upload_max_filesize` setting
- Check available disk space

### Session Issues
- Clear browser cookies
- Check PHP session settings
- Verify `logs/` directory is writable

## 📝 License

This project is proprietary and confidential. Unauthorized copying or distribution is prohibited.

## 👥 Contributors

- Development Team
- UI/UX Design Team
- Quality Assurance Team

## 📞 Support

For issues, questions, or suggestions, please contact the development team.

## 🔄 Version History

### v1.0.0 (Current)
- Initial release
- Core functionality implemented
- Modern UI design
- Security features implemented

---

**Last Updated**: November 2025
**Status**: Active Development
