# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The primary users are call-center agents working campaign-scoped lead queues. Their daily job is to handle leads and calls, use the browser softphone, complete campaign forms, record dispositions, and manage related attendance and callback work.

Secondary users are Team Leaders, Admins, and Super Admins who supervise agents, review operational data, manage campaigns and forms, maintain users and telephony settings, and control the supporting CRM workflows.

## Product Purpose

Laravel CRM is a customer relationship management platform for call-center operations. It keeps campaign login, lead handling, telephony, form capture, disposition management, attendance, reporting, notifications, and administration in one browser-based workspace. Success means agents can complete their call workflow without leaving the CRM while supervisors and administrators retain operational visibility and control.

## Positioning

The product is telephony-first: campaign-aware CRM workflows are directly connected to VICIdial and Asterisk operations, including a browser softphone powered by SIP.js and WebRTC, while real-time updates support active call and supervisor workflows.

## Operating Context

- Agents enter through campaign-aware authentication and work inside an Agent Screen with lead information, dynamic campaign forms, call controls, disposition capture, callbacks, and lead tools.
- Team Leaders and administrators use dashboards, supervisor monitoring, attendance, records, reports, notifications, and activity history to operate and review the call center.
- Super Admins additionally manage users, campaigns, forms, lead-hopper data, VICIdial servers, agent-screen configuration, attendance statuses, branding, retention, and system configuration.
- VICIdial and Asterisk are external telephony systems in the operating environment. Laravel Reverb and Echo provide real-time updates, while queues and long-running workers support operational services.
- Campaign and role boundaries are part of the normal workflow, not optional presentation details.

## Capabilities and Constraints

- Campaign-aware login and session handling.
- Role-based access for Super Admin, Admin, Team Leader, and Agent users.
- Lead queues and hydration, campaign forms, form submissions, dispositions, callbacks, attendance, reporting, notifications, activity logging, and data extraction.
- Browser telephony with SIP.js/WebRTC, VICIdial agent and non-agent APIs, Asterisk AMI integration, predictive dialing, transfers, recording controls, and real-time telephony state.
- The existing Laravel CRM behavior, role names, campaign-scoped data, telephony integrations, and browser-based operating model must remain coherent as the interface evolves.
- The runtime supports configurable branding, so the organization-specific display name, logo, and favicon may differ from the current default product name.

## Brand Commitments

- The current default product name is Laravel CRM.
- Branding is administratively configurable; future work must preserve the configured name, logo, favicon, and their accessible alternatives when present.
- The existing role and workflow terminology—Agent, Team Leader, Admin, Super Admin, Campaign, Lead, Disposition, and Agent Screen—is product language and should remain stable unless explicitly changed.

## Evidence on Hand

- `README.md` documents the product purpose, features, roles, integrations, operating commands, and current screenshot gallery.
- Existing representative screenshots are available at `docs/images/login-screen.png`, `docs/images/dashboard.png`, `docs/images/agent-screen.png`, `docs/images/reports.png`, `docs/images/admin-dashboard.png`, and `docs/images/form-ezycash.png`.
- The running application and source surfaces include campaign-aware authentication, the agent workspace, dashboards, reports, admin tools, dynamic forms, notifications, and telephony endpoints.
- No customer testimonials, benchmark claims, pricing, or other marketing proof were confirmed; future work must not fabricate them.

## Product Principles

- Keep the agent’s lead-to-call-to-disposition workflow in one focused workspace.
- Treat telephony state and CRM data as one operational experience.
- Preserve campaign scope and role-based access at every workflow boundary.
- Give supervisors and administrators timely, auditable operational visibility.
- Prefer clear, dependable workflows over decoration that competes with call-center work.

## Accessibility & Inclusion

The current web shell includes responsive behavior, a skip link, semantic landmarks and labels, visible keyboard focus states, labelled interactive controls, and reduced-motion handling. Future interface work should preserve these commitments. No additional product-specific accessibility needs or required standard were confirmed during init.
