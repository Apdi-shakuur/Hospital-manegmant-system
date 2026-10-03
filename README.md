# ModernCare Hospital Management System (HMS)

A complete, professional, responsive, and secure **Hospital Management System (HMS)** built with pure **PHP 8+ (PDO)**, **MySQL 8+**, **HTML5**, **Vanilla CSS3**, and **Vanilla JavaScript**.

---

## 🌟 Key Features

### 1. Multi-Role Authentication & Access Control (RBAC)
- 7 distinct user roles: **Admin**, **Doctor**, **Nurse**, **Receptionist**, **Pharmacist**, **Laboratory Staff**, and **Patient**.
- Session security with automatic inactivity timeout (30 minutes), BCRYPT password hashing (`password_hash()`), CSRF form protection tokens, and XSS output escaping (`htmlspecialchars()`).

### 2. Executive Admin Dashboard & Analytics
- Live KPI Metric Cards: Total Patients, Total Doctors, Total Nurses, Today's Appointments, Pending/Completed Appointments, Available Medicines, Low Stock Alerts, Pending Lab Orders, and Today's Revenue.
- Dynamic SVG & HTML5 Bar charts for department staffing metrics and real-time security activity stream.

### 3. Patient Management & 360-Degree Profile
- Patient registration form with auto-generated Patient Numbers (`PAT-2026-XXXX`).
- Filter & search by Name, ID, Phone Number, and Blood Group.
- **360-Degree Patient View**: Comprehensive dashboard tabulating Vital Signs history, Doctor Consultations, Diagnoses, Prescriptions, Lab Results, and Invoices.

### 4. Doctor & Department Management
- Manage clinical departments (General Medicine, Cardiology, Surgery, Pediatrics, Emergency, Radiology, Lab, Pharmacy, OBGYN, Dentistry).
- Doctor profiles with medical license tracking, specializations, experience, and weekly availability schedules.

### 5. Appointment Scheduler with Doctor Conflict Prevention
- Booking portal with **Doctor Double-Booking Conflict Prevention** (blocks scheduling if doctor has an existing non-cancelled slot at the exact same date and time).
- Status workflow: `Scheduled` &rarr; `Confirmed` &rarr; `Completed` / `Cancelled` / `No Show`.

### 6. Clinical Consultation & Vital Signs Intake
- Consultation recorder for chief complaints, observed symptoms, diagnoses, treatment plans, and follow-up dates.
- Vital signs recorder tracking Temperature (°C), Blood Pressure (mmHg), Heart Rate (bpm), Respiratory Rate, Oxygen Saturation (%), Weight (kg), Height (cm), and **Auto-Calculated BMI**.

### 7. Pharmacy Inventory & Prescription Dispensing Engine
- Stock management with batch number tracking, expiry date warnings, unit pricing, and low-stock reorder alerts.
- Stock-In and Stock-Out inventory adjustment logger.
- **Prescription Dispensing Engine**: Validates stock levels, **auto-deducts inventory quantities upon dispensing**, and **blocks dispensing** if stock is insufficient.

### 8. Laboratory Request & Results Portal
- Lab test catalog with normal reference ranges and prices.
- Status workflow: `Requested` &rarr; `Sample Collected` &rarr; `Processing` &rarr; `Completed`.
- Result entry interface with assessment flags (`Normal`, `Abnormal`, `Critical`).

### 9. Billing, Payments & Printable Invoices
- Itemized billing statement generator combining consultation fees, lab tests, medications, and procedures.
- Record payments via **Cash**, **Card**, **Mobile Money**, or **Bank Transfer**, automatically updating invoice status to `Partially Paid` or `Paid`.
- Print-friendly invoice and receipt template (`@media print` formatted).

### 10. System Reports & Notifications
- Date range filterable executive reports covering financial revenue, patient registration trends, appointment statistics, pharmacy stock valuation, and lab orders.
- In-app notification drawer for low stock alerts, new appointments, and unpaid invoices.

---

## 🛠️ Technology Stack

- **Frontend**: HTML5, Vanilla CSS3 (Custom CSS variables, Glassmorphism, Responsive Drawer Sidebar), Vanilla JavaScript (Fetch API / AJAX).
- **Backend**: Pure PHP 8.0+ (No frameworks, pure object-oriented PDO).
- **Database**: MySQL 8.0+ (21 normalized tables, Foreign Keys, Indexes).
- **Server**: Apache (XAMPP / WAMP / LAMP compatible).

---

## 🔑 Default Demo Login Credentials

All accounts are pre-configured in the sample database seed with password: **`password123`**

| Role | Username / Email | Default Password | Access Level |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `password123` | Full System Control & Reports |
| **Doctor** | `dr.smith` | `password123` | Patient Consultations, Records, Rx, Lab Requests |
| **Doctor** | `dr.johnson` | `password123` | Patient Consultations & Appointments |
| **Nurse** | `nurse.mary` | `password123` | Patient Intake, Vital Signs Recording |
| **Receptionist** | `receptionist` | `password123` | Patient Registration, Appointments, Billing |
| **Pharmacist** | `pharmacist` | `password123` | Medicine Inventory, Prescription Dispensing |
| **Lab Staff** | `labtech` | `password123` | Lab Test Processing & Results Entry |
| **Patient** | `patient.david` | `password123` | Personal Profile, History & Invoices |

---

## 🚀 Quick Start / Local Installation (XAMPP)

1. **Install XAMPP** (or any Apache + PHP 8+ + MySQL stack).
2. **Clone or Copy Project**:
   Copy the `hospital-management-system` folder into your XAMPP `htdocs` directory:
   ```text
   C:\xampp\htdocs\hospital-management-system\
   ```
3. **Start Apache & MySQL** in the XAMPP Control Panel.
4. **Import Database**:
   - Open phpMyAdmin: `http://localhost/phpmyadmin/`
   - Create a new database named `hospital_db`.
   - Select `hospital_db` and click the **Import** tab.
   - Choose the SQL file located at `database/hospital_management.sql` and click **Go**.
5. **Verify Database Configuration**:
   Open `config/database.php` and verify connection parameters:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'hospital_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
6. **Open in Browser**:
   Navigate to `http://localhost/hospital-management-system/` or `http://localhost/` (if placed directly in htdocs).

---

## 📂 Project Structure

```text
hospital-management-system/
│
├── config/
│   └── database.php           # Singleton PDO connection handler
├── includes/
│   ├── auth.php               # Session security, authentication & RBAC functions
│   ├── functions.php          # CSRF, XSS sanitization, activity logging, BMI calculator
│   ├── header.php             # HTML head and stylesheet loader
│   ├── sidebar.php            # Role-aware drawer navigation sidebar
│   ├── navbar.php             # Top navigation bar & notification dropdown
│   └── footer.php             # Footer & script importer
│
├── assets/
│   ├── css/
│   │   ├── style.css          # Design system, CSS variables, components, modals
│   │   ├── dashboard.css      # KPI grid, charts, activity feed timeline
│   │   └── responsive.css     # Mobile breakpoints & print styles
│   └── js/
│       ├── app.js             # Global Fetch API helper, toast notifications, modals
│       └── dashboard.js       # Real-time metrics fetcher & SVG chart renderer
│
├── auth/
│   ├── login.php              # Login page with demo account autofill pills
│   ├── logout.php             # Session clearing & logout script
│   └── forgot-password.php    # Password reset interface
│
├── api/
│   ├── dashboard.php          # Live statistics & chart metrics API
│   └── notifications.php      # User notification queue API
│
├── admin/
│   ├── dashboard.php          # Executive Overview & KPI Dashboard
│   ├── users.php              # User accounts & role permissions
│   ├── doctors.php            # Doctor directory & schedule management
│   ├── nurses.php             # Nurse directory & staffing
│   └── departments.php       # Clinical department configuration
│
├── patients/
│   ├── index.php              # Patients directory with search & filters
│   └── profile.php            # 360-degree patient view (Vitals, Records, Rx, Labs, Invoices)
│
├── appointments/
│   └── index.php              # Scheduler with doctor conflict checker
│
├── medical-records/
│   └── index.php              # Consultation recorder & vital signs intake
│
├── prescriptions/
│   └── index.php              # Prescription writer & history
│
├── pharmacy/
│   ├── index.php              # Medicine inventory, batch tracking, stock adjustment
│   └── dispensing.php         # Prescription dispensing & auto stock reduction
│
├── laboratory/
│   └── index.php              # Lab request queue & test results entry
│
├── billing/
│   ├── index.php              # Invoice generator
│   ├── payments.php           # Payment collections log (Cash/Card/Mobile/Bank)
│   └── view.php               # Printable invoice & receipt template
│
├── reports/
│   └── index.php              # System analytics, revenue, & date-range reports
│
├── database/
│   └── hospital_management.sql# Full database DDL schema and realistic seed data
│
├── index.php                  # Root entry router
└── README.md                  # System documentation
```

---

## 🔒 Security Practices Implemented

- **Prepared Statements**: All database operations utilize PDO prepared statements with parameter binding to prevent SQL Injection.
- **Password Security**: Passwords stored exclusively as BCRYPT hashes using `password_hash()`.
- **CSRF Defense**: Cryptographic session tokens (`csrf_token`) validated on all POST form submissions.
- **XSS Prevention**: HTML output escaped using `htmlspecialchars()` with `ENT_QUOTES` UTF-8 encoding.
- **Access Control (RBAC)**: Strict role checking using `require_role()` prevents unauthorized direct URL traversal.

---
