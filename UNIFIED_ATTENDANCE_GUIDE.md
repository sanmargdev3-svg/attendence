# Unified Attendance System - Implementation Guide

## Overview

The attendance system now supports **unified attendance tracking** that merges data from both **Face Recognition** and **Manual Punch In/Out** systems. The system automatically takes the **first punch-in** and **last punch-out** times across both methods and calculates total hours worked.

---

## Key Features

### 1. **Unified Punch In/Out Logic**

- When an employee punches in (via face or manual):
  - If it's their **first punch-in** of the day → Record as `punch_in`
  - If they already have a `punch_in` but no `punch_out` → Record as `punch_out`
  - If they have both → Update `punch_out` if the new time is later

### 2. **Automatic First & Last Time Detection**

- **First Punch In**: Earliest time from all punch-in records
- **Last Punch Out**: Latest time from all punch-out records
- Works across both face detection and manual punching systems

### 3. **Total Hours Calculation**

- Calculates working hours from: **First Punch In → Last Punch Out**
- Handles same-day shifts (5:01 AM - 5:00 AM shift logic)
- Displays in human-readable format: `8h 30m` or decimal: `8.5 hrs`

### 4. **Multiple Punch Records Support**

- Employees can punch in/out multiple times per day
- System takes first IN and last OUT across all records
- Example: Punch In at 9:00 AM, Out at 12:00 PM (lunch), In at 1:00 PM, Out at 5:00 PM
  - **First Punch In**: 09:00
  - **Last Punch Out**: 17:00
  - **Total Hours**: 8h (breaks are included in calculation)

---

## Modified/Created Files

### 1. **`/api/record_attendance.php`** (MODIFIED)

- **Purpose**: Records punch in/out from face recognition system
- **Logic**:
  - Checks if attendance record exists for the day
  - If no punch_in → Creates new record with punch_in
  - If punch_in exists but no punch_out → Updates as punch_out
  - If both exist → Updates punch_out if new time is later
- **Response**: Returns `type: 'punch_in'` or `'punch_out'`

### 2. **`/api/get_attendance_summary.php`** (NEW)

- **Purpose**: Retrieves unified attendance summary for display
- **Returns**:
  - `first_punch_in`: Earliest punch-in time
  - `last_punch_out`: Latest punch-out time
  - `total_hours`: Total hours as decimal
  - `total_hours_formatted`: Human-readable format (8h 30m)
  - `all_punches`: Array of all punch timestamps
- **Usage**:
  ```php
  POST /api/get_attendance_summary.php
  Parameters: user_id, date
  ```

### 3. **`/employee/my_attendance.php`** (MODIFIED)

- **Changes**:
  - Groups attendance by date
  - Shows **First Punch In** time instead of single punch_in
  - Shows **Last Punch Out** time instead of single punch_out
  - Added **Total Hours** column with badge display
  - Calculates hours using new function: `calculateHours()`
  - Displays status indicators (✓ Punch In, ✓ Punch Out)

### 4. **`/user_dashboard.php`** (MODIFIED)

- **Changes**:
  - **Today's Status Box** now shows:
    - First Punch In time
    - Last Punch Out time
    - Total Hours worked (with visual badge)
    - Multiple punch detection indicator
  - **Attendance History Table** now displays:
    - First Punch In per day
    - Last Punch Out per day
    - Total Hours with styling
  - Updated calculation function: `calculateHours()`

---

## How It Works - Step by Step

### Example Scenario: Employee with Multiple Punch Records

**Database Records for 2026-03-01:**

```
ID | user_id | date       | punch_in | punch_out | status
1  | 5       | 2026-03-01 | 09:00:00 | NULL      | Present  (Face)
2  | 5       | 2026-03-01 | NULL     | 12:00:00  | Present  (Face)
3  | 5       | 2026-03-01 | 13:00:00 | NULL      | Present  (Manual)
4  | 5       | 2026-03-01 | NULL     | 17:30:00  | Present  (Manual)
```

**How It's Displayed:**

- **First Punch In**: 09:00 (earliest from all records)
- **Last Punch Out**: 17:30 (latest from all records)
- **Total Hours**: 8h 30m (from 09:00 to 17:30)
- **Punch Count**: 4 records detected

---

## Data Flow Diagram

```
Face Recognition System          Manual Punch In/Out
       ↓                                ↓
   record_attendance.php          punch_in.php / punch_out.php
       ↓                                ↓
    ┌─────────────────────────────────┐
    │  Check Existing Record for Date  │
    └─────────────────────────────────┘
       ↓
    ┌──────────────────────────────────────────────┐
    │ No Record?        Record Exists?              │
    │ ↓                 ↓                           │
    │ Create new   ┌────────────────────────┐      │
    │ with         │ punch_in exists?       │      │
    │ punch_in     │ ↓                      │      │
    │             │ No → Update as punch_in│      │
    │             │ Yes → Check punch_out  │      │
    │             │       ↓                │      │
    │             │ punch_out exists?      │      │
    │             │ ↓                      │      │
    │             │ No → Update as punch_out│    │
    │             │ Yes → No change needed │      │
    │             └────────────────────────┘      │
    └──────────────────────────────────────────────┘
       ↓
    Database Updated with First Punch In & Last Punch Out
       ↓
    ┌──────────────────────────────────┐
    │ get_attendance_summary.php        │
    │ Returns unified data for display  │
    └──────────────────────────────────┘
       ↓
    Employee Dashboard & My Attendance Page
    Displays: First Punch In, Last Punch Out, Total Hours
```

---

## SQL Query Examples

### Get Today's Unified Attendance

```sql
SELECT
    MIN(punch_in) as first_punch_in,
    MAX(punch_out) as last_punch_out,
    COUNT(*) as punch_count
FROM attendance
WHERE user_id = 5 AND date = '2026-03-01';
```

### Get Monthly Attendance Summary

```sql
SELECT
    date,
    MIN(punch_in) as first_punch_in,
    MAX(punch_out) as last_punch_out,
    COUNT(*) as records
FROM attendance
WHERE user_id = 5
    AND MONTH(date) = 3
    AND YEAR(date) = 2026
GROUP BY date
ORDER BY date DESC;
```

---

## Calculating Total Hours (PHP Function)

```php
function calculateHours($punch_in, $punch_out, $date) {
    if (!$punch_in || !$punch_out) {
        return ['hours' => 0, 'formatted' => '-'];
    }

    // Create full timestamps from date and time
    $punch_in_timestamp = strtotime($date . ' ' . $punch_in);
    $punch_out_timestamp = strtotime($date . ' ' . $punch_out);

    // Handle next-day punch out (for night shifts)
    if ($punch_out_timestamp < $punch_in_timestamp) {
        $punch_out_timestamp = strtotime(
            date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $punch_out
        );
    }

    // Calculate difference
    $total_seconds = max(0, $punch_out_timestamp - $punch_in_timestamp);

    // Convert to hours and minutes
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $total_hours = round($total_seconds / 3600, 2);
    $formatted = sprintf('%dh %dm', $hours, $minutes);

    return ['hours' => $total_hours, 'formatted' => $formatted];
}
```

---

## Testing Checklist

### ✓ Test 1: Single Punch In/Out via Face

1. Open Face Attendance page
2. Start camera and let a face be detected
3. Verify `punch_in` is recorded
4. Use camera again to record punch out
5. Verify `punch_out` is recorded in same record
6. Check Dashboard shows times and hours

### ✓ Test 2: Single Punch In/Out via Manual

1. Go to Employee Dashboard → Punch In
2. Record punch in with selfie
3. Go to Punch Out page
4. Record punch out with selfie
5. Check My Attendance shows times and hours

### ✓ Test 3: Mixed Methods (Face + Manual)

1. Record punch in via Face
2. Record punch out via Manual Punch Out
3. Verify Dashboard shows correct first/last times
4. Check total hours calculated correctly

### ✓ Test 4: Multiple Punch Records

1. Record punch in at 9:00 AM (Face)
2. Record punch out at 12:00 PM (Face)
3. Record punch in at 1:00 PM (Manual)
4. Record punch out at 5:00 PM (Manual)
5. Verify Dashboard shows:
   - First Punch In: 09:00
   - Last Punch Out: 17:00
   - Total Hours: 8h
   - Multiple punch indicator

### ✓ Test 5: History Display

1. Go to My Attendance
2. Filter by current month
3. Verify all days show:
   - First Punch In times
   - Last Punch Out times
   - Total Hours
4. Compare with different months

---

## Configuration Notes

### Shift Logic

- System uses 5:01 AM - 5:00 AM shift logic
- Any punch after 5:00 AM is counted to current date
- Any punch before 5:01 AM is counted to previous date

### Cooldown

- Face detection has 3-second cooldown to prevent duplicate records
- Can record multiple punch-ins on same camera session

### Accuracy

- Face matching requires >30% confidence
- Manual punching requires selfie capture

---

## Troubleshooting

### Issue: Punch Out not recording

**Solution**: Check that punch_in already exists for the day. First punch is always recorded as punch_in.

### Issue: Total hours showing as "-"

**Solution**: Both punch_in AND punch_out must exist. Verify both times are recorded in database.

### Issue: Multiple punches showing wrong total

**Solution**: System takes MIN(punch_in) and MAX(punch_out). Old records might interfere. Clear old test data.

### Issue: Next-day shift calculation wrong

**Solution**: Verify shift start/end times match your configuration. Database must have correct date values.

---

## Future Enhancements

1. **Export Reports**: Export attendance with total hours to Excel
2. **Overtime Calculation**: Automatically flag hours > 8 as overtime
3. **Break Deduction**: Subtract lunch breaks from total hours
4. **Location Tracking**: Add location info to punch records
5. **Alerts**: Notify manager of late punch-ins
6. **Approval Workflow**: Admin approval for manual punch corrections

---

## Support & Questions

For issues or questions about this unified attendance system, please contact your system administrator.

**Last Updated**: 2026-03-01
**System Version**: 2.0 (Unified Attendance)
