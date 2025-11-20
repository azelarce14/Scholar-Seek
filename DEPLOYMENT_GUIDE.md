# ScholarSeek - Infinity Free Deployment Guide

## Prerequisites
1. Infinity Free Account (https://www.infinityfree.net/)
2. FTP Client (FileZilla recommended - https://filezilla-project.org/)
3. Your Infinity Free FTP credentials

## Step 1: Get Your Infinity Free FTP Credentials
1. Log in to your Infinity Free account
2. Go to **Account** → **FTP Accounts**
3. Create a new FTP account or use the default one
4. Note down:
   - FTP Host
   - FTP Username
   - FTP Password
   - FTP Port (usually 21)

## Step 2: Connect via FTP
1. Open FileZilla
2. Go to **File** → **Site Manager**
3. Click **New Site**
4. Enter your Infinity Free FTP credentials:
   - Host: Your FTP Host
   - Port: 21
   - Protocol: FTP
   - User: Your FTP Username
   - Password: Your FTP Password
5. Click **Connect**

## Step 3: Upload ScholarSeek Files
1. In FileZilla, navigate to the remote folder: `/htdocs/` or `/public_html/`
2. Upload all files from your local ScholarSeek folder to the remote server
3. Ensure the following directories are created:
   - `/assets/`
   - `/config/`
   - `/uploads/`
   - `/logs/`
   - `/docs/`

## Step 4: Configure Database
1. Log in to Infinity Free control panel
2. Go to **MySQL Databases**
3. Create a new database
4. Note down:
   - Database Name
   - Database Username
   - Database Password
   - Database Host (usually localhost)

## Step 5: Update Database Configuration
1. Edit `db_connect.php` on the remote server via FTP
2. Update the following variables:
   ```php
   $servername = "localhost";
   $username = "your_db_username";
   $password = "your_db_password";
   $database = "your_db_name";
   ```

## Step 6: Import Database Schema
1. Go to **MySQL Databases** in Infinity Free control panel
2. Click **phpMyAdmin**
3. Select your database
4. Go to **Import**
5. Upload your database SQL file (if you have one)
6. Or create tables manually using the provided schema

## Step 7: Set File Permissions
1. In FileZilla, right-click on `/uploads/` folder
2. Select **File Attributes**
3. Set permissions to **777** (read, write, execute for all)
4. Do the same for `/logs/` folder

## Step 8: Access Your Application
1. Your application will be available at:
   - `https://your-domain.infinityfree.app/`
   - Or your custom domain if configured

## Important Notes
- Infinity Free has limitations on file uploads (usually 2GB total)
- Database size is limited (usually 1GB)
- Some PHP functions may be disabled
- Always keep backups of your database and files
- Test all functionality after deployment

## Troubleshooting
- **Database Connection Error**: Check db_connect.php credentials
- **File Upload Issues**: Verify /uploads/ folder permissions (777)
- **Missing Pages**: Ensure all PHP files are uploaded
- **CSS/JS Not Loading**: Check file paths in HTML/PHP files

## Support
For Infinity Free support: https://www.infinityfree.net/support/
