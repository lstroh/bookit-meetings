-- ============================================
-- BOOKIT TEST DATA GENERATOR
-- Creates bookings for TODAY for testing
-- Safe to run multiple times (uses INSERT IGNORE)
-- ============================================

-- Variables (MySQL doesn't support variables in scripts, so we'll use subqueries)
SET @today = CURDATE();
SET @now = NOW();


-- Reset password to 'password' for user with ID 1
UPDATE wp_bookings_staff 
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    updated_at = NOW()
WHERE id = 1;


-- ============================================
-- 1. ENSURE TEST CUSTOMERS EXIST
-- ============================================
INSERT IGNORE INTO wp_bookings_customers (email, first_name, last_name, phone, marketing_consent, created_at, updated_at)
VALUES 
    ('alice.smith@example.com', 'Alice', 'Smith', '07700900001', 1, @now, @now),
    ('bob.jones@example.com', 'Bob', 'Jones', '07700900002', 0, @now, @now),
    ('charlie.brown@example.com', 'Charlie', 'Brown', '07700900003', 1, @now, @now),
    ('diana.prince@example.com', 'Diana', 'Prince', '07700900004', 1, @now, @now),
    ('edward.king@example.com', 'Edward', 'King', '07700900005', 0, @now, @now);

-- ============================================
-- 2. GET IDs (assumes your staff/services exist)
-- ============================================
SET @customer_1 = (SELECT id FROM wp_bookings_customers WHERE email = 'alice.smith@example.com' LIMIT 1);
SET @customer_2 = (SELECT id FROM wp_bookings_customers WHERE email = 'bob.jones@example.com' LIMIT 1);
SET @customer_3 = (SELECT id FROM wp_bookings_customers WHERE email = 'charlie.brown@example.com' LIMIT 1);
SET @customer_4 = (SELECT id FROM wp_bookings_customers WHERE email = 'diana.prince@example.com' LIMIT 1);
SET @customer_5 = (SELECT id FROM wp_bookings_customers WHERE email = 'edward.king@example.com' LIMIT 1);

-- Get first active staff and service
SET @staff_id = (SELECT id FROM wp_bookings_staff WHERE is_active = 1 ORDER BY id LIMIT 1);
SET @staff_id_2 = (SELECT id FROM wp_bookings_staff WHERE is_active = 1 ORDER BY id LIMIT 1 WHERE id != @staff_id)
;
SET @service_id = (SELECT id FROM wp_bookings_services WHERE is_active = 1 ORDER BY id LIMIT 1);

-- Get service details for pricing
SET @service_price = (SELECT price FROM wp_bookings_services WHERE id = @service_id);
SET @service_duration = (SELECT duration FROM wp_bookings_services WHERE id = @service_id);

-- ============================================
-- 3. DELETE TODAY'S OLD TEST BOOKINGS
-- (Prevents duplicates when running daily)
-- ============================================
DELETE FROM wp_bookings 
WHERE booking_date = @today 
  AND customer_id IN (@customer_1, @customer_2, @customer_3, @customer_4, @customer_5);

-- ============================================
-- 4. CREATE TODAY'S TEST BOOKINGS
-- ============================================

-- Booking 1: 09:00 - Confirmed (already paid)
INSERT INTO wp_bookings (
    customer_id, service_id, staff_id,
    booking_date, start_time, end_time, duration,
    status, total_price, deposit_amount, deposit_paid, balance_due, full_amount_paid,
    payment_method, payment_intent_id,
    special_requests,
    created_at, updated_at
) VALUES (
    @customer_1, @service_id, @staff_id,
    @today, '09:00:00', ADDTIME('09:00:00', SEC_TO_TIME(@service_duration * 60)), @service_duration,
    'confirmed', @service_price, @service_price, @service_price, 0.00, 1,
    'stripe', 'pi_test_09001234567890',
    'Please use organic products',
    @now, @now
);

-- Booking 2: 10:30 - Confirmed (partial deposit paid)
INSERT INTO wp_bookings (
    customer_id, service_id, staff_id,
    booking_date, start_time, end_time, duration,
    status, total_price, deposit_amount, deposit_paid, balance_due, full_amount_paid,
    payment_method,
    special_requests,
    created_at, updated_at
) VALUES (
    @customer_2, @service_id, @staff_id_2,
    @today, '10:30:00', ADDTIME('10:30:00', SEC_TO_TIME(@service_duration * 60)), @service_duration,
    'confirmed', @service_price, @service_price * 0.5, @service_price * 0.5, @service_price * 0.5, 0,
    'stripe',
    NULL,
    @now, @now
);

-- Booking 3: 13:00 - Pending Payment (pay on arrival)
INSERT INTO wp_bookings (
    customer_id, service_id, staff_id,
    booking_date, start_time, end_time, duration,
    status, total_price, deposit_amount, deposit_paid, balance_due, full_amount_paid,
    payment_method,
    special_requests,
    created_at, updated_at
) VALUES (
    @customer_3, @service_id, @staff_id,
    @today, '13:00:00', ADDTIME('13:00:00', SEC_TO_TIME(@service_duration * 60)), @service_duration,
    'pending_payment', @service_price, 0, 0, @service_price, 0,
    'pay_on_arrival',
    'First time customer - please confirm appointment',
    @now, @now
);

-- Booking 4: 14:30 - Confirmed (coming up soon for "Starting Soon" test)
INSERT INTO wp_bookings (
    customer_id, service_id, staff_id,
    booking_date, start_time, end_time, duration,
    status, total_price, deposit_amount, deposit_paid, balance_due, full_amount_paid,
    payment_method, payment_intent_id,
    created_at, updated_at
) VALUES (
    @customer_4, @service_id, @staff_id,
    @today, '14:30:00', ADDTIME('14:30:00', SEC_TO_TIME(@service_duration * 60)), @service_duration,
    'confirmed', @service_price, @service_price, @service_price, 0.00, 1,
    'stripe', 'pi_test_14301234567890',
    @now, @now
);

-- Booking 5: 16:00 - Completed (already finished)
INSERT INTO wp_bookings (
    customer_id, service_id, staff_id,
    booking_date, start_time, end_time, duration,
    status, total_price, deposit_amount, deposit_paid, balance_due, full_amount_paid,
    payment_method,
    staff_notes,
    created_at, updated_at
) VALUES (
    @customer_5, @service_id, @staff_id,
    @today, '08:00:00', ADDTIME('08:00:00', SEC_TO_TIME(@service_duration * 60)), @service_duration,
    'completed', @service_price, @service_price, @service_price, 0.00, 1,
    'cash',
    'Customer very satisfied, booked next appointment',
    @now, @now
);

-- ============================================
-- SUMMARY
-- ============================================
SELECT 
    COUNT(*) as bookings_created,
    @today as booking_date,
    MIN(start_time) as first_booking,
    MAX(start_time) as last_booking
FROM wp_bookings 
WHERE booking_date = @today;

SELECT 'Test bookings created successfully for today!' as status;