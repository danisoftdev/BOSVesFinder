# BOSVesFinder — Vessel Tracking & Fleet Intelligence Platform

**Professional Project Overview | Version 1.0 | Benmarine Offshore Services | Confidential**

---

## Application stack (technical)

BOSVesFinder is **only** a **PHP 8.1+** app (**Slim 4**) and **MySQL**. There is **no WordPress** in local or production paths.

- **Web UI:** session login, fleet map and captain pages under `/app/*`. The **captain** page (`/app/captain`) includes GPS tracking, SOS, and **fuel entries** for the active trip (same API as ops: `POST /api/v1/trips/{id}/fuel-logs`).
- **JSON API:** `/api/v1/...` (e.g. `GET /api/v1/fleet/live`). Mutating requests require header **`X-BVF-Nonce`** (issued per page in session).
- **Pagination headers (trips list):** `X-BVF-Total`, `X-BVF-TotalPages`.

**Operations console (after login as admin)**

| Path | Purpose |
|------|---------|
| `/app/vessels` | Vessel registry |
| `/app/trips` | Trips list / management |
| `/app/geofences` | Geofence definitions |
| `/app/alerts` | Alert history |
| `/app/users` | App users (roles) |
| `/app/settings` | Key/value store in `bvf_settings` (e.g. `ops_notification_emails` for SOS / ops mail) |
| `/app/crew` | Crew registry (manage fleet); link people to vessels / app users |
| `/app/trips/{id}` | Trip detail: fuel log (CSV export), crew roster, onboard toggles |

**JSON API (session + `X-BVF-Nonce` on mutating methods):** `GET/POST /api/v1/crew`, `PATCH/DELETE /api/v1/crew/{id}`; `GET/POST /api/v1/trips/{id}/crew`, `DELETE /api/v1/trips/{tripId}/crew/{crewId}`, `PATCH /api/v1/trips/{tripId}/crew/{crewId}` (body `onboard_confirmed`); `GET/POST /api/v1/trips/{id}/fuel-logs`.

Fallback for ops email: **`BVF_OPS_EMAILS`** in `.env` (comma-separated) if `ops_notification_emails` is not set in Settings.

**Cron (signal-loss alerts for stale active trips)**

- `composer cron` or `php bin/cron.php` — run on a schedule (e.g. every 5 minutes) in production.

**Existing databases:** if `bvf_settings` is missing, apply `database/migrations/002_bvf_settings.sql`. If crew or trip inserts fail on unknown columns, apply `database/migrations/003_bvf_crew_app_user_id.sql` and/or `database/migrations/004_bvf_trips_upgrade.sql`, or re-run **`php bin/seed.php`**.

On each web request the app runs **`SchemaEnsure`** (adds missing `bvf_trips` / `bvf_crew` columns when the DB user is allowed to `ALTER TABLE`). If pages still error, run **`php bin/doctor.php`** to see which columns/tables are missing (DB user needs `ALTER` + `CREATE` for auto-fix).

**Local (PHP built-in server)**

1. `composer install`
2. `copy .env.example .env` — set `DB_*` (e.g. `vesfinder`, `root`, empty password on XAMPP).
3. Create DB if needed: `.\scripts\apply-local-mysql-setup.ps1` or run `db/local-mysql-setup.sql`.
4. `php bin/seed.php` — schema, users **admin@example.local** / **admin** and **captain@example.local** / **captain**, plus demo vessel/trips/crew (safe to re-run; fills only missing rows). On Windows: `.\scripts\setup-local.ps1 -ApplyDb -Seed` after editing `.env`.
5. `.\scripts\dev-server.ps1` — **http://localhost:8080/** (script binds `localhost` so **localhost** and **127.0.0.1** both work on Windows; older docs used `127.0.0.1` only and broke `http://localhost:8080/`).

**If the browser says “This page isn’t working” / HTTP 500**

- **Use port8080** with the dev script: **http://localhost:8080/** (not `http://localhost/` alone unless something listens on port 80).
- **Use the same host for login and `/app/*`:** if you sign in at `http://127.0.0.1:8080`, use that host for trips too — session cookies are per-host.
- **Document root must be the `public` folder** (the directory that contains `index.php`). Pointing Apache/IIS/XAMPP at the repo root will break routing.
- Quick check: open **http://127.0.0.1:8080/health.php** — you should see plain text `BOSVesFinder health OK`. If that fails, the server is not serving `public/` correctly.
- From the repo root, run **`php bin/smoke-http.php`** — if that prints HTML without errors, PHP and the app bootstrap are fine; fix the web server URL or document root.
- **`php bin/doctor.php`** — confirms MySQL and required columns.

**Local / staging (Docker: PHP + MySQL only)**

1. `composer install` (vendor is bind-mounted into the container).
2. `docker compose up -d --build`
3. `docker compose exec app php bin/seed.php`
4. **http://localhost:8080/** — same seed users (admin + captain). DB is exposed on host port **3307** (root / `vesfinder_root`).

**Production (checklist)**

- Set **`APP_URL`** to the public `https://` origin (SOS emails and optional REST base resolution).
- Session cookies use **`Secure`** when `APP_URL` is `https://`, when **`SESSION_COOKIE_SECURE=1`**, or when the request is HTTPS (including **`TRUST_X_FORWARDED_PROTO=1`** with `X-Forwarded-Proto: https` from your edge proxy). Optional **`SESSION_COOKIE_SAMESITE`** (default `Lax`).
- Nginx + PHP-FPM: see **`docker/nginx-site.example.conf`** — adjust **`fastcgi_pass`** to your PHP-FPM socket or `127.0.0.1:9000`.

---

## Executive Summary

BOSVesFinder is a next-generation, enterprise-grade vessel tracking and fleet intelligence platform developed exclusively for Benmarine Offshore Services. The system delivers real-time vessel monitoring, comprehensive operational analytics, and centralized fleet control — powered entirely by captains' smartphones, eliminating the need for costly hardware tracking devices.

Built in Cursor as a **standalone PHP** application with a JSON REST API, BOSVesFinder combines purpose-built GPS tracking logic with session-based authentication. The result is a modern, scalable, and cost-effective maritime management solution designed to meet the real operational demands of offshore fleet management.

| | |
|---|---|
| Platform | BOSVesFinder — Vessel Tracking & Fleet Intelligence |
| Client | Benmarine Offshore Services |
| Dev Environment | Cursor (AI-Assisted IDE) |
| Deployment | PHP 8.1+ (Slim 4) + MySQL |
| Tracking Method | Mobile GPS via Captains' Smartphones |
| Database | MySQL (app tables `bvf_*` + `bvf_app_users`) |
| Version | 1.0.0 — Initial Release |
| Classification | Confidential — Internal Use Only |

---

## Project Vision & Strategic Objective

The strategic vision behind BOSVesFinder is to digitally transform how Benmarine Offshore Services manages its offshore fleet — moving from fragmented, manual processes to a unified, intelligent, and data-driven operational platform.

**Mission Statement**

To provide a fully digital, intelligent, and cost-effective vessel management ecosystem that gives Benmarine Offshore Services complete operational visibility, enhanced safety controls, and the analytical insights needed to make faster, smarter decisions — without dependence on expensive external hardware.

**Strategic Pillars**

- Operational Visibility — Real-time GPS position of every vessel, live status monitoring, route history, and a centralized fleet command dashboard
- Operational Intelligence — Fuel efficiency analytics, voyage performance comparisons, and trend reporting across daily, weekly, and monthly periods
- Safety & Compliance — SOS emergency broadcast, geofencing with violation alerts, crew onboard records, and signal loss notifications
- Scalability & Longevity — Architecture built for fleet expansion, modular feature development, and future integration with native mobile apps, AI, and IoT systems

---

## Core System Capabilities

BOSVesFinder is engineered around six core operational modules, each addressing a specific dimension of offshore fleet management.

### 1. Real-Time Tracking Engine

The tracking engine forms the backbone of the platform. Captains activate location services from any GPS-enabled smartphone, transmitting continuous or interval-based position data to the central system. The engine processes this data in real time, rendering live vessel positions on an interactive map.

- Live vessel tracking via smartphone GPS — no hardware required
- Intelligent status detection: Moving, Idle, or Offline
- High-accuracy mapping powered by Google Maps or Leaflet.js
- Interval-based transmission for optimized battery and data usage
- Background tracking support when the browser is minimized

### 2. Distance & Movement Intelligence

Every movement is automatically measured, recorded, and analyzed. The system calculates precise distances, plots route paths, and identifies movement patterns — giving management a complete picture of vessel activity across every voyage.

- Automatic distance calculation in nautical miles and kilometers
- Route path visualization with full trip replay functionality
- Speed estimation and movement pattern analysis
- Trip segmentation: automatic start, pause, and end detection

### 3. Fuel Management System

A structured fuel tracking module replaces paper-based logs with digital records. Captains input fuel loaded before departure; the system tracks consumption throughout the voyage and calculates efficiency metrics against distance covered.

- Captain-entered fuel load before each trip
- Fuel consumption tracking per voyage
- Efficiency analytics: distance per litre and nautical miles per litre
- Historical fuel comparisons per vessel and fleet-wide
- Automated alerts for abnormal or excessive consumption patterns

### 4. Crew & Vessel Operations

Full lifecycle crew management — from registration and role assignment to onboard logging and trip records — gives management complete accountability for who is on which vessel at all times.

- Crew registration with role categorization: Captain, Engineer, Deckhand, and more
- Per-trip crew assignment and onboard confirmation
- Crew logs maintained across all historical voyages
- Vessel profiles with assignment, status, and performance data

### 5. Trip & Voyage Management

Every voyage is a structured, data-rich record. Trips are created with defined origins, destinations, and crew — and are automatically populated with tracking data, fuel records, and captain notes as the voyage progresses.

- Structured trip creation: origin, destination, vessel, captain, and crew
- Automatic population of distance, duration, and fuel data
- Captain notes and incident documentation per trip
- Trip status lifecycle: Pending → Active → Completed
- Automated trip summaries generated at voyage end

### 6. Safety & Alert System

Safety is a primary system concern. BOSVesFinder includes a multi-layered alert and emergency response framework that keeps management informed of potential issues — and gives captains a direct emergency communication channel.

- SOS emergency alert — one-tap broadcast from captain's device
- Configurable geofencing for operational and restricted zones
- Idle-time violation alerts
- Signal loss detection with automatic management notifications
- Device battery level monitoring and low-battery warnings

---

## User Roles & System Access

BOSVesFinder operates on a strict role-based access control model. Each role is purpose-built to match real operational responsibilities within Benmarine Offshore Services.

**System Administrator — Full Platform Control**

- Manage all users, vessels, and system configuration
- Access the complete analytics and reporting suite
- Configure geofences, alert thresholds, and permissions
- Override or escalate any operational action
- View the full audit trail and system event logs

**Operations Manager — Fleet Oversight & Control**

- Monitor all vessels in real time on the live map
- Assign vessels, trips, and crew
- Review performance reports and fuel analytics
- Respond to alerts and coordinate emergency situations
- Generate and export operational reports

**Captain — Mobile Field User**

- Log in via mobile device from the field or vessel
- Start and stop GPS location tracking
- Enter fuel load and confirm onboard crew before departure
- Submit trip notes and voyage observations
- Trigger the SOS emergency alert when required

**Viewer — Read-Only Stakeholder Access**

- View live vessel positions on the fleet map
- Access completed trip and fuel reports
- No editing, assignment, or operational permissions

---

## System Architecture

BOSVesFinder is structured across three architectural layers, each with a distinct responsibility — ensuring clean separation of concerns, long-term maintainability, and horizontal scalability.

**Layer 1 — Development Layer (Cursor)**

- Custom frontend interface with modern, responsive UI/UX
- Backend API services for GPS tracking, fuel computation, and analytics
- Real-time data handling and event-driven processing
- Business logic for trip automation, distance calculation, and alerting

**Layer 2 — Application & API Layer (PHP)**

- Slim front controller (`public/index.php`), Apache/nginx with document root **`public/`**
- JSON API under **`/api/v1`**, session-authenticated browser clients, role checks (viewer / captain / ops / system admin)
- Server-rendered pages for login, fleet map, and captain tracker (Leaflet + bundled JS)

**Layer 3 — Data Layer (MySQL)**

All operational data lives in **`bvf_*`** tables (plus **`bvf_app_users`** for accounts).

- bvf_app_users — Application users and roles (`bvf_viewer`, `bvf_captain`, `bvf_operations_manager`, `bvf_system_administrator`)
- bvf_vessels — Vessel registry
- bvf_trips — Voyage records
- bvf_location_logs — GPS points per trip
- bvf_fuel_logs — Fuel records
- bvf_crew — Crew registry
- bvf_trip_crew — Crew per trip
- bvf_alerts — Alerts and SOS
- bvf_geofences — Geofence definitions

---

## User Interface & Experience Design

The BOSVesFinder interface is designed around two distinct user contexts: a powerful web-based command dashboard for administrators and managers, and a simplified, mobile-first panel for captains operating offshore.

**Admin Command Dashboard**

A full-featured web dashboard giving management complete operational visibility. The interface centers on a large interactive map flanked by summary statistics, active alerts, and sidebar navigation.

- Full-screen interactive fleet map as the central UI element
- Real-time stats: active vessels, open trips, fuel summaries, alert count
- Sidebar navigation: Dashboard · Vessels · Trips · Fuel · Crew · Reports · Settings
- Vessel markers color-coded by status: Green (Moving), Yellow (Idle), Red (Offline)
- Click-to-inspect vessel popups: name, speed, distance, fuel status, crew onboard
- Alerts panel with severity classification and response tracking

**Captain Mobile Interface**

Designed for use at sea — simple, bold, and fast. Large touch targets and minimal distractions ensure captains can operate the system reliably in demanding offshore conditions.

- Oversized action buttons: Start Tracking · Stop Tracking · SOS Emergency
- Simple data entry forms for fuel load and crew onboard confirmation
- Live status indicators: GPS signal strength, network status, battery level
- Last-synced timestamp so captains know their data is transmitting
- Mobile-first responsive design — fully functional on any smartphone browser

**Analytics & Reports Interface**

- Visual charts: Distance vs Fuel, Vessel Utilization, Fleet Performance
- Date range and vessel-specific filters for focused analysis
- One-click export to PDF and CSV formats
- Heatmap visualizations of vessel activity patterns

---

## System Requirements

**Functional Requirements**

- Real-time GPS location updates from mobile devices
- Structured trip lifecycle management
- Fuel and crew data recording per voyage
- Role-based authentication and access control
- Multi-channel alert and notification system
- Report generation and data export

**Non-Functional Requirements**

- High availability with minimal unplanned downtime
- Scalable for multiple concurrent vessel tracking sessions
- Secure data transmission via HTTPS encryption
- Optimized API response times for a real-time user experience
- Full mobile responsiveness across all screen sizes
- Offline data capture with automatic sync on reconnection

**Technical Requirements**

- Server: PHP **8.1+** with `pdo_mysql`, `json`, `session`; MySQL **8+** or MariaDB **10.3+**
- Browser Support: Chrome, Edge, Safari, Firefox — latest two versions
- Captain Device: Any GPS-enabled smartphone with a modern browser
- Connectivity: Active internet connection for real-time tracking
- Map Library: Google Maps API or Leaflet.js
- Security: HTTPS in production, session cookies, **`X-BVF-Nonce`** on API writes, role-based access

---

## Local development

Use the **Application stack** section above: either **`dev-server.ps1`** against a local MySQL, or **`docker compose`** for PHP + MySQL with no host install.

### Production deployment

1. **Runtime:** PHP **8.1+** (`pdo_mysql`, `json`, `session`). Point the vhost document root at **`public/`** (see [`docker/apache-site.conf`](docker/apache-site.conf) for Apache layout). Enable **`AllowOverride All`** if you use **`public/.htaccess`**.
2. **Database:** MySQL **8+** or MariaDB **10.3+**. Run **`php bin/seed.php`** once (or import **`database/schema.sql`**) and replace seed users with production accounts.
3. **Environment:** **`APP_URL`**, **`DB_*`**, optional **`BVF_OPS_EMAILS`** for SOS mail.
4. **HTTPS** and secure session cookies in production.
5. **Deploy:** Ship code + `composer install --no-dev --optimize-autoloader` on the server; run migrations when you add them.

---

## Development Roadmap

BOSVesFinder will be delivered across four structured phases. Each phase builds on the previous, ensuring a stable and testable foundation before new capabilities are introduced.

**Phase 1 — Foundation**

App bootstrap, roles and permissions, database tables, captain GPS tracking, basic fleet live map

**Phase 2 — Core Operations**

Vessel management, trip management, fuel logs, crew assignment, admin dashboard

**Phase 3 — Safety & Intelligence**

Alerts, geofencing, SOS, reporting engine, PDF and CSV exports

**Phase 4 — Polish & Deploy**

UI refinement, mobile optimization, caching, security hardening, live deployment (PHP + MySQL hosting)

---

## Business Value & Competitive Advantage

BOSVesFinder delivers measurable, strategic value to Benmarine Offshore Services across four dimensions.

**Cost Efficiency** — Eliminates costly hardware GPS trackers, reduces fuel waste through efficiency analytics, and lowers administrative overhead by replacing paper logs with structured digital records.

**Safety & Risk Reduction** — The SOS emergency broadcast protects crew in critical situations. Geofencing prevents unauthorized zone entry. Real-time visibility significantly reduces emergency response time.

**Operational Excellence** — A unified platform replaces fragmented manual processes. Every decision can be backed by data. A complete audit trail supports compliance and accountability at every level.

**Scalability & Future Value** — The architecture supports unlimited vessel expansion. The modular design enables rapid addition of new features. The system is positioned for AI analytics, IoT sensor integration, and a dedicated mobile application.

---

## Closing Statement

BOSVesFinder represents a fundamental advancement in how Benmarine Offshore Services manages its offshore fleet. By converging real-time GPS tracking, intelligent fuel analytics, crew accountability, and voyage management into a single integrated platform — built on modern web technology and deployed as a dedicated PHP application — the system positions Benmarine at the forefront of digital maritime operations.

This is not a tracking tool. It is a fleet intelligence platform — built to make operations safer, leaner, and more strategically informed. BOSVesFinder will set a new operational standard for Benmarine Offshore Services.

**BOSVesFinder · Built for the Sea. Powered by Intelligence.**
