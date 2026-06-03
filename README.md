# Golden Route Myanmar

Golden Route Myanmar is a PHP and MySQL based web system for bus ticket booking, tour package booking, company management, payment proof submission, schedule generation, and business reporting.

The system supports customers, Super Admin, Bus Company Admin, and Tour Company Admin.

---

## Main Features

### Customer

* Register and login
* Search bus trips by city and travel date
* View bus company, route, price, seat availability, and schedule
* Choose seats and create booking
* Submit payment proof with screenshot
* View booking history and payment status

### Super Admin

* Manage bus companies and tour companies
* Add, edit, delete, and suspend companies
* Create company admin accounts
* Manage routes and schedules
* Generate trips automatically from schedule templates
* Review payments
* View business reports
* Export business reports as PDF
* Manage events, notifications, settings, and audit logs

### Bus Company Admin

* Manage buses
* Manage bus routes
* Manage schedules
* View bus bookings and trip data

### Tour Company Admin

* Manage tour packages
* Manage tour batches
* View tour bookings

---

## Technologies Used

* PHP
* MySQL / MariaDB
* HTML
* CSS
* Bootstrap
* JavaScript
* XAMPP / LAMPP / MAMP
* phpMyAdmin

---

## Folder Structure

```text
Golden-Route-Myanmar/
├── account/
├── actions/
├── admin/
├── assets/
├── bootstrap/
├── bus_admin/
├── customer/
├── database/
├── includes/
├── storage/
├── tour_admin/
├── uploads/
├── auto_run.php
├── checkout.php
├── config.php
├── config.example.php
├── index.php
├── login.php
├── register.php
├── search_bus.php
└── README.md
```

---

# How to Run This Project From Git Clone

## 1. Install Required Software

Install one of the following depending on your operating system.

### Windows

Install XAMPP.

### Linux

Install XAMPP/LAMPP.

### macOS

Install MAMP or XAMPP.

Required services:

```text
Apache
MySQL / MariaDB
phpMyAdmin
```

---

## 2. Clone the Project

### Windows XAMPP

Open Git Bash or terminal:

```bash
cd C:/xampp/htdocs
git clone https://github.com/Ye89-cpu/Golden-Route-Myanmar.git
cd Golden-Route-Myanmar
```

### Linux LAMPP

```bash
cd /opt/lampp/htdocs
sudo git clone https://github.com/Ye89-cpu/Golden-Route-Myanmar.git
cd Golden-Route-Myanmar
```

If permission is needed:

```bash
sudo chown -R $USER:$USER /opt/lampp/htdocs/Golden-Route-Myanmar
```

### macOS MAMP

```bash
cd /Applications/MAMP/htdocs
git clone https://github.com/Ye89-cpu/Golden-Route-Myanmar.git
cd Golden-Route-Myanmar
```

---

## 3. Start Apache and MySQL

### Windows

Open XAMPP Control Panel and start:

```text
Apache
MySQL
```

### Linux

```bash
sudo /opt/lampp/lampp start
```

### macOS

Open MAMP or XAMPP and start the servers.

---

## 4. Create Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database:

```text
myanmar_bus_tour_booking
```

Then import the SQL file from the project database folder.

Example:

```text
database/myanmar_bus_tour_booking.sql
```

If your SQL file name is different, import the main `.sql` file inside the `database/` folder.

---

## 5. Configure Database Connection

Open:

```text
config.php
```

Make sure the database settings are correct.

For most XAMPP/LAMPP setups:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'myanmar_bus_tour_booking');
```

If your MySQL password is not empty, update `DB_PASS`.

Example:

```php
define('DB_PASS', 'your_mysql_password');
```

---

## 6. Create Required Writable Folders

### Linux / macOS

Run:

```bash
mkdir -p uploads/payment_proofs
mkdir -p uploads/company_logos
mkdir -p storage/logs

chmod -R 777 uploads
chmod -R 777 storage
```

For LAMPP on Linux:

```bash
cd /opt/lampp/htdocs/Golden-Route-Myanmar

sudo mkdir -p uploads/payment_proofs
sudo mkdir -p uploads/company_logos
sudo mkdir -p storage/logs

sudo chmod -R 777 uploads
sudo chmod -R 777 storage
```

### Windows

Create these folders manually if they do not exist:

```text
uploads/payment_proofs/
uploads/company_logos/
storage/logs/
```

Usually Windows XAMPP does not need chmod.

---

## 7. Run the Project

Open the website:

```text
http://localhost/Golden-Route-Myanmar/
```

If the project folder name is different, use that folder name in the URL.

Example:

```text
http://localhost/your-folder-name/
```

---

# Important URLs

Home page:

```text
http://localhost/Golden-Route-Myanmar/
```

Login:

```text
http://localhost/Golden-Route-Myanmar/login.php
```

Register:

```text
http://localhost/Golden-Route-Myanmar/register.php
```

Search bus:

```text
http://localhost/Golden-Route-Myanmar/search_bus.php
```

Tours:

```text
http://localhost/Golden-Route-Myanmar/tours.php
```

Super Admin dashboard:

```text
http://localhost/Golden-Route-Myanmar/admin/dashboard.php
```

Company management:

```text
http://localhost/Golden-Route-Myanmar/admin/companies.php
```

Business reports:

```text
http://localhost/Golden-Route-Myanmar/admin/business_reports.php
```

Manual auto schedule runner:

```text
http://localhost/Golden-Route-Myanmar/auto_run.php
```

phpMyAdmin:

```text
http://localhost/phpmyadmin
```

---

# Auto Schedule Runner

The project includes an auto schedule runner.

It checks active schedule templates and creates missing trips automatically.

Manual test:

```text
http://localhost/Golden-Route-Myanmar/auto_run.php
```

If the result shows:

```text
status => success
generated => 0
skipped => ...
templates => ...
```

That is normal.

Meaning:

```text
generated = new trips created
skipped = trips already existed, so they were not duplicated
templates = active schedule templates found
```

---

# Company Logos

Company logos are stored in:

```text
uploads/company_logos/
```

The `companies.logo` database column should contain paths like:

```text
uploads/company_logos/company-name-1.svg
```

If logos do not show, check:

```text
1. The file exists inside uploads/company_logos/
2. The database logo path is correct
3. Browser cache is cleared using Ctrl + F5
```

---

# Payment Screenshots

Payment screenshots are stored in:

```text
uploads/payment_proofs/
```

This folder must be writable.

On Linux/LAMPP:

```bash
sudo chmod -R 777 uploads
```

---

# Creating Company Admin Accounts

Super Admin can create company admin accounts.

Flow:

```text
Super Admin Login
→ Admin Dashboard
→ Companies
→ Add Company
→ Add Admin
→ Create bus_admin or tour_admin account
```

Company types:

```text
bus_company    = Bus Company
tour_operator  = Tour Company
both           = Bus + Tour Company
```

Admin roles:

```text
bus_admin
tour_admin
```

If company type is `both`, you may create two accounts:

```text
1 bus_admin account
1 tour_admin account
```

---

# Business Reports

Super Admin can open:

```text
admin/business_reports.php
```

Reports include:

```text
Booking report
Payment report
Tour package payment report
Company business table
Revenue summary
PDF export
```

PDF export page:

```text
admin/business_reports_pdf.php
```

---

# Common Problems and Fixes

## 1. Database connection error

Check `config.php`.

Make sure:

```text
DB name is correct
MySQL is running
Username and password are correct
```

---

## 2. Screenshot upload failed

Run:

```bash
sudo chmod -R 777 uploads
```

Make sure this folder exists:

```text
uploads/payment_proofs/
```

---

## 3. Auto schedule lock file error

Run:

```bash
sudo chmod -R 777 storage
```

Make sure this folder exists:

```text
storage/logs/
```

---

## 4. No trips found

Check these tables:

```text
companies
routes
buses
schedule_templates
trips
```

A trip will show only if:

```text
company is approved
route is active
bus is active
trip status is open or scheduled
available seats are greater than 0
trip date matches the search date
```

---

## 5. Company admin cannot login

Check:

```text
users table
company_users table
user role or user_type
company_id link
user status
```

The user must be linked to the company in `company_users`.

---

# Git Commands for Developers

Check changes:

```bash
git status
```

Add files:

```bash
git add .
```

Commit:

```bash
git commit -m "Update project"
```

Pull latest GitHub changes before pushing:

```bash
git pull --rebase origin main
```

Push:

```bash
git push origin main
```

If branch is master:

```bash
git push origin master
```

---

# Recommended .gitignore

```gitignore
storage/logs/*
uploads/payment_proofs/*
*.log
.DS_Store
.env
vendor/
node_modules/
```

Do not ignore `uploads/company_logos/` if demo company logos should appear after cloning.

---

# Stable Setup Checklist

After cloning on a new device:

```text
1. Start Apache and MySQL
2. Import database SQL
3. Check config.php
4. Create uploads/payment_proofs folder
5. Create uploads/company_logos folder
6. Create storage/logs folder
7. Set folder permission if using Linux/macOS
8. Open homepage
9. Login as Super Admin
10. Run auto_run.php if trips need to be generated
```

---

# Project Purpose

Golden Route Myanmar is developed to support digital transformation in Myanmar’s bus ticketing and tour booking industry. It allows customers, companies, and administrators to manage travel services through one organized platform.
