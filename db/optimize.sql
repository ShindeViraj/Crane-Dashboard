-- ============================================
-- OPTIMIZATION SCRIPT for Hostinger MySQL
-- Run ONCE after initial schema setup
-- Safe to re-run — all operations are idempotent
-- ============================================

-- 1. Verify existing indexes are present
--    (schema.sql already creates these, but confirm)
SHOW INDEX FROM crane_data;

-- 2. Add covering index for fault report queries
--    The fault_reports.php page queries only Timestamp + fault_code columns
--    per crane. This index lets MySQL satisfy the query from the index alone
--    without touching the data rows (covering index optimization).
ALTER TABLE crane_data
  ADD INDEX IF NOT EXISTS idx_crane_faults (
    crane_id,
    Timestamp,
    MH_Altivar_fault_code,
    CT_Altivar_fault_code,
    LT_Altivar_fault_code,
    AH_Altivar_fault_code
  );

-- 3. Add covering index for the dashboard power aggregation query
--    get_history.php runs: GROUP BY DATE(Timestamp) with AVG/MAX on power columns
--    This index covers the power columns so MySQL can compute aggregates from the index.
ALTER TABLE crane_data
  ADD INDEX IF NOT EXISTS idx_crane_power (
    crane_id,
    Timestamp,
    MH_Motor_power,
    CT_Motor_power,
    LT_Motor_power,
    AH_Motor_power
  );

-- 4. Optimize the cranes table for dashboard subquery
--    Dashboard runs: SELECT MAX(cd.Timestamp) FROM crane_data cd WHERE cd.crane_id = c.crane_id
--    The existing idx_crane_timestamp already handles this, but let's ensure
--    the cranes table itself has proper indexing for JOIN performance.
SHOW INDEX FROM cranes;
-- crane_id already has a UNIQUE index from schema — good.

-- 5. Set InnoDB buffer pool hints (if you have MySQL admin access)
--    On shared Hostinger hosting, you typically can't change these,
--    but on VPS/Cloud hosting you would run:
--    SET GLOBAL innodb_buffer_pool_size = 268435456;  -- 256MB
--    SET GLOBAL innodb_log_file_size = 67108864;       -- 64MB

-- 6. Analyze tables to update index statistics
--    This helps the MySQL query optimizer make better decisions.
ANALYZE TABLE crane_data;
ANALYZE TABLE cranes;
ANALYZE TABLE users;
ANALYZE TABLE user_cranes;

-- 7. Verify all indexes after optimization
SHOW INDEX FROM crane_data;
