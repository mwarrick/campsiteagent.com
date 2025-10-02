# Product Requirements Document (PRD) — Campsite Agent V1

## Document Info
- Version: 0.1 (Draft)
- Date: September 2025
- Owner: Product/Eng (TBD)
- Status: Draft

## 1. Summary
The Campsite Agent V1 enables automated monitoring of California State Park campsite availability with a focus on weekend stays (Friday and Saturday nights). Users receive timely email alerts before the 8AM PST booking window and can view results and manage preferences via a web dashboard.

## 2. Goals & Non-Goals
### 2.1 Goals
- Detect weekend availability (Friday AND Saturday) at San Onofre State Beach
- Run a daily automated check by 6AM PST; send alerts by 7:30AM PST
- Provide passwordless email authentication and role-based access (admin/user)
- Offer a basic web dashboard to view availability and manage preferences
- Support on-demand checks initiated by users

### 2.2 Non-Goals (V1)
- SMS/push notifications
- Monitoring multiple parks beyond San Onofre
- Advanced filters (site type, hookups) beyond basic site details
- Historical analytics and pricing alerts

## 3. Users & Use Cases
### 3.1 Users
- Admin users: manage parks and system settings
- Public users: register to receive weekend availability alerts

### 3.2 Primary Use Cases
- User registers and verifies email; logs in via passwordless link
- System scrapes ReserveCalifornia.com daily for San Onofre weekend availability
- User receives email alert by 7:30AM PST when weekend availability is detected
- User views dashboard and toggles monitoring on/off
- Admin manages parks (CRUD limited to San Onofre for V1 setup)

## 4. Requirements
### 4.1 Functional Requirements
1. Authentication
   - Email registration with first/last name and email
   - Email verification required before access
   - Passwordless login via emailed magic link
   - Roles: admin, user
2. Monitoring & Scraping
   - Target: ReserveCalifornia.com, San Onofre State Beach
   - Check 6-month date range; detect Friday + Saturday availability
   - Daily automated job (6AM PST) and on-demand trigger
   - Store site number, site type, and all publicly available details
3. Notifications
   - Email alerts for weekend availability by 7:30AM PST
   - Transport: Gmail SMTP using Google API key (app password/API-based SMTP)
   - Email deliverability and retry strategy
4. Dashboard
   - View latest availability findings and historical runs
   - User preference: monitoring on/off
   - Admin-only: basic park config
5. Data
   - Persist all scraped data and notification events in MySQL (`campsitechecker`)

### 4.2 Non-Functional Requirements
- Deployed on Ubuntu server at 192.168.0.205; PHP 7.4; MySQL
- Respect target site terms and avoid rate limiting
- Basic observability: logs for scraping, email send outcomes
- Reliability: daily job must complete before 7:30AM PST
- Security: store Google SMTP API key securely (env vars, restricted perms); no keys in repo
- Compliance: adhere to Gmail sending policies and daily quotas

## 5. Success Metrics
- Scraper completes daily without rate limiting
- Weekend detection accuracy ≥ 95%
- Email delivery within 30 minutes of detection
- ≥ 99% dashboard uptime during morning window

## 6. System Architecture (V1)
- Backend: PHP 7.4 application
- Database: MySQL (`campsitechecker`)
- Scheduled job: cron at 6:00 AM PST
- Email transport: Gmail API (`messages.send`) via OAuth2 (scope: gmail.send)
- Deployment path: `/var/www/campsiteagent.com/www`

## 7. Data Model (Initial)
- users: id, first_name, last_name, email, role, verified_at, created_at
- login_tokens: id, user_id, token, expires_at, used_at
- parks: id, name, external_id, active
- availability_runs: id, park_id, started_at, finished_at, status, error
- sites: id, park_id, site_number, site_type, attributes_json
- site_availability: id, site_id, date, is_available
- notifications: id, user_id, run_id, sent_at, status, metadata_json

## 8. User Flows
1. Registration → Email verification → Login via magic link
2. Daily job → Scrape → Detect Friday+Saturday → Persist → Send emails → Dashboard shows results
3. On-demand check → Same flow with user initiation

## 9. UX Requirements (V1)
- Simple dashboard with: last run status/time, list of available weekend sites (site number, type, dates), user toggle for monitoring
- Admin view: park configuration (San Onofre preconfigured)

## 10. API Endpoints (V1 outline)
- POST `/api/register` — create user and send verification email
- POST `/api/login` — request magic link
- GET `/api/auth/callback` — verify token, establish session
- GET `/api/availability/latest` — list latest weekend availability
- POST `/api/check-now` — trigger on-demand check (authorized)
- GET `/api/admin/parks` — list parks (admin)
- POST `/api/admin/parks` — create/update park config (admin)

## 11. Constraints & Risks
- Site structure changes; anti-bot defenses
- Email deliverability issues; Gmail SMTP quotas and rate limits
- Strict morning timing windows

## 12. Milestones
- M1: Auth (registration, verification, passwordless login)
- M2: Scraper (San Onofre, weekend detection, storage)
- M3: Notifications (email by 7:30AM PST)
- M4: Dashboard (read-only + preferences)
- M5: Deployment to Ubuntu server

## 13. Open Questions
- ReserveCalifornia scraping approach and legal review?
- Rate limit strategy and backoff parameters?

## 14. Configuration
- Environment variables (server):
  - `SMTP_HOST` = `smtp.gmail.com`
  - `SMTP_PORT` = `587` (TLS) or `465` (SSL)
  - `SMTP_USERNAME` = Gmail account (service/sender)
  - `SMTP_PASSWORD` = Google API key/app password
  - `SMTP_ENCRYPTION` = `tls` or `ssl`
  - `MAIL_FROM` = From email address
  - `MAIL_FROM_NAME` = From name (e.g., "Campsite Agent")

---
Document Status: Draft — derived from discovery; update as decisions are made.


