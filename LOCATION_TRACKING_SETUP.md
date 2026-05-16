# Location Tracking Setup Guide

## Step 1: Add Location Columns to Database

Before using the system with location tracking, you must add the required columns to the attendance table.

### Option A: Automatic Setup (Recommended)

1. Go to: `http://your-site/attendence/add_location_columns.php`
2. Login as SuperAdmin
3. The script will automatically add all required columns

### Option B: Manual SQL

Run this SQL query in your database:

```sql
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_location VARCHAR(255);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_lat DECIMAL(10, 8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_lng DECIMAL(11, 8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_accuracy DECIMAL(10, 2);

ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_location VARCHAR(255);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_lat DECIMAL(10, 8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_lng DECIMAL(11, 8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_accuracy DECIMAL(10, 2);
```

---

## Step 2: Updated Files with Location Tracking

The following files now capture and store location data:

### `/employee/punch_in.php` - Punch In with Location

- ✅ Gets employee's GPS coordinates
- ✅ Reverses geocodes to location name (address)
- ✅ Stores: `punch_in_location`, `punch_in_lat`, `punch_in_lng`, `punch_in_accuracy`
- Supports multiple punch-ins per day with location for each

### `/employee/punch_out.php` - Punch Out with Location

- ✅ Gets employee's GPS coordinates
- ✅ Reverses geocodes to location name (address)
- ✅ Stores: `punch_out_location`, `punch_out_lat`, `punch_out_lng`, `punch_out_accuracy`
- Updates the last punch-in record with punch-out location

---

## Step 3: How Location Tracking Works

### Browser Permission

When employee clicks "Punch In" or "Punch Out", they will be asked:

```
"This site wants to access your location"
[Block] [Allow]
```

They must click **Allow** to enable location tracking.

### Location Data Captured

```
Latitude:  20.5937 (Decimal format)
Longitude: 78.9629 (Decimal format)
Accuracy:  15 meters (GPS margin of error)
Location:  "123 Main Street, City, State" (via reverse geocoding)
```

### What Happens If Location is Blocked

- ✅ Punch In/Out still works
- ⚠️ Location fields will be NULL
- Employee gets message: "Location permission denied. Punch recorded without location."

---

## Step 4: View Location Data

### In Employee Dashboard

- Shows location info in attendance records (if captured)
- Displays latitude/longitude as backup if geocoding fails

### In Admin Panel

- All punch records show location, lat, lng, accuracy
- Can export report with location data
- Location helps track where employees are working

---

## Step 5: Location Data Analysis

### Uses for Location Data

1. **Remote Work Verification**: Confirm employee is at office/work location
2. **Field Work Tracking**: Track employees working at different job sites
3. **Compliance**: Ensure employees are working from authorized locations
4. **Audit Trail**: Historical record of where punches were recorded
5. **Geofencing**: Future: Auto punch-in/out when entering geofence

### Reverse Geocoding Service

- Uses **OpenStreetMap Nominatim API** (free, no API key needed)
- Converts GPS coordinates to human-readable address
- 3-second timeout fallback to lat,lng display if service is slow
- Example results:
  - `20.5937, 78.9629` → `"123 Main St, Delhi, India"`

---

## Step 6: Data Storage

### Database Fields Added

| Field                | Type          | Purpose                                  |
| -------------------- | ------------- | ---------------------------------------- |
| `punch_in_location`  | VARCHAR(255)  | Reverse geocoded address for punch in    |
| `punch_in_lat`       | DECIMAL(10,8) | Latitude of punch in location            |
| `punch_in_lng`       | DECIMAL(11,8) | Longitude of punch in location           |
| `punch_in_accuracy`  | DECIMAL(10,2) | GPS accuracy in meters (lower is better) |
| `punch_out_location` | VARCHAR(255)  | Reverse geocoded address for punch out   |
| `punch_out_lat`      | DECIMAL(10,8) | Latitude of punch out location           |
| `punch_out_lng`      | DECIMAL(11,8) | Longitude of punch out location          |
| `punch_out_accuracy` | DECIMAL(10,2) | GPS accuracy in meters                   |

---

## Troubleshooting

### Issue: "Unknown column 'punch_in_location'" Error

**Solution**: Run the migration at `add_location_columns.php`

### Issue: Location showing as NULL

**Causes**:

1. Browser GPS access blocked
2. User clicked "Block" on permission prompt
3. GPS signal unavailable (underground, blocked building)
4. Geocoding API timeout (internet too slow)

**Solution**: Ask user to:

- Allow location permission
- Reconnect if network is weak
- Be in area with GPS signal

### Issue: Lat/Lng appear to be wrong

**Solution**:

- Check decimal places (should have 8 decimal places)
- Verify browser location services are enabled
- Try on different device/browser

---

## Privacy & Security Notes

✅ **Data Protection**:

- Location stored only in database with attendance records
- Only admins can view location data
- Employee can see their own punch location records

✅ **Accuracy**:

- GPS accuracy ±15 meters (typical smartphone)
- Accuracy field shows actual margin of error
- Multiple records show real movement throughout day

✅ **Consent**:

- Browser requires explicit permission for location access
- Employee must click "Allow" to capture location
- No tracking happens if location is denied

---

## Testing Location Tracking

### Test Steps

1. Go to Employee Dashboard
2. Click "Punch In"
3. When prompted: **Click "Allow"** for location access
4. Take selfie and submit
5. Check Admin Panel → View Attendance
6. Verify location, lat, lng, accuracy are recorded
7. Repeat for Punch Out

### Expected Results

✅ Location field shows readable address (e.g., "Building 5, Office Plex, Main Road")
✅ Lat/Lng show decimal coordinates
✅ Accuracy shows GPS error margin (typically 10-30 meters)
✅ Selfie is saved with location metadata

---

**Installation complete!** Employees can now punch in/out with full location tracking.
