# Mailtrap vs Ethereal Email

Two ways to catch outbound mail before it reaches a real inbox, compared on what they do, what the free tier actually allows, and what it costs to outgrow that.

## At a glance

**Mailtrap** — sandbox + production sending. A persistent team tool: catch test mail in a shared sandbox inbox, then use the same account to actually send production email later.

**Ethereal Email** — disposable sandbox only. A throwaway SMTP catcher: generate a fake account in one request, glance at what your app sent, let it expire. Nothing to configure, nothing to pay for.

## Feature-by-feature

### Core features

| Dimension | Mailtrap | Ethereal Email |
|---|---|---|
| Sandbox inbox | Yes — shared, persistent, team-visible | Yes — single-use, not shared |
| Spam / deliverability score | Yes, per-message analysis | No |
| HTML/CSS client preview | Yes, renders across clients | Basic raw message view only |
| Team sharing | Yes (seat-limited by plan) | No — one account, no accounts model |
| Real-world sending (API/SMTP) | Yes, same product graduates to production | No — sandbox only, mail never leaves it |
| Setup | Account + API key | Zero — generate credentials on the fly |

### Free-tier limits

| Dimension | Mailtrap | Ethereal Email |
|---|---|---|
| Email volume | 50 test emails (sandbox) · 4,000/mo on the free sending plan | Unlimited (ephemeral) |
| Daily cap | 150/day on the free sending plan | None |
| Log / message retention | 3 days | Until the account is pruned (short-lived) |
| Domains / users | 1 domain · 1 user | Not applicable — no persistent accounts |

### Paid tiers & price

| Tier | Mailtrap | Ethereal Email |
|---|---|---|
| Entry paid plan | Basic — from $15/mo | No paid tier exists. No enterprise offering. |
| Mid tier | Business — $85/mo (100,000 emails · 1,000 users · 15-day logs · dedicated IP) | — |
| Top tier | Enterprise — $750/mo (1,500,000 emails · 30-day logs · SSO · dedicated support) | — |

## Which one for the James relay check

- **Fastest sandbox check:** Ethereal. No account to create, no plan to pick — point Nextcloud's SMTP settings at it, trigger a test email, read the caught message in its web viewer, done.
- **If the team wants a shared, standing test inbox** everyone can check without re-pointing SMTP config each time: Mailtrap's free plan covers that, as long as volume stays under 50 sandbox messages or the 4,000/mo-capped-at-150/day sending plan.
- Neither proves a real user's Gmail or company inbox receives the mail — both are sandboxes by design. That still requires temporarily pointing at a real relay (Brevo, SendGrid) as covered separately.

---
*Sources: mailtrap.io pricing, community pricing trackers, nodemailer/ethereal — figures current as of Aug 2026, confirm against provider sites before budgeting.*
