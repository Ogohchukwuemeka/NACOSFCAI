# NACOS E-Voting & Certificate System - Deployment Guide

## Quick Start Checklist

### Step 1: Apply Database Schema

```bash
# Import the database extensions to your MySQL database
mysql -u root -p nacos < database_extensions.sql

# Or execute through phpMyAdmin:
# 1. Go to phpMyAdmin
# 2. Select your 'nacos' database
# 3. Click "Import"
# 4. Select database_extensions.sql
# 5. Click "Go"
```

### Step 2: Verify Class Autoloading

The Election and Certificate classes should auto-load through composer:

- `classes/Election.php` - Auto-loaded
- `classes/Certificate.php` - Auto-loaded
- Classes instantiated via: `$election = new Election(Database::getInstance());`

### Step 3: Create Admin Navigation Entry (Optional)

To add E-Voting to the admin sidebar, edit `templates/admin-sidebar.php`:

```php
<li><a href="<?= APP_URL ?>/admin/elections.php"><i data-lucide="vote"></i> E-Voting</a></li>
```

### Step 4: Add Student Dashboard Menu (Optional)

To add Voting to student sidebar, edit `templates/student-sidebar.php`:

```php
<li><a href="<?= APP_URL ?>/student/voting.php"><i data-lucide="vote"></i> E-Voting</a></li>
```

---

## Feature Access URLs

### Admin Interfaces

- **Elections Management:** `localhost/NACOS/admin/elections.php`
- **Manage Positions:** `localhost/NACOS/admin/elections/positions.php?election_id=1`
- **Manage Candidates:** `localhost/NACOS/admin/elections/candidates.php?election_id=1`
- **View Results:** `localhost/NACOS/admin/elections/results.php?election_id=1`

### Student Interfaces

- **Voting:** `localhost/NACOS/student/voting.php`
- **View Results:** `localhost/NACOS/student/results.php` (Phase 5)

---

## Database Backup Recommendation

Before applying the schema:

```bash
mysqldump -u root -p nacos > nacos_backup_before_voting.sql
```

If needed to rollback, restore with:

```bash
mysql -u root -p nacos < nacos_backup_before_voting.sql
```

---

## Testing Workflow

### 1. Create an Election (Admin)

- Navigate to admin elections page
- Click "Create Election"
- Fill in title, description, dates
- Set status to "Active" or "Scheduled"

### 2. Create Positions (Admin)

- View the election
- Click "Manage Positions"
- Add positions (President, Vice President, etc.)

### 3. Register Candidates (Admin)

- View the election
- Click "Manage Candidates"
- Select student + position
- Upload passport photo
- Add manifesto (optional)

### 4. Test Voting (Student)

- Login as a student
- Visit `/student/voting.php`
- Select active election
- Vote for each position
- Verify "Voted" status appears

### 5. View Results (Admin)

- Click "View Results" on election
- Verify vote counts and percentages
- Test export functionality (PDF/Excel)

---

## Troubleshooting

### "Class not found: Election"

- Verify `classes/Election.php` exists
- Check `config/bootstrap.php` includes/autoloads classes
- Restart PHP server if needed

### "Table 'elections' doesn't exist"

- Run `database_extensions.sql` to create tables
- Verify MySQL user has CREATE TABLE permissions
- Check for SQL errors in import

### "Vote already recorded" error

- This is expected - prevents duplicate voting
- Check `votes` table for existing records
- Test with different student + position combo

### Students can't access voting page

- Verify student is logged in and verified
- Check `Auth::requireLogin('student')` in voting.php
- Ensure student account status is 'active'

---

## Performance Optimization (Production)

The tables already include indexes for:

- `elections.status` - Filter active/closed elections
- `votes.election_id` - Query votes per election
- `candidates.approved` - Filter approved candidates
- `event_participants.payment_status` - Filter verified participants

For high-volume elections (1000+ students):

```sql
-- Add additional index for vote counting
ALTER TABLE votes ADD INDEX idx_candidate_count (candidate_id, election_id);

-- Add index for student vote lookup
ALTER TABLE votes ADD INDEX idx_student_election (student_id, election_id);
```

---

## File Permissions

Ensure upload directory is writable:

```bash
chmod 755 assets/uploads/candidates/
chmod 755 assets/uploads/certificates/
```

---

## Email Configuration (Phase 6)

When implementing email notifications:

1. Use existing `Mailer` class in `classes/Mailer.php`
2. Configure SMTP in admin settings
3. Test with candidate confirmation emails
4. Automate with scheduled tasks for notifications

Example:

```php
$mailer = new Mailer(Database::getInstance());
$mailer->send($email, $subject, $body);
```

---

## Automation: Scheduled Email Notifications (Phase 8)

We've added a cron-friendly script to automate reminders, post-election results distribution, and unsent certificate delivery:

- Script: `scripts/email_notifications.php`

Recommended cron entry (every 10 minutes) — put this in your system crontab or use Task Scheduler on Windows:

```bash
# Run inside project root (adjust path to your NACOS installation)
*/10 * * * * php /path/to/NACOS/scripts/email_notifications.php >> /path/to/NACOS/logs/email_notifications.log 2>&1
```

What it does:

- Sends pre-election reminders to students for elections starting within 24 hours.
- Generates PDF results for recently concluded elections and emails the PDF to admins and students.
- Sends generated certificates that haven't been emailed yet.

Notes:

- Ensure `APP_URL` and SMTP settings are configured in your environment or admin settings.
- Ensure `assets/uploads/results/` and `assets/uploads/certificates/` are writable by the web user.
- Adjust recipient lists in `scripts/email_notifications.php` if you prefer to notify only registered voters.

---

## Next Features to Implement

**Phase 5:** Live Results Display

- Create `student/results.php` for viewing vote counts
- Add Chart.js visualizations
- Real-time vote updates

**Phase 6:** Email Notifications

- Pre-election reminders
- Post-election result distribution
- Candidate confirmation emails

**Phase 7:** Certificate System

- Admin certificate generation interface
- PDF rendering and storage
- Bulk email distribution

**Phase 8:** Gender Field

- Registration form update
- Profile page update
- Analytics in admin dashboard

---

## Support & Maintenance

- **Bugs/Issues:** Check error logs in `logs/` directory
- **Database Recovery:** Use backup SQL files
- **Performance:** Monitor `votes` table size during active elections
- **Archiving:** Move old elections to archive table after season ends

---

Generated: 2026-07-29
System: NACOS FCAI Student Portal v2.0
