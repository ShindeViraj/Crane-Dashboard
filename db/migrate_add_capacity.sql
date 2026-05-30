-- ============================================
-- Migration: Add capacity column to cranes table
-- Run ONCE on the live database
-- Safe to re-run — uses IF NOT EXISTS equivalent
-- ============================================

-- Add capacity column (e.g. "10 Ton", "25 MT")
ALTER TABLE cranes
    ADD COLUMN IF NOT EXISTS capacity VARCHAR(50) DEFAULT NULL AFTER name;
