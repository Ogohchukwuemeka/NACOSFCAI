# NACOS Departmental Digital Portal

A production-ready PHP/MySQL portal for the NACOS departmental community with authentication, announcements, payments, resources, events, search, settings, and PDF-ready certificate/ID-card workflow.

## Quick Start

1. Create a MySQL database named `nacosfcai`.
2. Import [database.sql](database.sql) into phpMyAdmin or MySQL.
3. Copy [.env.example](.env.example) to `.env` and update the database settings.
4. Install dependencies with Composer:
   `composer install`
5. Open the portal at `http://localhost/NACOS`.

## Default Admin

- Email: `admin@nacos.edu`
- Password: `password123`

## Notes

- The project uses Bootstrap 5, Lucide icons, AOS, Chart.js, PDO, PHPMailer, DOMPDF, and dotenv.
- Certificate and ID-card templates should be placed in the `certificate/` folder and the system will load them dynamically for PDF generation.
