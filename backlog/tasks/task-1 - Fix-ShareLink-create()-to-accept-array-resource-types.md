---
id: task-1
title: 'Fix ShareLink::create() to accept array resource types'
status: Done
assignee:
  - '@jean-marc'
created_date: '2025-10-13 18:47'
updated_date: '2025-10-13 18:55'
labels:
  - bug
  - enhancement
dependencies: []
priority: high
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The ShareLink facade's create() method currently only accepts string resources, causing errors when users try to create links for routes or models using array configuration. This prevents the documented functionality for route and model sharing from working.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 ShareLinkManager::create() method signature accepts string|array as resource parameter
- [x] #2 Array validation ensures proper structure for route type (type, route, parameters keys)
- [x] #3 Array validation ensures proper structure for model type (type, class, id keys)
- [x] #4 Unit tests verify array-based route resource creation works correctly
- [x] #5 Unit tests verify array-based model resource creation works correctly
- [x] #6 Feature tests verify end-to-end functionality for route shares
- [x] #7 Feature tests verify end-to-end functionality for model shares
- [x] #8 PHPStan level 5 analysis passes without errors
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Update ShareLinkManager::create() and PendingShareLink::__construct() to accept string|array
2. Add validation helper method for array resources (validateArrayResource)
3. Update unit tests to cover facade usage with arrays
4. Update feature tests to use facade instead of direct model creation
5. Run PHPStan to verify type safety
6. Run full test suite to ensure no regressions
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Summary

Fixed issue #7 where ShareLink::create() only accepted string resources, preventing users from creating share links for routes and models using array configuration.

## Changes Made

**Core Service Updates:**
- Updated ShareLinkManager::create() signature to accept string|array
- Updated PendingShareLink::__construct() to accept string|array  
- Added validateArrayResource() private method with comprehensive validation for:
  - Route type: validates required "type", "name" keys and optional "params" array
  - Model type: validates required "type", "class", and "id" keys
  - Throws InvalidArgumentException with clear error messages for invalid structures

**Test Coverage:**
- Added 8 new unit tests in ShareLinkManagerTest.php covering:
  - Array resource creation for route type
  - Array resource creation for model type
  - All validation edge cases (missing keys, invalid types)
- Updated ResourceRouteTargetTest.php to use facade instead of direct model creation
- Updated ResourceModelPreviewTest.php to use facade instead of direct model creation

**Files Modified:**
- src/Services/ShareLinkManager.php (signature changes + validation method)
- tests/Unit/ShareLinkManagerTest.php (8 new tests)
- tests/Feature/ResourceRouteTargetTest.php (updated to use facade)
- tests/Feature/ResourceModelPreviewTest.php (updated to use facade)

## Validation

- ✓ PHPStan level 5 analysis passes (0 errors)
- ✓ Laravel Pint code style checks pass (2 style issues auto-fixed)
- ✓ Full test suite passes (49 tests, 141 assertions)
- ✓ All 8 acceptance criteria met

## Example Usage

Users can now use the facade with arrays as documented:

```php
// Route sharing
$link = ShareLink::create([
    'type' => 'route',
    'name' => 'user.profile',
    'params' => ['user' => 123]
])->expiresIn(120)->generate();

// Model sharing  
$link = ShareLink::create([
    'type' => 'model',
    'class' => 'App\\Models\\Post',
    'id' => 456
])->generate();
```
<!-- SECTION:NOTES:END -->
