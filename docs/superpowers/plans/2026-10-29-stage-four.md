# Stage Four Implementation Plan

> Execute with superpowers:subagent-driven-development for the independent curriculum task, followed by specification and code reviews. The approved pages blueprint remains the product specification.

**Goal:** Complete blueprint stage 4: 12 published courses / 72 complete lessons, certificates, theme, resources, information pages, and branded authentication.

**Architecture:** Extend the existing Laravel domains and Blade components. Preserve the current lesson, asset, payment and publication contracts. Add repeatable stage-four seed data and use existing mail queues and project ownership checks.

**Tech Stack:** Laravel 13, PHP 8.3+, Blade, Alpine, Vite, SQLite tests / MySQL production, PHPUnit and Playwright.

- [x] Curriculum: create `database/seeders/CompleteAcademySeeder.php` and per-course source files for eleven courses, six lessons each. Each lesson has substantive concept text, an opening decision, three explained questions, a distinct practical lab, a template, cumulative inputs, what-if rules and real-world follow-up. Preserve the foundation course and existing member records. Add curriculum structure and repeat-seed tests.
- [x] Certificates: inspect existing issuance; require all lessons and at least one recorded real-world result from that course. Add public valid/revoked/missing verification and progress links. Test eligibility, ownership and idempotency.
- [x] Resources: public topic/format filters and full preview pages, Markdown / print-to-PDF / CSV downloads, associated lesson/tool links and authenticated project saving. Test publication and ownership boundaries.
- [x] Theme and identity: shared light/dark variables, early persisted/system theme selection and accessible toggle; branded authentication pages without changing auth contracts. Check both themes at mobile and desktop sizes.
- [x] About/pricing/contact: publish the approved editorial/AI methodology, actual plan comparison and billing links, validated queued contact mail with throttling and configured support address. Add error views and discovery links for public pages.
- [x] Integration: stage-four deployment seed marker, database/browser seed ordering, sitemap, documentation and blueprint decision log. Run PHPUnit, production build, Playwright, and visual review; resolve failures before reporting.

## Verification

Use the local PHP binary that satisfies composer requirements. Run focused tests while developing, then the complete PHP/browser suites once integration is ready. Browser tests use their dedicated SQLite database. No production seed, email, payment or deployment is performed as a side effect of local verification.

Final local evidence (2026-10-29): PHPUnit 158 passed / 5,072 assertions; Playwright 86 passed across desktop and mobile, followed by 16 stage-four checks after layout refinements; Vite production build passed; Pint passed; Blade and route caching passed; bootstrap Bash syntax passed. Visual screenshots reviewed for pricing, authentication, resources, contact, about and certificates in both themes and viewports. Fixed review findings: empty results cannot issue certificates; compatible project fields prefill new labs, including zero budgets; final lessons use the latest saved asset version. The initial reseed-test failure compared Carbon object identity and was corrected to compare timestamp values. Production deployment and SMTP delivery remain live verification steps after the owner runs the bootstrap.

## Scope decisions

The existing approved blueprint and the user's instruction to execute stage four authorize implementation without another design approval. Stage five (moving the main domain) remains separate. Pricing comes from persisted plans. PDF follows the approved browser-print approach already used for Arabic assets. Certificate eligibility follows §4.5 (all lessons plus a real result); access follows any existing entitlement rules rather than inventing a new payment barrier.
