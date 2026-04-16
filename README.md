# Crane Dashboard (BML IOT)

A comprehensive, real-time industrial VFD (Variable Frequency Drive) monitoring dashboard tailored for Bajaj Mukand cranes. This system acts as the central interface for viewing live operational metrics, health statuses, and historical reports for hoist, trolley, and gantry mechanisms.

## Overview
This platform securely consumes live telemetry data pushed directly via HTTP POST requests from edge Node-RED gateways, parses the data into a ProFreeHost MySQL database, and serves an aesthetic, high-performance UI modeled using the "Kinetic Architect" industrial design system.

### Key Features
- **Real-Time Drive Monitoring**: Live status of Output Frequency, Motor Current, Torque, Mains/Motor Voltages, Fault Codes, and Logic I/O across all VFDs (Main Hoist, Cross Travel, Long Travel, Aux Hoist).
- **Dynamic Power Calculation**: Accurately computes live power consumption dynamically using native 3-phase equations `P(kW) = (V * I * 1.732) / 1000`.
- **Node-RED Integration**: Features an unauthenticated API ingestion endpoint (`/api/receive_data.php`) tightly integrated with bot-bypass workflows (`bypass_aes_flow.json`) to guarantee smooth telemetry delivery across restricted cloud hosts (like iFastNet/ProFreeHost).
- **Modern Industrial UI**: Implements high-contrast typography, structural glassmorphism, and neon-glowing status indicators with optimal UX readability.
- **Reporting & History**: Fully queryable database UI to pull historical shift metrics and track machine load behavior.

## Project Structure
- `index.php / login.php`: Entry and Auth handlers. 
- `dashboard.php`: Main top-level operations view and aggregated total power footprint.
- `drives_live.php`: Deep-dive component analytics utilizing the Stitch 2-column parameter grids.
- `api/`: Ingestion points (`receive_data.php`) and data retrieval APIs.
- `js/ & css/`: Frontend assets and styling.
- `db/config.php`: Central connection broker parsing dynamic environment variables.

## Deployment Setup

1. **Environment Variables:**
   Copy `.env.example` (or create a new `.env` file) inside your root directory. Make sure to define:
   ```env
   DB_HOST=sql100.ezyro.com
   DB_NAME=your_db_name
   DB_USER=your_db_username
   DB_PASS=your_db_password
   ```

2. **Web Server:**
   Deploy the directory to any standard PHP Apache/Nginx web server. The project requires no backend framework, just standard PHP 7.4+ and PDO extensions.

3. **Database Architecture:**
   Use the SQL file included in the `db/` folder to scaffold the `vfd_data` schema that matches the backend expectations.

4. **Node-RED Config:**
   Use the included `bypass_aes_flow.json` in your gateway's Node-RED environment. Place it immediately after your main POST request to intercept and solve Cloudflare/AES challenges if hosting on a ProFreeHost platform.

## Disclaimer
Note: The user interfaces and data endpoints have been deliberately configured without restrictive login wrappers (`requireLogin()` bypassed where requested) to facilitate headless machine-to-machine interactions. Ensure your `.env` variables and `settings.php` are properly obfuscated away from public access.
