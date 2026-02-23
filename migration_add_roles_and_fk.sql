-- Migration: add role column and link professionals to users

-- IMPORTANT: Backup the database before running these statements.
-- Check whether the columns already exist (run these first):
SHOW COLUMNS FROM `users` LIKE 'role';
SHOW COLUMNS FROM `professionals` LIKE 'user_id';

-- If your tables use MyISAM, convert them to InnoDB before adding foreign keys:
ALTER TABLE `users` ENGINE=InnoDB;
ALTER TABLE `professionals` ENGINE=InnoDB;

-- 1) Add role column to users (remove this statement if `role` already exists)
ALTER TABLE `users`
    ADD COLUMN `role` ENUM('user','professional','professional_pending','admin') NOT NULL DEFAULT 'user';

-- 2) Add user_id to professionals (remove if already exists)
ALTER TABLE `professionals`
    ADD COLUMN `user_id` INT UNIQUE;

-- 3) Add foreign key (requires InnoDB)
ALTER TABLE `professionals`
    ADD CONSTRAINT fk_prof_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 4) Sync current roles from professionals table
UPDATE users u
    JOIN professionals p ON p.user_id = u.id
    SET u.role = 'professional'
    WHERE p.status = 'active';

UPDATE users u
    JOIN professionals p ON p.user_id = u.id
    SET u.role = 'professional_pending'
    WHERE p.status = 'pending';

-- 5) (Optional) Create an admin user — generate the hashed password in PHP and replace <HASHED_PASSWORD>
-- Example (run once after generating hash):
-- INSERT INTO users (full_name, email, password, role) VALUES ('Site Admin','admin@example.com','<HASHED_PASSWORD>','admin');
