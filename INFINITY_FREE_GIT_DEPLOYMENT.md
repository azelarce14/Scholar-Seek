# ScholarSeek - Infinity Free Deployment Using Git (No FileZilla)

## Simple 5-Step Deployment

---

## Step 1: Create Infinity Free Account

1. Go to https://www.infinityfree.net/
2. Click **Sign Up**
3. Enter your email and password
4. Verify your email
5. Log in to your account
6. Click **Create Website**
7. Choose a domain name (e.g., `scholarseek.infinityfree.app`)
8. Click **Create**
9. Wait 1-2 minutes for setup

---

## Step 2: Access the File Manager (NOT Terminal)

**Important**: Infinity Free free tier may not have terminal access. Use **File Manager** instead.

### How to Find File Manager:

1. Log in to Infinity Free control panel
2. Click on your website name
3. Look for **File Manager** button (usually in the main menu)
4. Click it
5. A new window opens showing your files

### What You'll See:
- Folders like: `htdocs`, `public_html`, `mail`, etc.
- Double-click **htdocs** or **public_html** to enter it

---

## Step 3: Upload Your Files Using File Manager

### Method A: Upload ZIP File (Fastest)

1. On your computer, go to `C:\xampp\htdocs\scholarseek\`
2. Select all files (Ctrl+A)
3. Right-click → **Send to** → **Compressed (zipped) folder**
4. Wait for ZIP file to be created
5. In Infinity Free File Manager:
   - Click **Upload** button
   - Select your ZIP file
   - Wait for upload
6. Right-click the ZIP file → **Extract**
7. Delete the ZIP file

### Method B: Upload Individual Files

1. In Infinity Free File Manager, click **Upload**
2. Select files from `C:\xampp\htdocs\scholarseek\`
3. Upload them
4. Repeat for all files and folders

---

## Step 4: Create & Configure Database

### Step 4.1: Create Database
1. In Infinity Free control panel, go to **MySQL Databases**
2. Click **Create New Database**
3. Enter name: `scholarseek_db`
4. Click **Create**
5. You'll see:
   - **Database Name**: `id123456_scholarseek_db`
   - **Database Username**: `id123456_scholarseek`
   - **Database Password**: (auto-generated)
6. **Copy these and save them**

### Step 4.2: Update Configuration File
1. In File Manager, find **db_connect.php**
2. Right-click → **Edit** (or **View/Edit**)
3. Find these lines (around line 10-15):
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $database = "scholarseek";
   ```
4. Replace with your database info:
   ```php
   $servername = "localhost";
   $username = "id123456_scholarseek";
   $password = "your_password_here";
   $database = "id123456_scholarseek_db";
   ```
5. Click **Save**

### Step 4.3: Create Database Tables
1. In Infinity Free control panel, go to **MySQL Databases**
2. Click **phpMyAdmin**
3. Select your database (left sidebar)
4. Click **SQL** tab
5. Paste this code:

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

6. Click **Go**
7. Tables created! ✅

---

## Step 5: Set File Permissions

### In File Manager:

1. Right-click on **uploads** folder
2. Click **Change Permissions** or **Properties**
3. Set to **777** (read, write, execute)
4. Click **OK**
5. Repeat for **logs** folder

---

## Step 6: Test Your Site

1. Open your browser
2. Go to: `https://your-domain.infinityfree.app/`
3. You should see the ScholarSeek login page
4. Try logging in with:
   - **Email**: admin@biliran.edu.ph
   - **Password**: 123

---

## Troubleshooting

### Issue: "Database Connection Failed"
- Check `db_connect.php` credentials
- Make sure database name is correct
- Verify database was created

### Issue: "404 Page Not Found"
- Make sure files are in `/htdocs/` folder
- Check that `index.html` or `login.php` exists
- Try accessing `login.php` directly

### Issue: "File Upload Not Working"
- Check `/uploads/` folder permissions (777)
- Verify `/uploads/` folder exists
- Check available disk space

### Issue: "White Screen"
- Check error logs in `/logs/` folder
- Verify all PHP files were uploaded
- Check database connection

---

## Summary

✅ **No FileZilla needed**
✅ **No Terminal needed**
✅ **Just use File Manager**
✅ **Upload files directly**
✅ **Configure database**
✅ **Done!**

**Total Time**: 20-30 minutes

---

## Important Notes

- Infinity Free free tier has 5GB storage
- Database limited to 1GB
- Some PHP functions may be disabled
- Keep regular backups
- Monitor error logs

---

## Next Steps

1. ✅ Create Infinity Free account
2. ✅ Upload files via File Manager
3. ✅ Create database
4. ✅ Configure db_connect.php
5. ✅ Create tables
6. ✅ Test login
7. ✅ Create admin account
8. ✅ Invite users

**Your ScholarSeek system is now live! 🎉**
