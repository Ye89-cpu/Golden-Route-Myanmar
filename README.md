# Golden Route Myanmar

Golden Route Myanmar is a web-based bus ticket and tour booking management system. The system allows customers to search bus trips, book seats, submit payment proof, and manage their booking history. It also supports multiple admin roles such as Super Admin, Bus Company Admin, and Tour Admin.

## Project Features

### Customer Features

* Register and login
* Search available bus trips by route and date
* View bus company, route, price, departure time, arrival time, and available seats
* Select seats and create booking
* Submit payment proof with screenshot
* View booking history and payment status
* Receive system notifications

### Super Admin Features

* Manage bus companies and tour companies
* Approve company accounts
* Manage cities
* Manage bus routes
* Manage route schedules
* Auto-generate trips from schedule templates
* Review bookings and payments
* Manage notifications and system data

### Bus Company Admin Features

* Manage buses
* Manage routes
* Manage schedules
* View company bookings
* Track trip and payment status

### Tour Admin Features

* Manage tour packages
* Manage tour batches
* View tour bookings
* Manage tour-related information

## Technologies Used

* PHP
* MySQL / MariaDB
* HTML
* CSS
* Bootstrap
* JavaScript
* XAMPP / LAMPP
* phpMyAdmin

## Project Folder Structure

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
├── index.php
├── login.php
├── register.php
├── search_bus.php
└── README.md
```

## How to Run the Project Locally

### 1. Copy the Project to LAMPP/XAMPP

For Linux LAMPP:

```bash
sudo cp -r Golden-Route-Myanmar /opt/lampp/htdocs/
```

Go to the project folder:

```bash
cd /opt/lampp/htdocs/Golden-Route-Myanmar
```

### 2. Start Apache and MySQL

For Linux LAMPP:

```bash
sudo /opt/lampp/lampp start
```

Or open XAMPP/LAMPP Control Panel and start:

```text
Apache
MySQL
```

### 3. Create Database

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

### 4. Configure Database Connection

Open:

```text
config.php
```

Check database settings:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'myanmar_bus_tour_booking');
```

If your MySQL password is different, update `DB_PASS`.

### 5. Fix Upload and Log Folder Permission

For Linux/LAMPP, run:

```bash
cd /opt/lampp/htdocs/Golden-Route-Myanmar

sudo mkdir -p uploads/payment_proofs
sudo mkdir -p storage/logs

sudo chmod -R 777 uploads
sudo chmod -R 777 storage
```

This is required for payment screenshot upload and auto schedule log files.

### 6. Run the Website

Open browser:

```text
http://localhost/Golden-Route-Myanmar/
```

## Auto Schedule Runner

The system includes an auto schedule runner.

Manual test URL:

```text
http://localhost/Golden-Route-Myanmar/auto_run.php
```

This function checks active schedule templates and generates missing trips automatically.

If the result shows:

```text
status => success
generated => 0
skipped => ...
templates => ...
```

It means the runner is working. `skipped` means trips already exist and were not duplicated.

## Important Notes

* Routes must be created before schedules.
* Buses must exist before schedules can generate trips.
* Schedule templates must be active.
* Trips must have `open` or `scheduled` status to appear in customer search.
* Payment screenshot folder must have write permission.
* Uploaded payment screenshots are stored in:

```text
uploads/payment_proofs/
```

## Recommended Git Ignore

Create a `.gitignore` file and add:

```gitignore
uploads/payment_proofs/*
storage/logs/*
*.log
.DS_Store
.env
vendor/
node_modules/
```

## Main Project URLs

Home page:

```text
http://localhost/Golden-Route-Myanmar/
```

Bus search:

```text
http://localhost/Golden-Route-Myanmar/search_bus.php
```

Auto schedule runner:

```text
http://localhost/Golden-Route-Myanmar/auto_run.php
```

phpMyAdmin:

```text
http://localhost/phpmyadmin
```

## Project Purpose

Golden Route Myanmar is developed to support digital transformation in Myanmar’s transportation and travel industry. It helps customers find bus trips and tours more easily while allowing companies and administrators to manage routes, schedules, bookings, and payments in one platform.
