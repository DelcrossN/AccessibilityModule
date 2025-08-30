# Accessibility Statistics Chart Fix

## Problem
The chart on the accessibility statistics page was showing dummy/fallback data instead of real scan frequency data from the database.

## Root Cause
1. The `AccessibilityCacheService::getDailyScanCounts()` method was generating random sample data instead of querying real data from the database
2. The JavaScript fallback was generating random data when no backend data was found
3. The chart was supposed to show "frequency of accessibility scans" (how many scans per day) but was showing dummy data

## Solution Implemented

### 1. Updated AccessibilityCacheService
- **File**: `src/Service/AccessibilityCacheService.php`
- **Changes**:
  - Replaced `getDailyScanCounts()` method to pull real data from database
  - Added `getRealScanCounts()` private method that queries `accessibility_violations` table
  - Uses `COUNT(DISTINCT timestamp)` grouped by date to count unique scan sessions per day
  - Removed `generateSampleScanData()` method that was creating dummy data

### 2. Updated Template JavaScript
- **File**: `templates/accessibility-stats.html.twig`
- **Changes**:
  - Removed random data generation fallback
  - Now shows empty chart (zeros) when no real data exists instead of random numbers
  - Added debugging information to help diagnose data flow issues

### 3. Database Query Logic
The fix counts accessibility scans by:
- Querying `accessibility_violations` table for last 7 days
- Grouping by scan date (`FROM_UNIXTIME(timestamp, '%Y-%m-%d')`)
- Counting distinct timestamps per day (`COUNT(DISTINCT timestamp)`)
- This gives us the number of unique scan sessions per day

## Testing

### Test URLs Added
1. `/admin/reports/accessibility/populate-test-data` - Populates recent test data
2. `/admin/reports/accessibility/debug-data` - Shows database content and chart data

### How to Test
1. Visit `/admin/reports/accessibility/populate-test-data` to add recent scan data
2. Visit `/admin/reports/accessibility/stats` to see the chart with real data
3. Check browser console for debug information
4. Use `/admin/reports/accessibility/debug-data` to inspect raw data

## Data Flow
1. Scans are performed → violations stored in `accessibility_violations` table with timestamp
2. `AccessibilityStatsController::build()` calls `$this->cacheService->getDailyScanCounts()`
3. `getDailyScanCounts()` calls `getRealScanCounts()` to query database
4. Real data passed to template via `drupalSettings.accessibility.chartData`
5. JavaScript uses real data to render chart

## Files Modified
- `src/Service/AccessibilityCacheService.php`
- `templates/accessibility-stats.html.twig`  
- `src/Controller/AccessibilityStatsController.php` (added test methods)
- `accessibility.routing.yml` (added test routes)

## Expected Result
The chart now shows:
- Real scan frequency data when scans have been performed
- Zero values for days with no scans (instead of random data)
- Accurate representation of accessibility scanning activity over time
