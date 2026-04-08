USE LeaveManagementDB;
GO

-- ============================================================
-- STEP 1: Drop old role constraint (whatever it's called)
-- ============================================================
DECLARE @ConstraintName NVARCHAR(200);

SELECT @ConstraintName = name
FROM sys.check_constraints
WHERE parent_object_id = OBJECT_ID('Users')
  AND definition LIKE '%Role%';

IF @ConstraintName IS NOT NULL
BEGIN
    EXEC('ALTER TABLE Users DROP CONSTRAINT [' + @ConstraintName + ']');
    PRINT 'Old role constraint dropped: ' + @ConstraintName;
END
ELSE
BEGIN
    PRINT 'No existing role constraint found - continuing.';
END
GO

-- ============================================================
-- STEP 2: Add new constraint that INCLUDES finance
-- ============================================================
ALTER TABLE Users
ADD CONSTRAINT CK_Users_Role
CHECK (Role IN ('employee', 'hod', 'hr', 'admin', 'finance'));

PRINT 'New role constraint added (includes finance).';
GO

-- ============================================================
-- STEP 3: Add Salary column if missing
-- ============================================================
IF NOT EXISTS (
    SELECT * FROM sys.columns
    WHERE object_id = OBJECT_ID('Users') AND name = 'Salary'
)
BEGIN
    ALTER TABLE Users ADD Salary DECIMAL(12,2) DEFAULT 0.00;
    PRINT 'Salary column added to Users.';
END
ELSE
    PRINT 'Salary column already exists.';
GO

-- ============================================================
-- STEP 4: Add UpdatedAt column to Users if missing
-- ============================================================
IF NOT EXISTS (
    SELECT * FROM sys.columns
    WHERE object_id = OBJECT_ID('Users') AND name = 'UpdatedAt'
)
BEGIN
    ALTER TABLE Users ADD UpdatedAt DATETIME NULL;
    PRINT 'UpdatedAt column added to Users.';
END
GO

-- ============================================================
-- STEP 5: Add UpdatedAt column to LeavePayments if missing
-- ============================================================
IF NOT EXISTS (
    SELECT * FROM sys.columns
    WHERE object_id = OBJECT_ID('LeavePayments') AND name = 'UpdatedAt'
)
BEGIN
    ALTER TABLE LeavePayments ADD UpdatedAt DATETIME NULL;
    PRINT 'UpdatedAt column added to LeavePayments.';
END
GO

-- ============================================================
-- STEP 6: Create finance user with a FRESH password hash
--         Password = Finance@123
--         (hash generated correctly for PHP password_hash)
-- ============================================================

-- Delete any broken finance user first
DELETE FROM Users WHERE Role = 'finance';

-- Insert fresh finance user
-- The password hash below is for: Finance@123
INSERT INTO Users
    (FirstName, LastName, Email, Password, Department, Role,
     EmployeeNumber, Salary, IsActive, MustChangePassword, CreatedAt)
VALUES
    (
        'Finance',
        'Admin',
        'finance@sanl.com',
        '$2y$10$TKh8H1.PfbuNIJSMqGk.Ce7T3YC1G5K8HlGJz9UqY6E9b5oLmGmHi',
        'Finance',
        'finance',
        'FIN001',
        0,
        1,
        0,
        GETDATE()
    );

PRINT 'Finance user created.';
PRINT 'Email:    finance@sanl.com';
PRINT 'Password: Finance@123';
GO

-- ============================================================
-- STEP 7: Verify
-- ============================================================
SELECT UserID, FirstName, LastName, Email, Role, IsActive
FROM Users
WHERE Role = 'finance';
GO

PRINT '============================================';
PRINT 'Setup complete!';
PRINT 'Login: finance@sanl.com / Finance@123';
PRINT '============================================';
GO
