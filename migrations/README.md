# Database Migrations

This folder contains database migration scripts for the Law Firm system.

## 📋 Migration List

| Version | Name | Date | Status |
|---------|------|------|--------|
| 001 | Add Flexible Scheduling | 2025-10-02 | ⏳ Pending |

---

## 🚀 How to Run a Migration

### Step 1: Backup Your Database

**ALWAYS backup before running migrations!**

```bash
# Windows (Command Prompt)
cd C:\xampp\mysql\bin
mysqldump -u root -p lawfirm_db > C:\xampp\htdocs\TRINIDADV4\migrations\backups\backup_2025-10-02.sql

# Or use phpMyAdmin:
# 1. Open http://localhost/phpmyadmin
# 2. Select 'lawfirm_db' database
# 3. Click 'Export' tab
# 4. Click 'Go' to download backup
```

### Step 2: Run the Migration

**Option A: Using phpMyAdmin (Recommended for beginners)**
1. Open http://localhost/phpmyadmin
2. Select `lawfirm_db` database
3. Click the `SQL` tab
4. Open the migration file (`001_add_flexible_scheduling.sql`)
5. Copy all the SQL code
6. Paste into the SQL query box
7. Click `Go`

**Option B: Using Command Line**
```bash
cd C:\xampp\mysql\bin
mysql -u root -p lawfirm_db < C:\xampp\htdocs\TRINIDADV4\migrations\001_add_flexible_scheduling.sql
```

### Step 3: Verify the Migration

Run the verification queries at the end of the migration file to ensure everything worked correctly.

### Step 4: Test Your Application

1. Open your application
2. Test existing lawyer schedules still work
3. Try creating a new schedule
4. Check that no errors appear

---

## 🔙 How to Rollback (Undo)

If something goes wrong, you can rollback:

**Option A: Using phpMyAdmin**
1. Open http://localhost/phpmyadmin
2. Select `lawfirm_db` database
3. Click the `SQL` tab
4. Open the rollback file (`001_rollback.sql`)
5. Copy and paste the SQL code
6. Click `Go`

**Option B: Using Command Line**
```bash
cd C:\xampp\mysql\bin
mysql -u root -p lawfirm_db < C:\xampp\htdocs\TRINIDADV4\migrations\001_rollback.sql
```

**Option C: Restore from Backup**
```bash
cd C:\xampp\mysql\bin
mysql -u root -p lawfirm_db < C:\xampp\htdocs\TRINIDADV4\migrations\backups\backup_2025-10-02.sql
```

---

## 📊 Migration 001: Flexible Scheduling

### What It Does

Adds support for:
- ✅ **Weekly recurring schedules** (existing functionality preserved)
- ✅ **One-time specific date schedules** (new feature)
- ✅ **Multiple schedules per lawyer** (removes unique constraint)
- ✅ **Soft delete** (deactivate without losing data)

### Database Changes

**New Columns:**
- `schedule_type` - ENUM('weekly', 'one_time')
- `specific_date` - DATE (for one-time schedules)
- `is_active` - BOOLEAN (soft delete flag)

**Removed Constraints:**
- `unique_lawyer_availability` (user_id) - Allows multiple schedules

**New Indexes:**
- `idx_lawyer_schedule` - For efficient schedule queries
- `idx_specific_date` - For date lookups
- `idx_active_schedules` - For active schedule filtering

### Impact

- ✅ **No data loss** - All existing schedules preserved
- ✅ **Backward compatible** - Existing weekly schedules still work
- ✅ **Performance optimized** - New indexes improve query speed
- ✅ **Reversible** - Can rollback if needed

---

## 🛡️ Safety Checklist

Before running any migration:

- [ ] Database backup created
- [ ] Backup file verified (can be opened/restored)
- [ ] Migration script reviewed
- [ ] Running on test/development database first
- [ ] Application is not in heavy use (low traffic time)
- [ ] Rollback script is ready
- [ ] Team members notified (if applicable)

---

## 📝 Next Steps After Migration 001

Once the migration is complete and verified:

1. **Phase 2**: Update API logic (`api/get_lawyer_availability.php`)
2. **Phase 3**: Update lawyer UI (`lawyer/availability.php`)
3. **Phase 4**: Update client calendar (`script.js`)
4. **Phase 5**: End-to-end testing

---

## 🆘 Troubleshooting

### Error: "Column already exists"
**Solution**: The migration was already run. Check if columns exist:
```sql
SHOW COLUMNS FROM lawyer_availability;
```

### Error: "Can't DROP 'unique_lawyer_availability'"
**Solution**: Constraint doesn't exist or has different name. Check constraints:
```sql
SHOW INDEX FROM lawyer_availability;
```

### Error: "Check constraint failed"
**Solution**: You're using MySQL < 8.0.16. Comment out the CHECK CONSTRAINT section.

### Application not working after migration
**Solution**: Run the rollback script immediately:
```bash
mysql -u root -p lawfirm_db < 001_rollback.sql
```

---

## 📞 Support

If you encounter issues:
1. Check the error message carefully
2. Review the verification queries
3. Check application logs
4. If needed, restore from backup
5. Document the issue for future reference

---

## 📜 Migration History

### 001 - Add Flexible Scheduling (2025-10-02)
- Added `schedule_type`, `specific_date`, `is_active` columns
- Removed `unique_lawyer_availability` constraint
- Added performance indexes
- Status: ⏳ Pending execution
