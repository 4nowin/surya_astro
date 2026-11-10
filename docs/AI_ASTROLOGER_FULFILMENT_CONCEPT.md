# Astro Setu — Future Product Concept: AI Astrologer & Paid Kundli PDF Fulfilment

**Status:** Future Product Direction / Backlog Concept  
**Scope:** Architecture & Fulfilment Note (Not currently active)  
**Baseline:** Post-M9.2 Roadmap Planning

---

## 1. Overview & Context

Because production shared-hosting environments (e.g. Hostinger) lack native system dependencies for modern headless browser engines (Chromium/HarfBuzz) required for complex-script Devanagari (Hindi) shaping, on-demand real-time PDF generation inside the web tier has been discontinued for in-app client downloads.

In-app users requiring Kundli PDF reports are currently guided to consult verified Astro Setu astrologers via the in-app consultation workflow (`AstrologerScreen`).

In a future milestone, Astro Setu plans to introduce a dedicated **AI Astrologer Paid Kundli Report Fulfilment System**.

---

## 2. Target Concept & User Journey

1. **Ordering:**
   - The user requests/purchases an authentic, high-fidelity Kundli report (Basic or Detailed) via the Astro Setu mobile app or website.
   - Order details capture the native profile/birth coordinates, selected language (Hindi or English), and preferred fulfilment method.

2. **Decoupled Generation Engine:**
   - Report generation is decoupled from the resource-constrained shared web server.
   - Astrologer and AI report generation workflows execute on dedicated hardware / local production workers provisioned with the full Chromium/HarfBuzz engine and Noto Sans typography stack.
   - Calculations use the verified Astro Setu Astrology Engine (M9.1/M9.2 calculation baseline).

3. **Multi-Channel Fulfilment Options:**
   - **WhatsApp Delivery:** Automated delivery of the digitally generated PDF directly to the customer's verified WhatsApp phone number.
   - **Email Delivery:** High-resolution PDF report sent as a secure email attachment with verification link.
   - **Printed Physical Kundli:** Professional color-printed, bound Kundli booklet dispatched via postal mail or courier service to the customer's delivery address.

---

## 3. Engineering Boundaries & Non-Goals for Current Milestones

- **No Active Code Changes:** No payment gateways, order queues, background listeners, or fulfilment dispatch code are implemented in current milestones.
- **Asset Preservation:** The existing PDF Blade templates (`resources/views/pdf/`), typography configurations, and chart SVG renderers in Laravel remain preserved for reuse by the future standalone worker.
