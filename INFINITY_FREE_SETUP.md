# Quick Setup for Infinity Free Deployment

## 1. Create Infinity Free Account
- Visit: https://www.infinityfree.net/
- Sign up for a free account
- Verify your email

## 2. Get Your Hosting Details
After account creation, you'll receive:
- **Domain**: something.infinityfree.app
- **FTP Host**: your-ftp-host.infinityfree.net
- **FTP Username**: your-username
- **FTP Password**: your-password

## 3. Download FileZilla
- Download: https://filezilla-project.org/download.php
- Install on your computer
- Open FileZilla

## 4. Deploy from GitHub (Recommended - Faster!)
Since your files are already on GitHub, use this faster method:

### Option A: Using Git (Fastest)
1. Log in to Infinity Free control panel
2. Go to **Advanced** → **Terminal** (if available)
3. Run these commands:
   ```bash
   cd /home/u123456789/public_html
   git clone https://github.com/azelarce14/Scholar-Seek.git .
   ```
4. Skip to Step 6

### Option B: Using FileZilla (Traditional)
1. Click **File** → **Site Manager**
2. Click **New Site**
3. Enter:
   - **Host**: your-ftp-host.infinityfree.net
   - **Port**: 21
   - **Protocol**: FTP
   - **User**: your-username
   - **Password**: your-password
4. Click **Connect**

## 5. Upload Your Files from GitHub
1. In FileZilla left panel (Local), navigate to: `c:\xampp\htdocs\scholarseek\`
2. In FileZilla right panel (Remote), navigate to: `/htdocs/`
3. Select all files and folders (Ctrl+A)
4. Right-click → **Upload**
5. Wait for upload to complete (should be faster since files are already compressed)

## 6. Set File Permissions
1. In FileZilla right panel, right-click on **uploads** folder
2. Click **File Attributes**
3. Change permissions to **777**
4. Click **OK**
5. Repeat for **logs** folder

## 7. Configure Database
1. Log in to Infinity Free control panel
2. Click **MySQL Databases**
3. Create new database
4. Note the credentials

## 8. Update Configuration
1. In FileZilla, right-click on **db_connect.php**
2. Click **View/Edit**
3. Update these lines:
   ```php
   $servername = "localhost";
   $username = "your_db_username";
   $password = "your_db_password";
   $database = "your_db_name";
   ```
4. Save and upload

## 9. Create Database Tables
1. In Infinity Free control panel, click **phpMyAdmin**
2. Select your database
3. Click **SQL** tab
4. Paste your database schema
5. Click **Go**

## 10. Test Your Site
1. Open browser
2. Go to: `https://your-domain.infinityfree.app/`
3. Test login with credentials:
   - Email: admin@biliran.edu.ph
   - Password: 123

## Common Issues & Solutions

### Issue: "Database Connection Failed"
**Solution**: 
- Check db_connect.php credentials
- Verify database name is correct
- Ensure database user has proper permissions

### Issue: "File Upload Not Working"
**Solution**:
- Check /uploads/ folder permissions (should be 777)
- Verify folder exists on server
- Check available disk space

### Issue: "CSS/JS Files Not Loading"
**Solution**:
- Verify all files were uploaded
- Check file paths in HTML
- Clear browser cache (Ctrl+Shift+Delete)

### Issue: "White Screen / 500 Error"
**Solution**:
- Check error logs in /logs/ folder
- Verify all PHP files are uploaded
- Check PHP version compatibility

### Issue: "Session Not Working"
**Solution**:
- Verify session.save_path is writable
- Check /logs/ folder permissions
- Clear browser cookies

## Support Resources
- Infinity Free Help: https://www.infinityfree.net/support/
- FileZilla Guide: https://wiki.filezilla-project.org/
- PHP Documentation: https://www.php.net/manual/

## Next Steps After Deployment
1. Create admin account
2. Set up email notifications
3. Configure backup schedule
4. Monitor error logs
5. Test all user roles
6. Set up SSL certificate
7. Configure custom domain (optional)
