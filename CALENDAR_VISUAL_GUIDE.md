# Calendar Visual Guide - Date Status Colors

## Calendar Display Example

```
┌────────────────────────────────────────────────────────────┐
│                    January 2026                             │
│  ◄                                                      ►   │
├────────────────────────────────────────────────────────────┤
│   Sun    Mon    Tue    Wed    Thu    Fri    Sat           │
├────────────────────────────────────────────────────────────┤
│                  1      2      3      4                    │
│                 ⚪     ⚪     🟢     ⚪                    │
│                Past   Past  Avail  Unavail                 │
│                                                             │
│    5      6      7      8      9     10     11             │
│   ⚪     ⚪     ⚪     🟢     🔴     ⚫     ⚪             │
│  Unavail Unavail Unavail Avail  Full  Block Unavail        │
│                                                             │
│   12     13     14     15     16     17     18             │
│   ⚪     🟢     🟢     🟢     🔴     ⚫     🟢            │
│  Unavail Avail  Avail  Avail  Full  Block  Avail          │
│                                                             │
│   19     20     21     22     23     24     25             │
│   ⚪     🟢     🟢     🟢     🔴     ⚪     🟢            │
│  Unavail Avail  Avail  Avail  Full Unavail Avail          │
└────────────────────────────────────────────────────────────┘

Selected date: January 14, 2026

Legend:
🟢 Available    🔴 Fully Booked    ⚫ Blocked    ⚪ Past/Unavailable
```

## Color Specifications

### 1. Available Dates (🟢)
```
Visual: Cream/Beige with gold border
Background: Linear gradient
  - Start: #faf7f0 (light cream)
  - End: #f5f1e8 (slightly darker cream)
Border: 2px solid rgba(197, 162, 83, 0.4) (gold/tan)
Text: #000000 (black)
Hover: Gold gradient (#C5A253 to #d4b36a)
Cursor: pointer
Opacity: 1.0

Example:
┌─────────────┐
│     15      │  ← Cream background
│             │     Gold border
└─────────────┘     Black text
```

### 2. Fully Booked Dates (🔴)
```
Visual: Light red/pink
Background: Linear gradient
  - Start: #ffe5e5 (light pink)
  - End: #ffd5d5 (slightly darker pink)
Border: 2px solid rgba(204, 0, 0, 0.3) (light red)
Text: #cc0000 (red)
Hover: No effect
Cursor: not-allowed
Opacity: 0.7

Example:
┌─────────────┐
│     16      │  ← Light red background
│             │     Red border
└─────────────┘     Red text
```

### 3. Blocked Dates (⚫)
```
Visual: Gray
Background: Linear gradient
  - Start: #e0e0e0 (light gray)
  - End: #d0d0d0 (medium gray)
Border: 2px solid #b0b0b0 (dark gray)
Text: #666666 (dark gray)
Hover: No effect
Cursor: not-allowed
Opacity: 0.6

Example:
┌─────────────┐
│     17      │  ← Gray background
│             │     Dark gray border
└─────────────┘     Gray text
```

### 4. Unavailable Dates (⚪)
```
Visual: Very light gray
Background: Linear gradient
  - Start: #f5f5f5 (very light gray)
  - End: #e8e8e8 (light gray)
Border: 2px solid #d0d0d0 (medium gray)
Text: #666666 (dark gray)
Hover: No effect
Cursor: not-allowed
Opacity: 0.6

Example:
┌─────────────┐
│     19      │  ← Very light gray
│             │     Medium gray border
└─────────────┘     Gray text
```

### 5. Past Dates (⚪)
```
Visual: Faded gray
Background: Linear gradient
  - Start: #fafafa (almost white)
  - End: #f0f0f0 (very light gray)
Border: 2px solid #e0e0e0 (light gray)
Text: #999999 (light gray)
Hover: No effect
Cursor: not-allowed
Opacity: 0.5

Example:
┌─────────────┐
│      1      │  ← Faded gray
│             │     Light gray border
└─────────────┘     Very light text
```

## Interactive States

### Available Date - Hover Effect
```
Before Hover:          After Hover:
┌─────────────┐       ┌─────────────┐
│     15      │  →    │     15      │
│   (cream)   │       │   (gold)    │
└─────────────┘       └─────────────┘
                      + Shadow effect
                      + Slight scale up
```

### Non-Available Dates - No Hover
```
Fully Booked:         Blocked:           Unavailable:
┌─────────────┐      ┌─────────────┐    ┌─────────────┐
│     16      │      │     17      │    │     19      │
│  (no hover) │      │  (no hover) │    │  (no hover) │
└─────────────┘      └─────────────┘    └─────────────┘
```

## Legend Display

```
┌──────────────────────────────────────────────────────────┐
│                     Calendar Legend                       │
├──────────────────────────────────────────────────────────┤
│  [🟢] Available    [🔴] Fully Booked                     │
│  [⚫] Blocked      [⚪] Past Date                         │
└──────────────────────────────────────────────────────────┘

Each legend item:
┌────────────────┐
│ [■] Status     │  ← Color box + Label
└────────────────┘
```

## Real-World Example

### Lawyer: Atty. John Smith
### Practice Area: Criminal Defense
### Viewing: January 2026

```
Week 1 (Jan 1-4):
- Jan 1-2: Past dates (already happened)
- Jan 3: Available (Friday, 5 slots open)
- Jan 4: Unavailable (Saturday, not working)

Week 2 (Jan 5-11):
- Jan 5: Unavailable (Sunday, not working)
- Jan 6-7: Unavailable (Monday-Tuesday, not in schedule)
- Jan 8: Available (Wednesday, 3 slots open)
- Jan 9: Fully Booked (Thursday, 5/5 slots taken)
- Jan 10: Blocked (Friday, vacation)
- Jan 11: Unavailable (Saturday, not working)

Week 3 (Jan 12-18):
- Jan 12: Unavailable (Sunday, not working)
- Jan 13-15: Available (Mon-Wed, slots open)
- Jan 16: Fully Booked (Thursday, 5/5 slots taken)
- Jan 17: Blocked (Friday, court appearance)
- Jan 18: Available (Saturday, special hours)
```

## Mobile View

```
┌──────────────────┐
│   January 2026   │
│   ◄          ►   │
├──────────────────┤
│ S  M  T  W  T  F │
│                  │
│    1  2  3  4    │
│ ⚪ ⚪ 🟢 ⚪       │
│                  │
│ 5  6  7  8  9 10 │
│ ⚪ ⚪ ⚪ 🟢 🔴 ⚫  │
│                  │
│ Legend:          │
│ 🟢 Available     │
│ 🔴 Fully Booked  │
│ ⚫ Blocked        │
│ ⚪ Past/Unavail  │
└──────────────────┘
```

## Accessibility Notes

### Color Blind Friendly
- **Available**: Distinct cream/beige (not pure green)
- **Fully Booked**: Light red/pink (not pure red)
- **Blocked**: Gray (neutral)
- **Unavailable**: Light gray (neutral)

### Pattern Recognition
- Available dates have gold borders
- Fully booked dates have red text
- Blocked dates have darker gray
- All non-clickable dates have `cursor: not-allowed`

### Screen Reader Support
```html
<button 
  data-date="2026-01-15" 
  aria-label="January 15, 2026 - Available, 3 slots remaining"
>
  15
</button>

<button 
  data-date="2026-01-16" 
  disabled
  aria-label="January 16, 2026 - Fully booked"
>
  16
</button>

<button 
  data-date="2026-01-17" 
  disabled
  aria-label="January 17, 2026 - Blocked: Vacation"
>
  17
</button>
```

## CSS Class Mapping

```css
/* Date Status Classes */
.calendar-day.available      → 🟢 Cream/Gold
.calendar-day.fully-booked   → 🔴 Light Red
.calendar-day.blocked        → ⚫ Gray
.calendar-day.unavailable    → ⚪ Light Gray
.calendar-day.past           → ⚪ Faded Gray

/* Legend Classes */
.legend-color.available      → Cream gradient
.legend-color.fully-booked   → Light red gradient
.legend-color.blocked        → Gray gradient
.legend-color.unavailable    → Light gray gradient
.legend-color.past           → Faded gradient
```

## Animation Effects

### Available Date Hover
```
Transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1)
Effects:
- Background: Cream → Gold gradient
- Border: Tan → Gold
- Shadow: None → 0 4px 12px rgba(197, 162, 83, 0.5)
- Transform: scale(1) → scale(1.05)
```

### Non-Available Dates
```
No animations or transitions
Static appearance to indicate non-interactivity
```
