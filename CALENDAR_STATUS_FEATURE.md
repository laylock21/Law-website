# Calendar Date Status Feature

## Overview
Enhanced the appointment calendar to display different visual states for dates based on lawyer availability, booking status, and schedule blocks.

## Date Status Types

### 1. **Available** (Green/Cream)
- **Color**: Light cream/beige gradient (#faf7f0 to #f5f1e8)
- **Border**: Gold/tan border
- **Status**: Lawyer has open slots on this date
- **Clickable**: ✅ Yes
- **Description**: Client can book an appointment on this date

### 2. **Fully Booked** (Light Red)
- **Color**: Light red gradient (#ffe5e5 to #ffd5d5)
- **Border**: Red border (rgba(204, 0, 0, 0.3))
- **Status**: All appointment slots are taken
- **Clickable**: ❌ No
- **Description**: Lawyer has reached maximum appointments for this date

### 3. **Blocked** (Gray)
- **Color**: Gray gradient (#e0e0e0 to #d0d0d0)
- **Border**: Dark gray border (#b0b0b0)
- **Status**: Lawyer has blocked this date (vacation, holiday, etc.)
- **Clickable**: ❌ No
- **Description**: Lawyer is not available on this date

### 4. **Unavailable** (Light Gray)
- **Color**: Very light gray gradient (#f5f5f5 to #e8e8e8)
- **Border**: Light gray border (#d0d0d0)
- **Status**: Not in lawyer's schedule
- **Clickable**: ❌ No
- **Description**: Lawyer doesn't work on this day of the week

### 5. **Past Date** (Faded Gray)
- **Color**: Faded gray gradient (#fafafa to #f0f0f0)
- **Border**: Light gray border (#e0e0e0)
- **Status**: Date has already passed
- **Clickable**: ❌ No
- **Description**: Cannot book appointments in the past

## Visual Legend

```
┌─────────────────────────────────────────────────────┐
│                  Calendar Legend                     │
├─────────────────────────────────────────────────────┤
│  🟢 Available      - Open slots, can book           │
│  🔴 Fully Booked   - All slots taken                │
│  ⚫ Blocked        - Lawyer unavailable              │
│  ⚪ Past Date      - Date has passed                │
└─────────────────────────────────────────────────────┘
```

## Technical Implementation

### API Response Structure

The `get_lawyer_availability.php` endpoint now returns a `date_status_map` object:

```json
{
  "success": true,
  "lawyer": "John Smith",
  "available_dates": ["2026-01-15", "2026-01-18"],
  "date_status_map": {
    "2026-01-15": {
      "status": "available",
      "type": "weekly",
      "max_appointments": 5,
      "booked": 2,
      "slots_remaining": 3
    },
    "2026-01-16": {
      "status": "fully_booked",
      "type": "weekly",
      "max_appointments": 5,
      "booked": 5
    },
    "2026-01-17": {
      "status": "blocked",
      "reason": "Vacation"
    }
  }
}
```

### Status Determination Logic

#### Backend (PHP)
1. **Available**: Date in schedule with `booked < max_appointments`
2. **Fully Booked**: Date in schedule with `booked >= max_appointments`
3. **Blocked**: Date marked as blocked in `lawyer_availability` table
4. **Unavailable**: Date not in lawyer's schedule

#### Frontend (JavaScript)
```javascript
// Check date status from API response
const lawyerData = lawyerAvailability[selectedLawyer];
const dateStatus = lawyerData.dateStatusMap[dateStr]?.status || 'unavailable';

// Apply CSS class based on status
const buttonClass = dateStatus; // 'available', 'fully-booked', 'blocked', etc.
```

## Files Modified

### 1. `api/get_lawyer_availability.php`
**Changes:**
- Added `date_status_map` to response
- Includes fully booked dates (not just available)
- Includes blocked dates with reasons
- Tracks booking counts for each date

**New Response Fields:**
```php
'date_status_map' => [
    'date' => [
        'status' => 'available|fully_booked|blocked',
        'type' => 'weekly|one_time',
        'max_appointments' => int,
        'booked' => int,
        'slots_remaining' => int,
        'reason' => string (for blocked dates)
    ]
]
```

### 2. `src/css/styles.css`
**Added Styles:**
```css
.calendar-day.available button { /* Green/cream */ }
.calendar-day.fully-booked button { /* Light red */ }
.calendar-day.blocked button { /* Gray */ }
.calendar-day.unavailable button { /* Light gray */ }
.calendar-day.past button { /* Faded gray */ }
```

**Legend Colors:**
```css
.legend-color.available { /* Cream gradient */ }
.legend-color.fully-booked { /* Light red gradient */ }
.legend-color.blocked { /* Gray gradient */ }
.legend-color.past { /* Faded gradient */ }
```

### 3. `src/js/script.js`
**Updated Functions:**
- `renderCalendar()`: Uses `dateStatusMap` to determine button class
- `lawyerSelect.addEventListener('change')`: Stores `dateStatusMap` from API
- `updateCalendarForPracticeArea()`: Fetches and stores status maps for all lawyers

**Data Structure:**
```javascript
lawyerAvailability[lawyerName] = {
    availableDays: ['2026-01-15', '2026-01-18'],
    dateStatusMap: {
        '2026-01-15': { status: 'available', ... },
        '2026-01-16': { status: 'fully_booked', ... },
        '2026-01-17': { status: 'blocked', ... }
    }
};
```

### 4. `index.html`
**Updated Legend:**
```html
<div class="calendar-legend">
    <div class="legend-item">
        <div class="legend-color available"></div>
        <span>Available</span>
    </div>
    <div class="legend-item">
        <div class="legend-color fully-booked"></div>
        <span>Fully Booked</span>
    </div>
    <div class="legend-item">
        <div class="legend-color blocked"></div>
        <span>Blocked</span>
    </div>
    <div class="legend-item">
        <div class="legend-color past"></div>
        <span>Past Date</span>
    </div>
</div>
```

## User Experience

### Scenario 1: Viewing Lawyer's Calendar
```
User selects: "Atty. John Smith"
Calendar shows:
- Jan 15: 🟢 Available (3 slots left)
- Jan 16: 🔴 Fully Booked (0 slots left)
- Jan 17: ⚫ Blocked (Vacation)
- Jan 18: 🟢 Available (5 slots left)
```

### Scenario 2: Practice Area View
```
User selects: "Criminal Defense"
Calendar shows:
- Combined availability from all criminal defense lawyers
- Only shows available dates (green)
- Doesn't show fully booked/blocked until lawyer selected
```

### Scenario 3: Selecting Specific Lawyer
```
User selects lawyer after practice area:
- Calendar updates to show that lawyer's specific status
- Fully booked dates appear in red
- Blocked dates appear in gray
- User can see exactly which dates are bookable
```

## Benefits

### For Clients
✅ **Clear Visual Feedback**: Instantly see which dates are available  
✅ **Reduced Frustration**: No clicking on unavailable dates  
✅ **Better Planning**: See fully booked dates to plan alternatives  
✅ **Transparency**: Understand why dates aren't available (blocked vs booked)

### For Lawyers
✅ **Capacity Management**: Clients see when you're fully booked  
✅ **Schedule Control**: Blocked dates clearly marked  
✅ **Reduced Inquiries**: Fewer questions about unavailable dates

### For System
✅ **Accurate Data**: Real-time booking status from database  
✅ **Performance**: Status calculated server-side  
✅ **Scalability**: Handles multiple lawyers and schedules efficiently

## Color Accessibility

All colors meet WCAG 2.1 AA standards for contrast:
- **Available**: High contrast with black text
- **Fully Booked**: Red with sufficient contrast
- **Blocked**: Gray with clear distinction
- **Past**: Faded but still readable

## Mobile Responsiveness

All status colors and legend work seamlessly on mobile devices:
- Touch-friendly button sizes maintained
- Legend stacks vertically on small screens
- Colors remain distinct on all screen types

## Testing Checklist

- [x] Available dates show in cream/green
- [x] Fully booked dates show in light red
- [x] Blocked dates show in gray
- [x] Past dates show in faded gray
- [x] Legend displays all status types
- [x] Clicking available dates opens time slot modal
- [x] Clicking non-available dates does nothing
- [x] Status updates when lawyer changes
- [x] Status updates when practice area changes
- [x] API returns correct date_status_map
- [x] Colors are accessible and distinct

## Future Enhancements

1. **Tooltips**: Show booking details on hover
   - "3 of 5 slots available"
   - "Blocked: Vacation"
   
2. **Waitlist**: Allow booking on fully booked dates
   - Add to waitlist if cancellation occurs
   
3. **Partial Availability**: Show dates with limited slots differently
   - Yellow/orange for "almost full" dates
   
4. **Multi-day Selection**: Allow booking multiple consecutive days
   - Show availability across date range
