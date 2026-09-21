## Context

The application is a Laravel 12, Blade, Tailwind CSS 4 CRM with configurable branding and an existing dark-first "Operational Signal Desk" visual language. The root route currently redirects to `/login`; the login and authenticated product already load the shared `app.css` bundle and resolve organization branding through the global view composer.

The product is telephony-first. Confirmed capabilities include campaign-aware lead handling, browser calling through SIP.js/WebRTC, VICIdial/Asterisk integration, dynamic forms, dispositions, callbacks, attendance, reporting, notifications, activity history, and role-based administration. The repository explicitly has no confirmed testimonials, customer-count claims, pricing, or benchmark proof.

## Goals / Non-Goals

**Goals:**

- Give visitors immediate product context before sign-in with one clear primary action.
- Explain the lead-to-call-to-disposition workflow in plain language using only confirmed capabilities.
- Use real product imagery and existing design tokens so the page feels native to the CRM.
- Keep the page fast, responsive, keyboard accessible, and easy to scan from mobile through desktop.

**Non-Goals:**

- No registration, free-trial, pricing, booking, contact-sales, or demo workflow.
- No new backend service, controller, dependency, analytics package, or marketing CMS.
- No invented testimonials, logos, performance metrics, compliance claims, or security guarantees.
- No changes to login, role authorization, telephony, campaign scope, or authenticated application behavior.

## Decisions

1. **Use the root route as the public product entry.** `/` renders the landing view while `/login` remains the authentication destination. This preserves the existing sign-in contract and gives the product a meaningful public front door.

2. **Lead with the product workflow.** The hero states that calls and CRM work live in one workspace, followed by real product proof, a Lead → Call → Capture → Disposition/Callback workflow, telephony integration context, operational visibility, and a final sign-in CTA.

3. **Reuse the live design system.** The landing page consumes the current CSS variables and Blade icon/brand components. Magenta remains the primary action/focus signal; charcoal tonal surfaces do the structural work. The page may use one restrained radial atmosphere in the hero but avoids gradient-heavy decoration, nested card grids, or decorative motion.

4. **Use a code-native workflow preview as proof.** The hero shows a restrained, non-interactive representation of the existing agent workspace: lead context, browser softphone state, SIP.js/WebRTC, transfer/record controls, and disposition follow-up. This keeps the page fast and avoids introducing a new public asset pipeline while staying grounded in implemented product behavior.

5. **Keep behavior mostly HTML/CSS.** Anchor navigation, CTA links, semantic sections, and responsive layout require no client-side application state. This keeps the page quick to load and avoids unnecessary JavaScript.

## Risks / Trade-offs

- A code-native preview can be mistaken for a live control surface → label it as an agent workspace preview, keep it non-interactive, and support it with plain-language capability copy.
- Configured brand names can be long → use the shared brand component in a bounded header area and allow wrapping on narrow screens.
- A public page introduces additional vertical content → keep sections concise, vary layout rhythm, and repeat the sign-in CTA only at high-intent points.
