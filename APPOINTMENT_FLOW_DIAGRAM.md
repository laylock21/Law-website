# Appointment Booking Flow - Visual Guide

## New Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    STEP 1: Personal Info                     │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │ Full Name  │  │   Email    │  │   Phone    │            │
│  └────────────┘  └────────────┘  └────────────┘            │
│  ┌──────────────────────────────────────────────┐           │
│  │         Case Description                      │           │
│  └──────────────────────────────────────────────┘           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              STEP 2: Lawyer & Date Selection                 │
│                                                               │
│  ① SELECT PRACTICE AREA (First!)                            │
│  ┌──────────────────────────────────────────────┐           │
│  │ [Click to select practice area]         🔍  │           │
│  └──────────────────────────────────────────────┘           │
│                            ↓                                  │
│         Opens Modal with Practice Areas:                     │
│         • Criminal Defense                                   │
│         • Family Law                                         │
│         • Corporate Law                                      │
│         • Real Estate                                        │
│         • Health Care Law                                    │
│         • Educational Law                                    │
│                            ↓                                  │
│  ② LAWYER DROPDOWN (Auto-filtered)                          │
│  ┌──────────────────────────────────────────────┐           │
│  │ Select a lawyer ▼                            │           │
│  │ • Atty. John Smith                           │           │
│  │ • Atty. Jane Doe                             │           │
│  │ (Only lawyers with selected practice area)   │           │
│  └──────────────────────────────────────────────┘           │
│                            ↓                                  │
│  ③ CALENDAR (Auto-filtered by practice area)                │
│  ┌──────────────────────────────────────────────┐           │
│  │        January 2026                          │           │
│  │  S  M  T  W  T  F  S                         │           │
│  │           1  2  3  4                         │           │
│  │  5  6  7  8  9 10 11                         │           │
│  │ 12 13 14 15 16 17 18                         │           │
│  │                                               │           │
│  │ 🟢 Available  ⚪ Unavailable  ⚫ Past        │           │
│  └──────────────────────────────────────────────┘           │
│                            ↓                                  │
│  ④ SELECT DATE → ⑤ SELECT TIME                              │
│  Time Slot Modal Opens:                                      │
│  • 9:00 AM - 10:00 AM                                       │
│  • 10:00 AM - 11:00 AM                                      │
│  • 2:00 PM - 3:00 PM                                        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                 STEP 3: Review & Submit                      │
│  ┌─────────────────────┐  ┌─────────────────────┐          │
│  │ Personal Info       │  │ Consultation Details│          │
│  │ • Name: John Doe    │  │ • Lawyer: Atty. ...│          │
│  │ • Email: ...        │  │ • Practice: ...    │          │
│  │ • Phone: ...        │  │ • Date: Jan 15     │          │
│  └─────────────────────┘  └─────────────────────┘          │
│                                                               │
│              [Confirm & Submit] Button                       │
└─────────────────────────────────────────────────────────────┘
```

## Key Features

### 1. Practice Area First
```
Before: Lawyer → Practice Area → Date
After:  Practice Area → Lawyer → Date ✅
```

### 2. Smart Filtering
```
Practice Area Selected: "Criminal Defense"
    ↓
Lawyers Filtered: Only criminal defense lawyers shown
    ↓
Calendar Filtered: Only dates when criminal defense lawyers available
    ↓
Better User Experience!
```

### 3. Calendar Color Coding
- 🟢 **Green (Available)**: At least one lawyer available on this date
- ⚪ **Gray (Unavailable)**: No lawyers available on this date
- ⚫ **Light Gray (Past)**: Date has already passed

### 4. Flexible Selection
```
Option A: Practice Area → Date → Lawyer
Option B: Practice Area → Lawyer → Date
Both work! System adapts to user preference.
```

## Example User Journey

### Scenario: Client needs a divorce lawyer

1. **Client arrives at booking form**
   - Sees "Click to select practice area" button
   - Clicks button

2. **Practice area modal opens**
   - Client searches or scrolls
   - Finds "Family Law"
   - Clicks "Family Law"

3. **System responds**
   - Fetches all family law lawyers
   - Populates lawyer dropdown with 5 family law attorneys
   - Fetches availability for all 5 lawyers
   - Calendar shows combined availability (any date where at least 1 is available)

4. **Client sees calendar**
   - January 15, 18, 22, 25 are green (available)
   - Other dates are gray (unavailable)
   - Client clicks January 18

5. **Client selects lawyer**
   - Dropdown shows: Atty. Smith, Atty. Jones, Atty. Williams
   - Client selects "Atty. Smith"
   - Calendar updates to show only Atty. Smith's availability

6. **Time slot modal opens**
   - Shows available times for Atty. Smith on January 18
   - Client selects "2:00 PM - 3:00 PM"
   - Clicks "Confirm"

7. **Form proceeds to review**
   - Client reviews all information
   - Submits consultation request
   - Success! Email sent to Atty. Smith

## Mobile Experience

```
┌─────────────────┐
│  Practice Area  │
│  [Select ▼]     │
├─────────────────┤
│  Lawyer         │
│  [Select ▼]     │
├─────────────────┤
│  Calendar       │
│  [Jan 2026]     │
│  S M T W T F S  │
│  . . . . . . .  │
│  🟢 🟢 ⚪ 🟢 ⚪ ⚪ ⚪  │
└─────────────────┘
```

All features work seamlessly on mobile devices with touch-friendly controls.
