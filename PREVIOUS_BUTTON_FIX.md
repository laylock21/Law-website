# Previous Button Navigation Fix

## Date: January 21, 2026

## Problem:
When users are on **Step 3 (Review Your Information)** and click the "Previous" button, they were being sent back to **Step 1 (Personal Information)** instead of **Step 2 (Select Date & Lawyer)**.

This was frustrating because users typically want to review or change their date/lawyer selection, not their personal information.

## Solution:
Added special logic to the Previous button handler that skips Step 1 when navigating back from Step 3.

### Navigation Flow:

**Before Fix:**
- Step 3 → Previous → Step 2 → Previous → Step 1 ✓
- Step 3 → Previous → Step 1 ✗ (Wrong!)

**After Fix:**
- Step 3 → Previous → **Step 2** ✓ (Correct!)
- Step 2 → Previous → Step 1 ✓ (Still works)

### Implementation:

**File**: `src/js/script.js`

```javascript
// Previous button click
if (prevBtn) {
    prevBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            // Special logic: From step 3 (Review), go directly to step 2 (Date & Lawyer)
            // Skip step 1 (Personal Information) since users rarely need to edit it
            if (currentStep === 3) {
                showStep(2); // Go directly to "Select Date & Lawyer"
            } else {
                showStep(currentStep - 1); // Normal previous step
            }
            
            // Scroll to top of form
            const container = document.querySelector('.appointment-single-column');
            if (container) {
                container.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }
    });
}
```

### User Experience:

1. **Step 1**: Personal Information
   - Fill out name, email, phone, case description
   - Click "Next" →

2. **Step 2**: Select Date & Lawyer
   - Choose practice area, lawyer, date, time
   - Click "Next" →

3. **Step 3**: Review Your Information
   - Review all details
   - Click "Previous" → **Goes back to Step 2** (Date & Lawyer)
   - Can adjust date/time/lawyer
   - Click "Next" → Back to Step 3

4. **From Step 2**:
   - Click "Previous" → Goes to Step 1 (Personal Information)
   - Normal navigation still works

### Benefits:
- ✅ More intuitive navigation
- ✅ Users can quickly adjust date/lawyer selection
- ✅ Reduces frustration
- ✅ Maintains ability to edit personal info from Step 2

### Files Modified:
- `src/js/script.js` - Updated Previous button logic
- `index.html` - Version updated to v=3.6

### Testing:
1. Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
2. Fill out Step 1 (Personal Information)
3. Click "Next" to Step 2 (Date & Lawyer)
4. Click "Next" to Step 3 (Review)
5. Click "Previous" → Should go to Step 2 ✓
6. Click "Previous" again → Should go to Step 1 ✓
