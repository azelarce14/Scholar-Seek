# ScholarSeek - Infinity Free Deployment Checklist

## Pre-Deployment Tasks
- [ ] Backup your local database
- [ ] Test all functionality locally
- [ ] Create Infinity Free account
- [ ] Download and install FileZilla FTP client
- [ ] Get Infinity Free FTP credentials

## Database Preparation
- [ ] Create database on Infinity Free
- [ ] Export your local database (if needed)
- [ ] Have database credentials ready:
  - [ ] Database Name: _______________
  - [ ] Database Username: _______________
  - [ ] Database Password: _______________
  - [ ] Database Host: _______________

## FTP Credentials
- [ ] FTP Host: _______________
- [ ] FTP Username: _______________
- [ ] FTP Password: _______________
- [ ] FTP Port: _______________

## File Upload
- [ ] Connect to FTP server
- [ ] Create directory structure:
  - [ ] /htdocs/
  - [ ] /htdocs/assets/
  - [ ] /htdocs/config/
  - [ ] /htdocs/uploads/
  - [ ] /htdocs/logs/
  - [ ] /htdocs/docs/
- [ ] Upload all PHP files
- [ ] Upload all CSS files
- [ ] Upload all JavaScript files
- [ ] Upload all image files
- [ ] Upload .htaccess file
- [ ] Upload manifest.json
- [ ] Upload sw.js

## Configuration
- [ ] Update db_connect.php with Infinity Free database credentials
- [ ] Set /uploads/ folder permissions to 777
- [ ] Set /logs/ folder permissions to 777
- [ ] Verify all file paths are correct

## Database Setup
- [ ] Access phpMyAdmin on Infinity Free
- [ ] Create all necessary tables
- [ ] Insert initial data (if needed)
- [ ] Test database connection

## Post-Deployment Testing
- [ ] Access your domain
- [ ] Test login functionality
- [ ] Test file uploads
- [ ] Test database queries
- [ ] Check all pages load correctly
- [ ] Verify CSS/JS files are loading
- [ ] Test email functionality (if applicable)
- [ ] Check error logs for issues

## Security
- [ ] Remove debug files
- [ ] Verify .htaccess is uploaded
- [ ] Check file permissions
- [ ] Test SQL injection prevention
- [ ] Verify session handling

## Final Steps
- [ ] Update DNS if using custom domain
- [ ] Set up SSL certificate (if available)
- [ ] Create admin account
- [ ] Test all user roles (admin, staff, student)
- [ ] Document any issues encountered

## Rollback Plan
- [ ] Keep backup of local files
- [ ] Keep backup of database
- [ ] Document all changes made
- [ ] Have rollback procedure ready
