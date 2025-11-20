# ScholarSeek - Complete Infinity Free Deployment Guide

## Phase 1: Create Infinity Free Account & Get Hosting

### Step 1.1: Sign Up for Infinity Free
1. Go to https://www.infinityfree.net/
2. Click **Sign Up**
3. Enter your email and password
4. Verify your email
5. Log in to your account

### Step 1.2: Create a Website
1. In the Infinity Free dashboard, click **Create Website**
2. Choose a domain name (e.g., `scholarseek.infinityfree.app`)
3. Click **Create**
4. Wait for setup to complete (usually 1-2 minutes)

### Step 1.3: Get Your FTP Credentials
1. In the dashboard, click on your website
2. Go to **FTP Accounts** (or **Account Settings**)
3. You'll see:
   - **FTP Host**: (something like `ftpupload.net`)
   - **FTP Username**: (your username)
   - **FTP Password**: (your password)
4. **Write these down** - you'll need them soon

---

## Phase 2: Download & Install FileZilla

### Step 2.1: Download FileZilla
1. Go to https://filezilla-project.org/download.php
2. Download **FileZilla Client** (not Server)
3. Run the installer
4. Follow the installation wizard
5. Open FileZilla

### Step 2.2: Connect to Your Server
1. In FileZilla, click **File** → **Site Manager**
2. Click **New Site**
3. Fill in these details:
   - **Host**: Your FTP Host (from Step 1.3)
   - **Port**: 21
   - **Protocol**: FTP
   - **Encryption**: Only use plain FTP
   - **Logon Type**: Normal
   - **User**: Your FTP Username (from Step 1.3)
   - **Password**: Your FTP Password (from Step 1.3)
4. Click **Connect**
5. You should now see your server files on the right panel

---

## Phase 3: Upload Your ScholarSeek Files

### Step 3.1: Navigate to Upload Folder
1. In FileZilla right panel (Remote), you should see folders
2. Look for `/htdocs/` or `/public_html/`
3. Double-click to enter that folder
4. **Delete any existing files** (like index.html)

### Step 3.2: Upload Files from Your Computer
1. In FileZilla left panel (Local), navigate to:
   - `C:\xampp\htdocs\scholarseek\`
2. Select **ALL files and folders** (Ctrl+A)
3. Right-click → **Upload**
4. Wait for upload to complete
   - You should see a progress bar
   - It may take 5-15 minutes depending on your internet speed
5. Once done, you'll see all your files in the right panel

### Step 3.3: Verify Upload
Check that these files are uploaded:
- ✅ `login.php`
- ✅ `admin_dashboard.php`
- ✅ `staff_dashboard.php`
- ✅ `student_dashboard.php`
- ✅ `register.php`
- ✅ `assets/` folder
- ✅ `config/` folder
- ✅ `uploads/` folder
- ✅ `logs/` folder

---

## Phase 4: Set File Permissions

### Step 4.1: Set Uploads Folder Permissions
1. In FileZilla right panel, right-click on **uploads** folder
2. Click **File Attributes**
3. Change the numeric value to **777**
4. Click **OK**

### Step 4.2: Set Logs Folder Permissions
1. In FileZilla right panel, right-click on **logs** folder
2. Click **File Attributes**
3. Change the numeric value to **777**
4. Click **OK**

---

## Phase 5: Create & Configure Database

### Step 5.1: Create Database
1. Go back to Infinity Free control panel
2. Click on your website
3. Go to **MySQL Databases**
4. Click **Create New Database**
5. Enter a database name (e.g., `scholarseek_db`)
6. Click **Create**
7. You'll see:
   - **Database Name**: scholarseek_db
   - **Database Username**: (something like `id123456_scholarseek`)
   - **Database Password**: (auto-generated)
8. **Write these down**

### Step 5.2: Update Configuration File
1. In FileZilla, right-click on **db_connect.php**
2. Click **View/Edit**
3. Find these lines (around line 10-15):
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $database = "scholarseek";
   ```
4. Replace with your Infinity Free database info:
   ```php
   $servername = "localhost";
   $username = "id123456_scholarseek";  // Your DB username
   $password = "your_db_password";      // Your DB password
   $database = "id123456_scholarseek";  // Your DB name
   ```
5. Save the file (Ctrl+S)
6. FileZilla will ask to upload - click **Yes**

---

## Phase 6: Create Database Tables

### Step 6.1: Access phpMyAdmin
1. Go back to Infinity Free control panel
2. Click on your website
3. Go to **MySQL Databases**
4. Click **phpMyAdmin** button
5. You'll be logged in automatically

### Step 6.2: Create Tables
1. In phpMyAdmin, click on your database name (left sidebar)
2. Click the **SQL** tab
3. You need to create tables. Here's a basic schema:

```sql
-- Users table
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  role VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Staff table
CREATE TABLE staff (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scholarships table
CREATE TABLE scholarships (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  amount DECIMAL(10, 2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Applications table
CREATE TABLE applications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT,
  scholarship_id INT,
  status VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id)
);
```

4. Paste the SQL code into the text area
5. Click **Go** button
6. Tables should be created successfully

### Step 6.3: Insert Test Data (Optional)
1. Still in phpMyAdmin, click **SQL** tab again
2. Paste this code to create a test admin account:

```sql
INSERT INTO users (email, password, fullname, role) 
VALUES ('admin@biliran.edu.ph', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin');

INSERT INTO staff (email, password, fullname) 
VALUES ('staff@biliran.edu.ph', '$2y$10$YourHashedPasswordHere', 'Staff Member');
```

3. Click **Go**

---

## Phase 7: Test Your Deployment

### Step 7.1: Access Your Website
1. Open your browser
2. Go to your Infinity Free domain:
   - `https://scholarseek.infinityfree.app/` (or your custom domain)
3. You should see the ScholarSeek login page

### Step 7.2: Test Login
1. Try logging in with:
   - **Email**: admin@biliran.edu.ph
   - **Password**: 123
2. If it works, you're deployed! ✅

### Step 7.3: Test Key Features
- [ ] Login page loads
- [ ] Admin dashboard accessible
- [ ] Staff dashboard accessible
- [ ] Student registration works
- [ ] File uploads work (check /uploads/ folder)
- [ ] CSS/JS files load correctly

---

## Phase 8: Troubleshooting

### Issue: "Database Connection Failed"
**Solution**:
1. Check your `db_connect.php` credentials
2. Make sure database name, username, and password are correct
3. Verify database was created in Infinity Free

### Issue: "404 Page Not Found"
**Solution**:
1. Make sure all files were uploaded to `/htdocs/`
2. Check that `.htaccess` file was uploaded
3. Try accessing `index.html` directly

### Issue: "File Upload Not Working"
**Solution**:
1. Check `/uploads/` folder permissions (should be 777)
2. Verify `/uploads/` folder exists
3. Check available disk space in Infinity Free

### Issue: "White Screen / 500 Error"
**Solution**:
1. Check error logs in `/logs/` folder via FTP
2. Enable PHP error reporting (contact Infinity Free support)
3. Verify all PHP files were uploaded correctly

### Issue: "CSS/JS Not Loading"
**Solution**:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Check that `/assets/` folder was uploaded
3. Verify file paths in HTML are correct

---

## Phase 9: Post-Deployment Tasks

### Step 9.1: Create Admin Account
1. Log in to your site
2. Create a new admin account through the admin panel
3. Test all admin features

### Step 9.2: Backup Your Database
1. In phpMyAdmin, select your database
2. Click **Export**
3. Click **Go** to download backup
4. Save it safely on your computer

### Step 9.3: Monitor Logs
1. Via FTP, check `/logs/` folder regularly
2. Look for any error messages
3. Fix issues as they arise

### Step 9.4: Test All User Roles
- [ ] Admin login and dashboard
- [ ] Staff login and dashboard
- [ ] Student registration and login
- [ ] Scholarship application process
- [ ] File uploads and downloads

---

## Important Notes

⚠️ **Infinity Free Limitations**:
- Free tier has 5GB storage
- Database limited to 1GB
- Some PHP functions may be disabled
- Uptime is best-effort (not guaranteed)
- No email sending (may need workaround)

✅ **Best Practices**:
- Keep regular backups
- Monitor error logs
- Test after each update
- Keep your GitHub repository updated
- Document any customizations

---

## Quick Reference

| Item | Value |
|------|-------|
| Website URL | https://your-domain.infinityfree.app |
| FTP Host | From Infinity Free account |
| FTP Port | 21 |
| Database Host | localhost |
| Upload Folder | /htdocs/ or /public_html/ |
| Permissions | 777 for /uploads/ and /logs/ |

---

## Support Resources

- **Infinity Free Help**: https://www.infinityfree.net/support/
- **FileZilla Guide**: https://wiki.filezilla-project.org/
- **PHP Documentation**: https://www.php.net/manual/
- **MySQL Documentation**: https://dev.mysql.com/doc/

---

## Next Steps

1. ✅ Complete all phases above
2. ✅ Test your deployment
3. ✅ Create admin account
4. ✅ Invite staff and students
5. ✅ Monitor logs and performance
6. ✅ Keep backups updated

**Your ScholarSeek system is now live! 🎉**
