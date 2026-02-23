-- Migration: Add missing columns to appointments table
-- Purpose: Support appointment modes (online/offline) and rejection reasons

ALTER TABLE `appointments` 
ADD COLUMN `consultation_mode` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'online' AFTER `consultation_type`,
ADD COLUMN `rejection_reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `status`;

-- Add check constraint for status values
ALTER TABLE `appointments` 
MODIFY COLUMN `status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' 
CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled'));

-- Create index on status for faster queries
CREATE INDEX idx_appointments_status ON appointments (status);
CREATE INDEX idx_appointments_professional_status ON appointments (professional_id, status);
