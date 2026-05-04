# Legal Go-Live Checklist
## Bookit Booking System — Wimbledon Smart

> *This checklist is intended for use by Wimbledon Smart (Liron) when
> onboarding each new client. It does not constitute legal advice.
> A qualified UK solicitor should review the Privacy Policy and Terms &
> Conditions before first use with any client.*

---

**Client business name:** ___________________________________
**Website URL:** ___________________________________
**Completed by:** ___________________________________
**Date completed:** ___________________________________

---

## 1. ICO Registration

The client is the data controller for personal data processed through the
Bookit system. ICO registration is the client's legal obligation, not
Wimbledon Smart's. Most UK businesses that process personal data
electronically are required to register and pay the annual data protection
fee.

- [ ] Direct the client to the ICO self-assessment tool to confirm whether
      they are required to register:
      **ico.org.uk/for-organisations/data-protection-fee/self-assessment/**

- [ ] If registration is required, confirm the applicable fee tier:
  - **Micro organisation** — under £632k turnover OR fewer than 10 staff:
    **£52/year** (£47 by Direct Debit)
  - **SME** — under £36m turnover OR under 250 staff:
    **£78/year** (£73 by Direct Debit)
  - *(Fees correct as of February 2025 — check ico.org.uk/fee for current
    amounts)*

- [ ] Client has registered (or confirmed exemption) at:
      **ico.org.uk/fee**

- [ ] ICO registration number obtained from client:
      **ICO number: ___________________________________**

- [ ] ICO registration number added to the Privacy Policy
      (Section 1 — Who We Are)

- [ ] Remind client: ICO registration must be renewed annually.
      The ICO sends a renewal reminder by email.

---

## 2. Data Processing Agreements (DPAs)

The Bookit system uses three third-party processors. A Data Processing
Agreement must be in place with each before the system goes live.
All three DPAs are accepted as part of the standard account setup process —
use the steps below to verify they are in place.

- [ ] **Brevo (transactional email)**
  - DPA is accepted automatically on account creation
  - Verify: log in to **app.brevo.com** → Settings → Legal
  - Confirm DPA status is active and note the account email:
    ___________________________________

- [ ] **Stripe (payment processing)**
  - DPA is accepted automatically via Stripe's Terms of Service
  - Verify: log in to **dashboard.stripe.com** → Settings → Legal
  - Confirm DPA status is active and note the account email:
    ___________________________________

- [ ] **Google (Google Calendar API)** — *only if Calendar sync is enabled*
  - DPA is accepted automatically for Google Cloud services
  - Verify: **console.cloud.google.com** → IAM & Admin → Legal
  - If Calendar sync is not enabled for this client, mark N/A:
    **[ ] N/A — Google Calendar sync not enabled**

- [ ] Document that all applicable DPAs are confirmed and on file for
      this client

---

## 3. Website Legal Pages

### 3.1 Privacy Policy

- [ ] Privacy Policy finalised and reviewed by client
- [ ] Privacy Policy published on the client's website at:
      **/privacy-policy/** (or equivalent URL)
- [ ] Privacy Policy linked in the website footer on every page
- [ ] ICO registration number is present in Section 1 of the policy
- [ ] "Last updated" date is correct
- [ ] Privacy Policy links to the ICO complaints page:
      **ico.org.uk/make-a-complaint**

### 3.2 Terms & Conditions

- [ ] Terms & Conditions finalised and reviewed by client
- [ ] Terms & Conditions published on the client's website at:
      **/terms/** (or equivalent URL)
- [ ] Terms & Conditions linked in the website footer on every page
- [ ] All placeholder fields completed:
  - [ ] [BUSINESS NAME]
  - [ ] [BUSINESS ADDRESS]
  - [ ] [BUSINESS EMAIL]
  - [ ] [WEBSITE URL]
  - [ ] [CANCELLATION WINDOW HOURS] — matches plugin setting
  - [ ] [REFUND POLICY WITHIN WINDOW]
  - [ ] [REFUND POLICY OUTSIDE WINDOW]
  - [ ] [DEPOSIT PERCENTAGE OR AMOUNT] — or clause marked N/A if no deposit
  - [ ] [PACKAGE EXPIRY POLICY] — or clause marked N/A if no packages

### 3.3 Booking wizard integration

- [ ] A link to the Terms & Conditions appears at the booking confirmation
      step, before payment is taken
- [ ] The cooling-off waiver checkbox is present and active in the booking
      wizard (implemented in Sprint 4)
- [ ] The waiver checkbox wording has been reviewed and approved by the
      client
- [ ] A link to the Privacy Policy is accessible from the booking wizard

---

## 4. Plugin Configuration Cross-Check

Confirm that the following plugin settings match the placeholder values
used in the published Terms & Conditions:

| T&Cs placeholder | Plugin setting | Value confirmed |
|---|---|---|
| [CANCELLATION WINDOW HOURS] | Cancellation window | _______________ |
| [REFUND POLICY OUTSIDE WINDOW] | Late cancellation refund | _______________ |
| [DEPOSIT PERCENTAGE OR AMOUNT] | Deposit setting | _______________ |
| [PACKAGE EXPIRY POLICY] | Package expiry | _______________ |

- [ ] All values above match between the plugin and the published T&Cs

---

## 5. Optional but Recommended

- [ ] **Cookie policy** — required if the client's website uses any
      non-essential cookies (e.g. Google Analytics, Facebook Pixel,
      advertising cookies). The Bookit booking system itself uses only
      strictly necessary session cookies, but the wider website may use
      others. Use the ICO cookie guidance at **ico.org.uk/cookies** to assess.

- [ ] **Accessibility statement** — recommended if the client's website
      is subject to public sector accessibility requirements, or as good
      practice. A template is available separately.

- [ ] **Solicitor review** — a qualified UK solicitor has reviewed the
      Privacy Policy and Terms & Conditions before this client's first
      live booking. *(Strongly recommended for the first client launch.)*

---

## 6. Ongoing Obligations

Remind the client of the following ongoing responsibilities after go-live:

- [ ] Client briefed: **ICO registration must be renewed annually.**
      The ICO will send a reminder. Fee is currently £52 (micro) or
      £78 (SME) per year.

- [ ] Client briefed: **Privacy Policy must be reviewed and updated** if:
  - New types of personal data are collected
  - A new third-party processor is added (e.g. SMS provider, analytics tool)
  - Any data processing activity changes materially

- [ ] Client briefed: **Terms & Conditions must be reviewed and updated** if:
  - The cancellation window changes
  - The refund or deposit policy changes
  - Package expiry terms change

- [ ] Client briefed: **Data breach obligations** — if a breach occurs that
      is likely to result in risk to individuals, the client must notify the
      ICO within 72 hours and notify affected customers without undue delay.
      ICO reporting: **ico.org.uk/make-a-complaint**

---

## Sign-Off

| | Name | Date |
|---|---|---|
| Completed by (Wimbledon Smart) | _______________ | _______________ |
| Reviewed by client | _______________ | _______________ |

**Notes / exceptions:**

___________________________________________________________________________

___________________________________________________________________________

___________________________________________________________________________

---

*Checklist version 1.0 — Wimbledon Smart (wimbledonsmart.co.uk)*
*Prepared for use with the Bookit Booking System.*
*Review this checklist whenever a new processor or data category is added
to the Bookit system.*
