## Project Structure
rent-a-tool/
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   └── images/                # Tool and user images
├── database/
│   └── setup.sql              # Database schema
├── includes/
│   └── db.php   # Database connection & helper functions
│   └──pdf-report.php
├── pages/
│   ├── admin/                 # Admin pages
│   │   ├── dashboard.php
│   │   ├── tools.php
│   │   ├── rentals.php
│   │   ├── users.php
│   │   ├── deposits.php
│   │   ├── reports.php
│   │   ├── activity-logs.php
│   │   └── save-tool.php
│   │   └──download-reports.php
│   │   └──rental-details.php
│   └── user/                  # User pages
│       ├── dashboard.php
│       ├── browse-tools.php
│       ├── rent-tool.php
│       ├── my-rentals.php
│       ├── deposits.php
│       ├── reports.php
│       └── profile.php
│       └──download-reports.php
│       └──rental-details.php
├── Forgot-password.php        # Penalty to wrong Password
├── index.php                  # Home page
├── login.php                  # Login page
├── register.php               # Registration page
├── logout.php                 # Logout handler
└── Project Structure.md       # This file
