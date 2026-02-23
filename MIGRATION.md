# MBSales to Laravel CRM – Migration Notes

This project is the Laravel 12 migration of the legacy procedural PHP CRM (mbsales).

## Setup

1. Copy `.env.example` to `.env` and set `APP_KEY` (`php artisan key:generate`).
2. For CRM database: set `DB_CONNECTION=mysql`, `DB_DATABASE=mbsales`, and DB credentials. Or run on SQLite for a fresh install (migrations create all tables).
3. Run `php artisan migrate`.
4. Run `php artisan db:seed` to seed campaigns, forms, disposition codes, form fields, and an admin user (username: `admin`, password: `password` if using factory default).
5. Optional: configure VICIdial and Asterisk in `.env` (see `.env.example`).

**Note:** `users.vici_pass` is stored encrypted (new writes). If migrating from legacy with plaintext `vici_pass`, re-save each user's VICIdial credentials once (e.g. via admin user edit) so they are encrypted, or existing plaintext will continue to work via backward-compatible cast until overwritten.

## Auth

- Login uses **username** and password (not email). The default seeded user has `username=admin`.
- Session stores `campaign` and `campaign_name` after login.
- Roles: `Super Admin`, `Admin`, `Team Leader`, `Agent`. Admin routes use middleware `role:Admin,Super Admin`.

## Routes: Implemented vs Redirect/Stub

| Route | Status | Notes |
|-------|--------|--------|
| `GET /`, `GET /login`, `POST /login`, `POST /logout` | Implemented | Username + campaign login. |
| `GET /dashboard` | Implemented | Campaign-aware stats, form links, layout/sidebar. |
| `GET /forms/{type}`, `POST /forms/submit` | Implemented | Dynamic form from form_fields; FormSubmissionRequest validation. |
| `GET /records` | Implemented | Call history with filters (date, agent). |
| `GET /agent` | Implemented | Agent page; softphone uses API proxy. |
| `GET /api/vicidial/proxy` | Implemented | VICIdial Agent API proxy (HTTP). |
| `GET /api/disposition-codes`, `POST /api/disposition/save`, `GET /api/notifications` | Implemented | Disposition and notifications API. |
| `GET /leads` | **Redirect** | Redirects to dashboard. No leads list or import UI yet. |
| `GET /attendance` | Implemented | Agent-facing attendance view (today’s events). |
| `GET /admin/configuration` | Implemented | Tabs (general, disposition). |
| `GET /admin/attendance` | Implemented | Attendance logs list (admin). |

## Admin screens: implemented vs missing (legacy parity)

- **Implemented:** Configuration (tabs), Attendance logs.
- **Not implemented (legacy has these):** Admin dashboard (`admin.php`), manage_users, manage_campaigns, manage_forms, manage_vicidial_servers, manage_agent_screen, manage_fields, manage_disposition_codes, manage_disposition_records, manage_records. Use DB/seeders or add these CRUD screens as needed.

## Queues

- Lead import: `ImportLeadsCsvJob` (dispatch with file path, campaign, form type, agent).
- Asterisk: `AsteriskOriginateJob` (dispatch with channel, number, callerId).
- Run `php artisan queue:work` to process jobs when using database queue.

## Legacy Parity

- Campaigns and forms are loaded from DB (with config fallback).
- Form submissions write to campaign-specific tables (ezycash, ezyconvert, etc.) and log to `crm_call_history`.
- VICIdial proxy uses the campaign’s configured server and the user’s `vici_user` / `vici_pass` (vici_pass encrypted at rest).
- Disposition codes and attendance logs are supported; admin can view attendance logs; agents can view their own attendance at `/attendance`.
