# Admin PHP Files Updated for New Database Schema

## Summary
All admin PHP files have been updated to match the new database schema from `test.sql`. The main changes involve column name mappings to match the prefixed naming convention.

## Column Name Mappings Applied

### consultations table
- `id` → `c_id`
- `full_name` → `c_full_name`
- `email` → `c_email`
- `phone` → `c_phone`
- `status` → `c_status`
- Removed: `practice_area`, `selected_lawyer`, `selected_date` (not in new schema)
- Uses: `lawyer_id` (foreign key to users table)

### users table
- `id` → `user_id`
- Removed: `first_name`, `last_name`, `description`, `profile_picture` (moved to lawyer_profile)

### lawyer_profile table (NEW)
- `lawyer_id` (foreign key to users.user_id)
- `lp_fullname` (replaces first_name + last_name)
- `lp_description` (replaces description)
- `profile` (LONGBLOB for profile picture)

### lawyer_specializations table
- `user_id` → `lawyer_id`
- `practice_area_id` → `pa_id`

### practice_areas table
- `id` → `pa_id`
- `description` → `pa_description`

### lawyer_availability table
- `id` → `la_id`
- `user_id` → `lawyer_id`
- `is_active` → `la_is_active`

### notification_queue table
- `id` → `nq_id`
- `status` → `nq_status`

## Files Updated

### 1. admin/dashboard.php
- Updated consultation status queries to use `c_status`
- Updated consultation display to use `c_full_name`, `c_id`
- Modified practice area distribution query to use lawyer_specializations
- Added lawyer name lookup from lawyer_profile table

### 2. admin/consultations.php
- Updated all consultation queries to use new column names
- Modified search functionality to use `c_full_name`, `c_email`, `c_phone`, `c_status`
- Updated bulk action queries
- Modified table display to show lawyer names from lawyer_profile
- Updated sortable columns list

### 3. admin/view_consultation.php
- Updated consultation detail display to use `c_id`, `c_full_name`, `c_email`, `c_phone`, `c_status`
- Added lawyer name lookup from lawyer_profile
- Removed practice_area and selected_lawyer fields (not in new schema)
- Updated status update queries

### 4. admin/manage_lawyers.php
- Updated lawyer listing query to join with lawyer_profile
- Modified lawyer creation to insert into both users and lawyer_profile tables
- Updated profile picture handling to use LONGBLOB from lawyer_profile
- Changed specialization queries to use `pa_id` and `lawyer_id`
- Updated delete lawyer functionality

### 5. admin/manage_lawyer_schedule.php
- Updated lawyer details query to join with lawyer_profile
- Modified lawyer_availability queries to use `la_id` and `lawyer_id`
- Updated blocked schedule queries
- Changed column references for time_slot_duration and la_is_active

### 6. admin/practice_areas.php
- Updated practice area queries to use `pa_id` and `pa_description`
- Modified lawyer count queries to use lawyer_specializations with `pa_id`
- Updated toggle and delete functionality

### 7. admin/notification_queue.php
- Updated notification queries to use `nq_id` and `nq_status`
- Modified user join to use `user_id`
- Removed first_name/last_name display (not in users table)

### 8. admin/process_emails.php
- Updated notification queue queries to use `nq_status`
- Modified pending notifications query

### 9. admin/manage_sessions.php
- Updated user session queries to use `user_id`
- Modified session display to show username instead of first_name/last_name

## Key Changes

1. **Lawyer Profile Separation**: Lawyer-specific data (name, description, profile picture) is now in the `lawyer_profile` table instead of the `users` table.

2. **Column Prefixing**: Most tables now use prefixed column names (c_, lp_, pa_, la_, nq_) for better organization.

3. **Foreign Key Updates**: All foreign key references updated to use the new column names (e.g., `lawyer_id` instead of `user_id` in lawyer-specific tables).

4. **Removed Fields**: Fields like `practice_area` and `selected_lawyer` in consultations table are no longer used (consultations now use `lawyer_id` foreign key).

5. **Profile Picture Storage**: Changed from file path to LONGBLOB storage in the database.

## Testing Recommendations

1. Test lawyer creation and profile management
2. Verify consultation listing and status updates
3. Check practice area management
4. Test notification queue functionality
5. Verify session management
6. Test lawyer schedule blocking/unblocking

## Notes

- All queries now properly join with `lawyer_profile` when lawyer names are needed
- Profile pictures are now stored as LONGBLOB and displayed using base64 encoding
- The schema maintains referential integrity with proper foreign key constraints
