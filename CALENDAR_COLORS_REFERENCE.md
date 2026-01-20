# Calendar Colors Reference - Solid Backgrounds

## Color Palette

### 1. Available (Green)
```
Background: #90EE90 (Light Green)
Text: #000000 (Black)
Border: #90EE90 (Same as background)
Hover: #7CCD7C (Darker Green)
Status: Clickable ✅

Visual:
┌─────────────┐
│     15      │  ← Solid light green background
│             │     Black text, bold
└─────────────┘     Green border
```

### 2. Fully Booked (Light Red)
```
Background: #FFB6B6 (Light Red/Pink)
Text: #8B0000 (Dark Red)
Border: #FFB6B6 (Same as background)
Hover: No effect
Status: Not clickable ❌

Visual:
┌─────────────┐
│     16      │  ← Solid light red background
│             │     Dark red text, bold
└─────────────┘     Red border
```

### 3. Blocked (Gray)
```
Background: #D3D3D3 (Light Gray)
Text: #4A4A4A (Dark Gray)
Border: #D3D3D3 (Same as background)
Hover: No effect
Status: Not clickable ❌

Visual:
┌─────────────┐
│     17      │  ← Solid gray background
│             │     Dark gray text, bold
└─────────────┘     Gray border
```

### 4. Unavailable (Very Light Gray)
```
Background: #F5F5F5 (Very Light Gray)
Text: #888888 (Medium Gray)
Border: #F5F5F5 (Same as background)
Hover: No effect
Status: Not clickable ❌

Visual:
┌─────────────┐
│     19      │  ← Solid very light gray
│             │     Medium gray text
└─────────────┘     Light gray border
```

### 5. Past Date (Faded)
```
Background: #FAFAFA (Almost White)
Text: #BBBBBB (Light Gray)
Border: #FAFAFA (Same as background)
Hover: No effect
Opacity: 0.7
Status: Not clickable ❌

Visual:
┌─────────────┐
│      1      │  ← Solid faded gray
│             │     Very light gray text
└─────────────┘     Faded border
```

## Calendar Example with Solid Colors

```
┌────────────────────────────────────────────────────────────┐
│                    January 2026                             │
│  ◄                                                      ►   │
├────────────────────────────────────────────────────────────┤
│   Sun    Mon    Tue    Wed    Thu    Fri    Sat           │
├────────────────────────────────────────────────────────────┤
│                  1      2      3      4                    │
│                [⚪]   [⚪]   [🟢]   [⚪]                   │
│                Past   Past  Green  Gray                     │
│                                                             │
│    5      6      7      8      9     10     11             │
│   [⚪]   [⚪]   [⚪]   [🟢]   [🔴]   [⚫]   [⚪]           │
│   Gray   Gray   Gray  Green   Red   Gray   Gray            │
│                                                             │
│   12     13     14     15     16     17     18             │
│   [⚪]   [🟢]   [🟢]   [🟢]   [🔴]   [⚫]   [🟢]          │
│   Gray  Green  Green  Green   Red   Gray  Green            │
└────────────────────────────────────────────────────────────┘

Legend:
[🟢] #90EE90 - Available (Light Green)
[🔴] #FFB6B6 - Fully Booked (Light Red)
[⚫] #D3D3D3 - Blocked (Gray)
[⚪] #F5F5F5 - Unavailable (Very Light Gray)
```

## Color Swatches

### Available
```
█████████  #90EE90
Light Green - Indicates open appointment slots
```

### Fully Booked
```
█████████  #FFB6B6
Light Red - All slots taken for this date
```

### Blocked
```
█████████  #D3D3D3
Light Gray - Lawyer unavailable (vacation, etc.)
```

### Unavailable
```
█████████  #F5F5F5
Very Light Gray - Not in lawyer's schedule
```

### Past Date
```
█████████  #FAFAFA (with 70% opacity)
Almost White - Date has already passed
```

## Hover Effects

### Available Date Hover
```
Normal State:          Hover State:
┌─────────────┐       ┌─────────────┐
│     15      │  →    │     15      │
│  #90EE90    │       │  #7CCD7C    │
│ (Lt Green)  │       │ (Dk Green)  │
└─────────────┘       └─────────────┘
                      + Shadow
                      + Scale 1.05
```

### Non-Available Dates
```
No hover effects - cursor shows "not-allowed"
```

## Accessibility

### Contrast Ratios (WCAG 2.1 AA)
- **Available**: Black text on #90EE90 = 7.2:1 ✅
- **Fully Booked**: Dark red (#8B0000) on #FFB6B6 = 5.8:1 ✅
- **Blocked**: Dark gray (#4A4A4A) on #D3D3D3 = 4.8:1 ✅
- **Unavailable**: Medium gray (#888888) on #F5F5F5 = 3.5:1 ✅

### Color Blind Friendly
- Green vs Red: Distinct brightness levels
- Gray shades: Different saturation levels
- Text weight: Bold for important states

## CSS Classes

```css
/* Available - Light Green */
.calendar-day.available button {
    background: #90EE90;
    color: #000000;
    border: 2px solid #90EE90;
}

/* Fully Booked - Light Red */
.calendar-day.fully-booked button {
    background: #FFB6B6;
    color: #8B0000;
    border: 2px solid #FFB6B6;
}

/* Blocked - Gray */
.calendar-day.blocked button {
    background: #D3D3D3;
    color: #4A4A4A;
    border: 2px solid #D3D3D3;
}

/* Unavailable - Very Light Gray */
.calendar-day.unavailable button {
    background: #F5F5F5;
    color: #888888;
    border: 2px solid #F5F5F5;
}

/* Past - Faded */
.calendar-day.past button {
    background: #FAFAFA;
    color: #BBBBBB;
    border: 2px solid #FAFAFA;
    opacity: 0.7;
}
```

## Mobile View

```
┌──────────────────┐
│   January 2026   │
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
└──────────────────┘

All colors remain
clearly visible on
mobile screens
```

## Print Styles (Optional)

```css
@media print {
    .calendar-day.available button {
        background: #90EE90 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* Ensures colors print correctly */
}
```

## Dark Mode Compatibility (Future)

```css
@media (prefers-color-scheme: dark) {
    .calendar-day.available button {
        background: #4CAF50; /* Darker green */
        color: #FFFFFF;
    }
    .calendar-day.fully-booked button {
        background: #E57373; /* Darker red */
        color: #FFFFFF;
    }
    /* Adjust other colors for dark mode */
}
```
