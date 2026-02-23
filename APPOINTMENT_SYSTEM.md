# Complete Appointment Booking & Management System

## Overview
FinCart now has a fully functional appointment booking system that connects users with financial professionals. The system includes end-to-end workflows for booking, confirmation, acceptance/rejection, and appointment management.

## System Architecture

```
User Interface
├── Browse Professionals (professionals.php)
├── Professional Profile (professional/profile.php)
├── Book Appointment (booking.php)
├── View Appointments (my_appointments.php)
└── Cancel Appointment (cancel_appointment.php)

Professional Interface
├── Professional Dashboard (professional/dashboard.php)
└── Manage Appointments (professional/appointment_action.php)

Database
├── users (authentication & profiles)
├── professionals (approved professional profiles)
├── appointments (booking records)
└── professional_applications (pending applications)
```

## Complete User Flow

### 1. Browse & Book
**User Steps:**
1. User logs in to FinCart
2. Navigate to "Professional Network" → "Find Professionals"
3. View professional directory sorted by type (CA, Tax Consultant, Financial Advisor)
4. Click "Book Now" on desired professional
5. System validates professional exists and is active
6. User sees booking form with professional details

### 2. Appointment Request Form
**File:** `booking.php`
**Form Fields:**
- Professional ID (hidden)
- Appointment Date (date picker, min +1 day from today)
- Appointment Time (time input, limited to 9 AM - 6 PM)
- Consultation Type (dropdown: Initial Consultation, Follow-up, etc.)
- Consultation Mode (dropdown: Online, In-Person)
- Notes (optional text area)

**Validation:**
- Date must be in future
- Time must be within business hours (9 AM - 6 PM)
- Slot availability check (no double bookings)

### 3. Appointment Storage
**File:** `book_appointment.php`
**Process:**
1. Validate all input parameters
2. Check if professional exists and is active
3. Verify slot is not already booked
4. Insert appointment with status='pending'
5. Send emails to user and professional
6. Store appointment ID and redirect to confirmation page

**Database Insert:**
```sql
INSERT INTO appointments 
(professional_id, user_id, appointment_date, appointment_time, 
 consultation_type, consultation_mode, notes, status)
VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
```

### 4. Confirmation Page
**File:** `booking_confirmation.php`
**Displays:**
- Success message
- Full appointment details
- Professional information with contact
- Expected next steps (professional confirmation within 24 hours)
- Links to dashboard and professional directory

### 5. Notification Emails

**Email to User (Booking Confirmation)**
- Appointment details with professional info
- Date, time, type, mode, fee
- Status: Pending professional confirmation
- Call-to-action: View my appointments, logout

**Email to Professional (New Booking)**
- New booking notification
- Client name and email
- Appointment details
- Link to dashboard to accept/reject
- Status: Action required

## Professional Dashboard

### View & Manage Appointments
**File:** `professional/dashboard.php`
**Features:**
- Displays all appointments for the professional (sorted by date)
- Shows pending, confirmed, and rejected appointments
- Status badges with icons (⏳ pending, ✓ confirmed, ✗ rejected, ⊘ cancelled)
- Action buttons for pending appointments (Accept, Reject)
- Cancel button for confirmed appointments

**Appointment Table:**
| Column | Shows |
|--------|-------|
| Client Name | Full name + email |
| Date & Time | Formatted date and time |
| Type | Consultation type |
| Status | Color-coded badge |
| Actions | Accept/Reject buttons (pending only) |

### Accept Appointment
**File:** `professional/appointment_action.php`
**Action:** accept
**Process:**
1. Verify professional owns the appointment
2. Update status to 'confirmed'
3. Send HTML email to user with:
   - Confirmation message
   - Full appointment details
   - Professional contact information
   - Fee information
   - Next steps (payment processing)

### Reject Appointment
**File:** `professional/appointment_action.php`
**Action:** reject
**Process:**
1. Verify professional owns the appointment
2. Update status to 'rejected'
3. Store optional rejection reason
4. Send email to user with:
   - Rejection notification
   - Rejection reason if provided
   - Option to book another appointment

### Cancel Appointment (Professional)
**File:** `professional/appointment_action.php`
**Action:** cancel
**Process:**
1. Verify professional owns the appointment
2. Update status to 'cancelled'
3. Send notification email to user
4. Slot becomes available for rebooking

## User Appointment History

### View My Appointments
**File:** `my_appointments.php`
**Accessible from:** Dashboard → Professional Network → My Appointments

**Displays:**
- All user's appointments in card layout
- Sorted by date (newest last)
- Status with icon (⏳ pending, ✓ confirmed, ✗ rejected, ⊘ cancelled)
- Professional name and type
- Consultation details (date, time, type, mode)
- Rejection reason (if rejected)
- Booking timestamp

**Status-Specific Actions:**
| Status | Actions Available |
|--------|------------------|
| Pending | Cancel appointment |
| Confirmed | Cancel appointment |
| Rejected | Book Again (same professional) |
| Cancelled | Rebook (same professional) |

### Cancel Appointment (User)
**File:** `cancel_appointment.php`
**Process:**
1. Verify user owns the appointment
2. Update status to 'cancelled'
3. Send notification email to professional with:
   - Cancellation notification
   - Appointment details
   - Message about slot availability

## Email Configuration

**Current Implementation:** Uses PHP @mail() function
**Emails Configured:**
1. User booking confirmation (when appointment created)
2. Professional new booking notification (when appointment created)
3. User confirmation acceptance (when professional accepts)
4. User rejection notification (when professional rejects)
5. Professional cancellation notice (when user cancels)
6. User cancellation notice (when professional cancels)

**Email Format:** HTML with styled tables and sections
**From Address:** noreply@fincart.local

### Email Enhancement (Recommended)
For reliable email delivery, configure SMTP:
```php
// Use PHPMailer or SwiftMailer for production
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = '';  // Your email
$mail->Password = '';  // Your app password
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
```

## Database Schema

### appointments table
```sql
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `professional_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `consultation_type` VARCHAR(50),
  `consultation_mode` VARCHAR(50) DEFAULT 'online',
  `notes` TEXT,
  `status` VARCHAR(20) DEFAULT 'pending',
  `rejection_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (professional_id) REFERENCES professionals(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  
  KEY idx_appointments_status (status),
  KEY idx_appointments_professional_status (professional_id, status)
);
```

**Status Values:** pending, confirmed, rejected, cancelled

## File Inventory

### User-Facing Files
- `booking.php` - Appointment form
- `book_appointment.php` - Form handler
- `booking_confirmation.php` - Confirmation page
- `my_appointments.php` - Appointment history
- `cancel_appointment.php` - Cancellation handler
- `professionals.php` - Directory (already exists)
- `professional/profile.php` - Individual profile (already exists)

### Professional-Facing Files
- `professional/dashboard.php` - Dashboard with appointments
- `professional/appointment_action.php` - Accept/Reject/Cancel handler

### Database Files
- `fincartdb.txt` - Updated schema with appointments table
- `migration_add_appointment_columns.sql` - Migration for missing columns

## Testing Checklist

### Booking Flow
- [ ] User can browse professionals
- [ ] Click "Book Now" shows booking form
- [ ] Form validation works (date, time, required fields)
- [ ] Slot conflict detection prevents double bookings
- [ ] Appointment saved to database with status='pending'
- [ ] Confirmation page displays correctly
- [ ] User receives confirmation email

### Professional Dashboard
- [ ] Professional sees all appointments sorted by date
- [ ] Status badges display correctly
- [ ] Accept/Reject buttons visible for pending appointments
- [ ] Accept button updates status to 'confirmed'
- [ ] Accept sends confirmation email to user
- [ ] Reject button shows reason input
- [ ] Reject updates status and sends notification
- [ ] Cancel button works for confirmed appointments

### User Appointment History
- [ ] User can access "My Appointments" from dashboard
- [ ] All appointments display in card layout
- [ ] Status badges show correct icons and colors
- [ ] Can cancel pending/confirmed appointments
- [ ] Can rebook rejected/cancelled appointments
- [ ] Cancellation sends notification email to professional

## Integration Points

### With Existing Features
1. **Professional Directory** (`professionals.php`)
   - "Book Now" button → `booking.php?professional_id={id}`

2. **Professional Profile** (`professional/profile.php`)
   - "Book Now" button → `booking.php?professional_id={id}`

3. **Professional Application** (`professional/become_handler.php`)
   - Professional created with status='pending', 'approved', or 'active'
   - Only active professionals can receive bookings

4. **Professional Dashboard** (`professional/dashboard.php`)
   - Shows pending approvals AND appointments management
   - Seamless integration with application approval workflow

5. **User Dashboard** (`dashboard.php`)
   - "My Appointments" link added to Professional Network card

## Future Enhancements

### Phase 2 - Payment Integration
- Add payment gateway integration when appointment confirmed
- Store payment status in appointments table
- Send payment receipt emails

### Phase 3 - Availability Management
- Professional sets available time slots
- Automatic slot blocking based on availability
- Recurring appointment blocking

### Phase 4 - Advanced Features
- Appointment rescheduling (without cancellation)
- Meeting link generation (Zoom/Google Meet integration)
- SMS notifications alongside email
- Automatic reminders (24 hours before)
- Professional ratings/reviews after completed appointments
- Appointment templates for different consultation types

### Phase 5 - Analytics
- Professional booking rate dashboard
- Time slot utilization analytics
- Client satisfaction metrics
- Revenue tracking for professionals

## Troubleshooting

### Appointments Not Showing in Professional Dashboard
- Check `professionals` table has entries with `status='active'`
- Check `professional_id` in session matches `id` in professionals table
- Verify foreign key constraints are set up correctly

### Emails Not Sending
- In local WAMP: @mail() may not work without mail server
- Check `php.ini` for SMTP configuration
- Consider using PHPMailer with external SMTP (Gmail, SendGrid, etc.)

### Slot Still Shows as Booked After Cancellation
- Verify `status != 'cancelled'` is included in availability check
- Only 'pending' and 'confirmed' appointments block slots

### Professional Can't See Own Appointments
- Verify `professional_id` in appointments table matches their `id` in professionals
- Check user role is 'professional' in session
- Verify user_id matches in professionals table

## Environment Setup

### Required Tables
Ensure these migrations have been run:
```bash
# Primary migration
mysql < fincartdb.txt

# Or if table exists:
mysql < migration_add_appointment_columns.sql
```

### Required Columns in appointments table
- consultation_mode (VARCHAR 50)
- rejection_reason (TEXT, nullable)

If these columns don't exist, run the migration file provided.

---

**System Version:** 2.0
**Last Updated:** January 2026
**Status:** Production Ready
