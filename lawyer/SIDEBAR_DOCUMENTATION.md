# Expandable Sidebar Documentation

## Overview
The lawyer portal now features a modern, expandable sidebar navigation system that overlays the content when expanded. The sidebar starts collapsed (70px) and expands to 260px when clicked, overlaying the main content with a semi-transparent backdrop.

## Features

### Desktop Experience
- **Overlay Expansion**: Sidebar expands over the content (doesn't push it)
- **Default State**: Starts collapsed (70px icon-only mode)
- **Expanded State**: 260px with full text labels
- **Backdrop Overlay**: Semi-transparent dark overlay when expanded
- **Click Outside to Close**: Clicking the overlay or anywhere outside collapses the sidebar
- **Smooth Animations**: All transitions use cubic-bezier easing for a polished feel
- **Active Page Highlighting**: Current page is highlighted with gold accent color
- **Hover Effects**: Interactive hover states on all navigation items

### Mobile Experience (≤768px)
- **Off-Canvas Menu**: Sidebar slides in from the left when opened
- **Overlay Background**: Semi-transparent backdrop when menu is open
- **Touch-Friendly**: Optimized for mobile interactions
- **Auto-Close**: Taps outside the sidebar automatically close it

## Navigation Items
1. **Dashboard** - Main overview page
2. **Consultations** - View and manage consultation requests
3. **Availability** - Manage schedule and availability
4. **Profile** - Update profile information
5. **Logout** - Sign out (in footer section)

## Technical Details

### Files Modified
- `lawyer/partials/header.php` - Transformed from horizontal nav to sidebar
- `lawyer/styles.css` - Added comprehensive sidebar styles (~300 lines)
- `lawyer/dashboard.php` - Updated to use new header structure

### CSS Classes
- `.lawyer-sidebar` - Main sidebar container
- `.sidebar-header` - Top section with branding and toggle
- `.sidebar-nav` - Navigation links container
- `.sidebar-link` - Individual navigation items
- `.sidebar-footer` - Bottom section (logout)
- `.lawyer-topbar` - Fixed top bar with page title
- `.collapsed` - Modifier for collapsed state
- `.mobile-open` - Modifier for mobile open state

### JavaScript Functionality
```javascript
// Expand sidebar
function expandSidebar() {
    sidebar.classList.remove('collapsed');
    sidebarOverlay.classList.add('active'); // Show overlay
}

// Collapse sidebar
function collapseSidebar() {
    sidebar.classList.add('collapsed');
    sidebarOverlay.classList.remove('active'); // Hide overlay
}

// Toggle on click
sidebarToggle.addEventListener('click', function() {
    if (sidebar.classList.contains('collapsed')) {
        expandSidebar();
    } else {
        collapseSidebar();
    }
});

// Close when clicking overlay
sidebarOverlay.addEventListener('click', function() {
    collapseSidebar();
});
```

## Layout Structure
```
┌──┬──────────────────────────────────────────┐
│  │  Top Bar (Page Title)                    │
│S ├──────────────────────────────────────────┤
│i │                                           │
│d │  Main Content Area (Fixed Width)         │
│e │  Sidebar overlays when expanded          │
│b │                                           │
│a │                                           │
│r │                                           │
└──┴──────────────────────────────────────────┘
   70px (collapsed)

When Expanded:
┌──────────────┬───────────────────────────────┐
│              │░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░│
│  Sidebar     │░░ Overlay (semi-transparent) ░│
│  (260px)     │░░ Main Content (dimmed)      ░│
│  Expanded    │░░                             ░│
│              │░░                             ░│
└──────────────┴───────────────────────────────┘
```

## Color Scheme
- **Sidebar Background**: Navy gradient (#0B1D3A → #1A2B4A)
- **Active Link**: Gold (#C5A253)
- **Hover State**: White overlay (10% opacity)
- **Icons**: 18px, centered alignment

## Responsive Breakpoints
- **Desktop**: Overlay sidebar (70px collapsed, 260px expanded over content)
- **Tablet**: Same as desktop
- **Mobile** (≤768px): Off-canvas sidebar (260px when open)

## Browser Compatibility
- Chrome/Edge: Full support
- Firefox: Full support
- Safari: Full support
- Mobile browsers: Full support with touch optimization

## Future Enhancements
- Add user profile section in sidebar header
- Implement notification badges
- Add keyboard shortcuts (e.g., Ctrl+B to toggle)
- Theme switcher (light/dark mode)
