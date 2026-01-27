-- SQL Query Fixes for lawyer/availability.php
-- These are the patterns that need to be changed in the PHP code

-- Pattern 1: INSERT INTO lawyer_availability
-- OLD: INSERT INTO lawyer_availability (user_id, schedule_type, ...)
-- NEW: INSERT INTO lawyer_availability (lawyer_id, schedule_type, ...)

-- Pattern 2: SELECT FROM lawyer_availability  
-- OLD: SELECT * FROM lawyer_availability WHERE user_id = ?
-- NEW: SELECT * FROM lawyer_availability WHERE lawyer_id = ?

-- Pattern 3: UPDATE lawyer_availability
-- OLD: UPDATE lawyer_availability SET ... WHERE id = ? AND user_id = ?
-- NEW: UPDATE lawyer_availability SET ... WHERE la_id = ? AND lawyer_id = ?

-- Pattern 4: DELETE FROM lawyer_availability
-- OLD: DELETE FROM lawyer_availability WHERE id = ? AND user_id = ?
-- NEW: DELETE FROM lawyer_availability WHERE la_id = ? AND lawyer_id = ?

-- Pattern 5: is_active column
-- OLD: is_active
-- NEW: la_is_active

-- Note: The 'id' column in lawyer_availability is now 'la_id'
