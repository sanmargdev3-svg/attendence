# Face Recognition Attendance System

A comprehensive web-based attendance management system with face recognition, location tracking, and advanced punch in/out functionality. Built with PHP, MySQL, and Face-api.js.

---

## 🎯 Project Overview

This system provides:
- **Face Recognition Attendance**: Automatic punch in/out via browser camera
- **Location Tracking**: Records punch in/out locations
- **Multiple Punch Records**: Support for multiple punches per day (dash-boarding + auto-face)
- **Employee Management**: Add, edit, delete employees with conditional fields
- **Monthly Reports**: Export attendance data with first/last punch times
- **Role-Based Access**: SuperAdmin, Admin, Face Operator, and Employee roles
- **Dashboard Views**: Multiple dashboards based on user role

---

## ✨ Key Features

### **Employee Management**
- ✅ Add/Edit/Delete employees
- ✅ Manage employee photos for face recognition
- ✅ Conditional fields (Date of Exit shows only when Status = "Resign")
- ✅ Email (optional) | Date of Joining (mandatory)
- ✅ Edit employees via modal popup (auto-closes on success)
- ✅ Search and filter by department

### **Face Attendance System**
- ✅ Real-time face detection using face-api.js
- ✅ Automatic punch in/out on face recognition
- ✅ Browser time synchronization (fixes timezone issues)
- ✅ 100ms detection loop with 3-second cooldown
- ✅ Supports multiple detections per day (alternating pattern)
- ✅ Records selfies for audit trail

### **Attendance Recording**
- ✅ Punch in/out with TIME type columns (HH:MM:SS)
- ✅ Location tracking for each punch
- ✅ Multiple records per employee per day
- ✅ Alternating logic: 1st=IN, 2nd=OUT, 3rd=IN (new record)
- ✅ Browser time sent to API (resolves timezone mismatch)
- ✅ Automatic status based on punch records

### **Attendance Display & Export**
- ✅ WebPage View: Shows all punch records with locations visible
- ✅ Excel Export: Shows only first Punch In + last Punch Out (summary format)
- ✅ Excel Export: Location columns removed from exports
- ✅ Multiple views for different user roles
- ✅ Filter by date range, employee, department

### **Conditional Fields**
- ✅ Date of Exit: Required only when Status = "Resign"
- ✅ Auto-show/hide based on status selection
- ✅ Works in both Add and Edit forms
- ✅ Modal and inline forms supported

### **Dashboards**
- ✅ SuperAdmin Dashboard: Full system overview
- ✅ Admin Dashboard: Employee management & attendance
- ✅ Face Operator Dashboard: Today's face attendance records
- ✅ Employee Dashboard: Personal punch in/out + records
- ✅ Face Attendance Dashboard: Real-time camera feed

---

## 📁 Project Structure

```
attendance/
├── admin/
│   ├── admin_dashboard.php          # Admin overview dashboard
│   ├── admin.php                    # Admin panel
│   ├── attendance.php               # View all attendance records
│   ├── dashboard.php                # SuperAdmin main dashboard
│   ├── employees.php                # Manage employees (with modal edit)
│   ├── companies.php                # Manage companies
│   ├── department.php               # Manage departments
│   ├── shifts.php                   # Manage shifts
│   ├── locations.php                # Manage locations
│   ├── manage_employee_photos.php   # Handle employee photos
│   ├── export_all_departments.php   # Export monthly reports (no locations)
│   ├── export_location_excel.php    # Export with location summary
│   └── face_recognition_dashboard.php # Face operator dashboard
│
├── api/
│   ├── record_attendance.php        # Punch in/out recording (with browser_time)
│   ├── get_employees.php            # Employee list API
│   ├── upload_employee_photo.php    # Photo upload API
│   └── delete_employee_photo.php    # Photo delete API
│
├── auth/
│   ├── login.php                    # Login page
│   ├── login_process.php            # Process login
│   └── logout.php                   # Logout handler
│
├── config/
│   ├── db.php                       # Database connection
│   └── db_migration.sql             # Database schema
│
├── employee/
│   ├── dashboard.php                # Employee dashboard
│   ├── punch_in.php                 # Manual punch in
│   ├── punch_out.php                # Manual punch out
│   └── my_attendance.php            # Personal attendance view
│
├── uploads/
│   ├── employee_photos/             # Employee face photos
│   ├── employee_faces/              # Face descriptors
│   ├── face_captures/               # Captured selfies
│   └── selfies/                     # Backup of selfies
│
├── assets/
│   ├── css/
│   │   └── style.css                # Main stylesheet
│   └── js/
│       └── script.js                # JavaScript functionality
│
├── auto_face_attendance.php         # Main face detection page (with browser_time)
├── face_dashboard.php               # Face attendance dashboard (FIXED)
├── user_attendance.php              # User attendance view
├── user_dashboard.php               # User dashboard
├── index.php                        # Home page / redirects
├── attendance.sql                   # Database export
└── README.md                        # This file
```

---

## 🗄️ Database Schema

### **Users Table**
```sql
id, name, email, password, role (employee/admin/suparadmin/face_operator),
department, employee_id, company, phone, shift_time, location,
date_of_joining, date_of_exit, status (Working/Resign),
sex (Male/Female/Other), week_off, password_set, created_at
```

### **Attendance Table**
```sql
id, user_id, date (DATE), punch_in (TIME), punch_out (TIME),
punch_in_location, punch_out_location, status, selfie_punchin, selfie_punchout, created_at
```

### **Other Tables**
- departments, companies, shifts, locations

---

## 🚀 Installation & Setup

1. **Import Database**: `attendance.sql` into MySQL
2. **Configure**: Edit `config/db.php` with database credentials
3. **Create Admin**: Run `admin/initialize_database.php`
4. **Access**: `http://localhost/attendance/`
5. **Login**: Use admin credentials

---

## 👥 User Roles & Permissions

- **SuperAdmin**: Full system control
- **Admin**: Employee management & attendance view
- **Face Operator**: Today's face attendance only
- **Employee**: Personal punch in/out & records

---

## 💻 Usage Guide

### **Employee Management** (Admin)
1. Go to Admin → Employees
2. Click **➕ Add New Employee**
3. Fill form (mandatory: Name, ID, Phone, Department, Company, Shift, Location, Date of Joining, Status)
4. If Status = "Resign": Date of Exit field appears (mandatory)
5. Click **✓ Add Employee**
6. To Edit: Click **Edit** → Modal opens → Edit → **✓ Update** → Auto-closes

### **Face Attendance** (Operator)
1. Go to **Face Attendance**
2. Camera activates automatically
3. Face detected → Punch IN recorded
4. Face detected again (3sec cooldown) → Punch OUT recorded

### **Manual Punch In/Out** (Employee)
1. Go to **Employee Dashboard**
2. Click **Punch In** (records browser time)
3. Later, click **Punch Out**

### **View Attendance**
- **Admin**: Admin → Attendance (all records with locations)
- **Face Operator**: Face Dashboard (today's records with punch in/out)
- **Employee**: My Attendance (personal records)

### **Export Reports**
- Click **Export Excel**
- Format: First Punch In + Last Punch Out (no locations)

---

## 🔧 Key Technical Details

### **Timezone Fix (Browser Time)**
- Problem: Server time ≠ Browser time
- Solution: JavaScript sends browser_time to API
- File: `auto_face_attendance.php` + `api/record_attendance.php`

### **Alternating Punch Logic**
- Problem: Multiple detections created new records
- Solution: Query uses `ORDER BY id DESC LIMIT 1` to get LAST record
- Pattern: 1st=IN, 2nd=OUT, 3rd=New IN, 4th=OUT

### **Conditional Fields**
- Date of Exit shows only when Status = "Resign"
- JavaScript: `toggleExitDateField()` in `admin/employees.php`

### **Excel Export**
- Shows: First Punch In + Last Punch Out (summary)
- Excludes: Location columns (per requirement)

---

## 🐛 Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Face Dashboard Empty | Wrong column names | Fixed: uses `date`, `punch_in`, `punch_out` |
| Wrong Punch Times | Timezone mismatch | Fixed: sends browser_time to API |
| Multiple Records | Query returns oldest | Fixed: `ORDER BY DESC LIMIT 1` |
| Edit Form Below Records | Inline form display | Fixed: Modal popup with auto-close |

---

## 🔒 Security

- ✅ Prepared statements (SQL injection prevention)
- ✅ Bcrypt password hashing
- ✅ Session-based authentication
- ✅ Role-based access control
- ✅ Input sanitization

---

## 📝 Recent Updates (March 2026)

- ✅ Fixed timezone mismatch (browser_time parameter)
- ✅ Fixed alternating punch logic (ORDER BY DESC LIMIT 1)
- ✅ Added location tracking, removed from exports
- ✅ Conditional Date of Exit (Resign status)
- ✅ Employee edit → Modal with auto-close
- ✅ Fixed face_dashboard.php queries
- ✅ Added Punch Out to dashboard display

---

## 📞 Requirements

- PHP 7.4+
- MySQL 5.7+
- Apache/XAMPP
- Modern browser (Chrome, Firefox, Edge, Safari)
- Camera access for face attendance

---

**Last Updated**: May 2026 | **Status**: ✅ Fully Operational
