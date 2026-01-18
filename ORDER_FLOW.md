# Order Submission Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    CUSTOMER ORDER FLOW                       │
└─────────────────────────────────────────────────────────────┘

1. USER VISITS PAGE
   │
   ├─> Not Logged In ──> Redirect to Login
   │
   └─> Logged In (Customer Role) ──> Show Order Form
                                      │
                                      ▼
2. FILL ORDER FORM
   ├─> Order Details (textarea) ───────────> Required ✓
   ├─> Phone Number (input) ──────────────> Required ✓
   ├─> GPS Location (button) ─────────────> Click to set
   │   ├─> Browser asks permission
   │   ├─> Get coordinates
   │   └─> Populate hidden fields
   └─> Promo Code (input) ────────────────> Optional

3. SUBMIT FORM
   │
   ▼
4. SERVER-SIDE VALIDATION (actions.php:371-514)
   │
   ├─> Check Role = 'customer' ────> NO ──> Error
   │                           └──> YES
   │
   ├─> Validate Details (min 3 chars) ──> FAIL ──> Error
   │                                └──> PASS
   │
   ├─> Validate GPS Provided ──────────> MISSING ──> Error
   │                              └──> PRESENT
   │
   ├─> Validate GPS Range ─────────────> INVALID ──> Error
   │   (lat: -90 to 90, lng: -180 to 180)
   │                              └──> VALID
   │
   ├─> Validate Phone (8 digits) ──────> INVALID ──> Error
   │                              └──> VALID
   │
   └─> Validate Promo Code (if provided)
       ├─> Check if exists
       ├─> Check if expired
       ├─> Check if max uses reached
       ├─> Check if already used by user
       └─> Calculate discount

5. INSERT INTO DATABASE
   │
   ├─> Generate 4-digit PIN (delivery_code)
   │
   ├─> INSERT INTO orders1 (
   │     client_id,
   │     customer_name,
   │     details,
   │     address,
   │     client_phone,
   │     pickup_lat,          ← GPS coordinates
   │     pickup_lng,          ← GPS coordinates
   │     status = 'pending',
   │     delivery_code,
   │     points_cost,
   │     promo_code,          ← Optional
   │     discount_amount      ← Optional
   │   )
   │
   ├─> Get last insert ID (order_id)
   │
   └─> IF promo code used:
       ├─> Update promo_codes.used_count
       └─> INSERT INTO promo_code_uses

6. SUCCESS RESPONSE
   │
   ├─> Set Flash Message:
   │   "Order created successfully!"
   │   OR
   │   "Order created! Discount applied: XX MRU"
   │
   └─> Redirect to index.php

7. ORDER NOW VISIBLE
   │
   ├─> Customer View:
   │   ├─> Order status: Pending
   │   ├─> Delivery code: XXXX
   │   └─> Can cancel if still pending
   │
   └─> Driver View:
       ├─> Order appears in nearby orders
       ├─> Can accept order (costs 20 points)
       └─> Distance calculated from GPS

┌─────────────────────────────────────────────────────────────┐
│                    DATABASE STRUCTURE                        │
└─────────────────────────────────────────────────────────────┘

orders1
├── id (PK)
├── client_id ──────────> users1.id
├── customer_name
├── details             ← From form
├── address             ← From form (or 'GPS Location')
├── client_phone        ← From form
├── pickup_lat          ← From GPS
├── pickup_lng          ← From GPS
├── dropoff_lat
├── dropoff_lng
├── distance_km
├── status (pending/accepted/picked_up/delivered/cancelled)
├── driver_id ──────────> users1.id (NULL until accepted)
├── delivery_code       ← 4-digit PIN
├── points_cost         ← 20 points
├── promo_code          ← From form (optional)
├── discount_amount     ← Calculated discount
├── accepted_at
├── picked_at
├── delivered_at
├── cancelled_at
├── cancel_reason
├── created_at
└── updated_at

┌─────────────────────────────────────────────────────────────┐
│                    KEY FIXES APPLIED                         │
└─────────────────────────────────────────────────────────────┘

FIX 1: Submit Button Styling
───────────────────────────
BEFORE: <button type="submit" name="add_order" 
          class="slider-btn-container w-100" 
          style="border: none; background: none; padding: 0; cursor: pointer;">

AFTER:  <button type="submit" name="add_order" 
          class="slider-btn-container w-100">

IMPACT: Button now displays correctly with proper CSS styling

FIX 2: GPS Hidden Inputs
────────────────────────
BEFORE: <input type="hidden" name="pickup_lat" required>
        <input type="hidden" name="pickup_lng" required>

AFTER:  <input type="hidden" name="pickup_lat">
        <input type="hidden" name="pickup_lng">

IMPACT: Prevents browser validation blocking submission before GPS is set

FIX 3: Address Field
───────────────────
BEFORE: <input type="text" name="address" required readonly>

AFTER:  <input type="text" name="address" readonly>

IMPACT: Server-side validation properly handles GPS requirement with error messages

┌─────────────────────────────────────────────────────────────┐
│                    VALIDATION SUMMARY                        │
└─────────────────────────────────────────────────────────────┘

CLIENT-SIDE:
✓ Details field - HTML5 required attribute
✓ Phone field - HTML5 pattern validation [234][0-9]{7}
✓ GPS button - Must be clicked to enable submission

SERVER-SIDE (actions.php):
✓ User role check (must be 'customer')
✓ Details minimum length (3 characters)
✓ GPS coordinates presence check
✓ GPS coordinates range validation
✓ Phone format validation (8 digits)
✓ Promo code validity check
✓ UTF-8 encoding enforcement

DATABASE:
✓ All required columns exist
✓ Proper data types and constraints
✓ Indexes for performance
✓ Foreign key references
✓ UTF-8 character set
