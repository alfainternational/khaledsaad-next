# Reading and Editorial Completion Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development for independent editorial tasks; the parent integrates the reading layout, content rollout and verification. The owner approved the design and substantive content expansion on 2026-10-30.

**Goal:** Give reading pages a deliberate RTL editorial hierarchy and replace partial published starter content with useful, complete material.

**Architecture:** Keep existing URLs/models/SEO and apply an adaptive article layout: centered reading for short pieces, restrained table of contents only for structured long pieces, integrated source/author trust block, one primary application action. Store editorial revisions in source files and apply them through an idempotent, conflict-aware release seeder rather than resetting production content.

**Tech Stack:** Laravel/Blade, shared CSS, Markdown/front matter, PHPUnit and browser visual checks.

- [ ] Audit source and published content. Identify actual partial starter pieces and distinguish complete material from gaps; inspect source-dependent factual claims. Produce an editorial coverage record.
- [ ] Expand the nine original starter pieces in `database/content/editorial/` with complete topic-specific explanations, worked examples, practical steps, limits and real relevant sources. Preserve slugs and mark hypothetical examples. Review other source-backed content and repair concrete gaps without padding.
- [ ] Add a release seeder using the existing FrontMatter parser and ContentItem relations. Update only known starter bodies or the prior revision hash; preserve IDs/publication/editor revisions, report conflicts, and snapshot replaced text. Wire first install and a separate deployment marker. Tests exercise original content, edited rows, reseeding and source metadata.
- [ ] Reorganize `resources/views/site/articles/show.blade.php`, glossary/about reading variants and scoped CSS. Retain structured data, reporting, related links and data-toc contracts. Compute reading duration from actual content; show a lightweight TOC only when useful.
- [ ] Add focused semantic layout/content tests. Run existing magazine/profile/content suites, build, then browser tests and visual checks for short/long/news/glossary/about in light/dark at 1366/390 widths. Fix failures, review spec and code, then commit/push on the existing delivery branch.

## Concrete verification cases

1. `customer-journey-map` presents a developed explanation and useful journey worksheet rather than its original two paragraphs; its URL and ID are stable.
2. An editor-modified body survives release seeding, repeated seeding creates no duplicates, and old body/metadata remain recoverable.
3. A two-paragraph article has no empty sidebar; a structured long article has working heading anchors and one navigational TOC per viewport.
4. Sources, author identity, FAQ/Article schema and report POST stay available. A single primary application link does not suppress relevant alternatives.
5. Tables scroll inside the reading column, not the page. Mobile and dark mode preserve contrast and reading order.

## Editorial constraints

No invented customer outcomes, author credentials, market averages, dates or source attributions. Timely platform facts must be verified against primary documentation. Complete useful existing articles need no arbitrary length increase. Production writes occur only through the owner-run deployment workflow; live checks are read-only except an anonymous lab trial if needed.
