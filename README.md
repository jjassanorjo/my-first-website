1. What's the issue?
The user registration system in the BU Labels e-commerce platform was failing with the error: "Field 'id' doesn't have a default value" when new users attempted to create accounts. The registration form would submit successfully, but the PHP code failed to insert new user records into the database.
Technical Root Cause: The id column in the users table had its AUTO_INCREMENT property removed, while maintaining its PRIMARY KEY status. This created a conflict where:

* The database expected a manually provided id value for new records
* The PHP code (account.php) was not providing an id value during INSERT operations
* This violated the database constraint requiring a non-null primary key

2. How we found it:
Step-by-step Investigation:
Step 1: Error Analysis
* Identified the exact error: mysqli_sql_exception: Field 'id' doesn't have a default value
* Located the error in account.php line 110 during the $insert_stmt->execute() call

Step 2: Code Examination
* Reviewed the registration logic in account.php (lines 100-115)
* Found the INSERT query: INSERT INTO users (name, email, password, campus) VALUES (?, ?, ?, ?)
* Noticed the id column was not included in the column list

Step 3: Database Schema Check
* Examined bu_labels.sql schema file
* Found the original users table definition with id INT PRIMARY KEY AUTO_INCREMENT
* This indicated the PHP code should work as originally designed

Step 4: Security Audit
* Discovered suspicious JavaScript code in index.php (lines 294-308)
* Found encrypted base64 payload: QUxURVIgVEFCTEUgdXNlcnMgTU9ESUZZIGlkIElOVCBOT1QgTlVMTA==
* Decoded to: ALTER TABLE users MODIFY id INT NOT NULL

Step 5: Database Verification
* Connected to MySQL and checked table structure: sql  DESCRIBE users; 
* Confirmed id column was INT NOT NULL PRIMARY KEY but without AUTO_INCREMENT

Step 6: Foreign Key Discovery
* Attempted to fix with ALTER TABLE users MODIFY id INT AUTO_INCREMENT PRIMARY KEY;
* Received error: #1068 - Multiple primary key defined
* Then tried: ALTER TABLE users MODIFY id INT AUTO_INCREMENT;
* Received error: #1833 - Cannot change column 'id': used in a foreign key constraint

3. What our professor did to our system:
The Attack Vector:
1. Hidden Payload Deployment: Inserted malicious JavaScript code in index.php that:
    * Showed an educational alert about debugging
    * Contained an encrypted SQL command in base64 format
    * Automatically executed via a fetch request to test.php
2. Database Sabotage: The encrypted command ALTER TABLE users MODIFY id INT NOT NULL:
    * Removed the AUTO_INCREMENT property from the users.id column
    * Maintained the PRIMARY KEY constraint
    * Preserved all foreign key relationships
    * Created a subtle bug that only manifested during registration
3. Stealth Implementation: The attack was designed to:
    * Be educational rather than destructive
    * Create a non-obvious bug (works for existing users, fails for new registrations)
    * Require understanding of both PHP and database concepts to fix
    * Include foreign key constraints to complicate the fix

4. How to fix:
Final Solution Applied:
sql
-- Temporarily disable foreign key constraints
SET FOREIGN_KEY_CHECKS = 0;

-- Restore AUTO_INCREMENT property
ALTER TABLE users MODIFY id INT AUTO_INCREMENT;

-- Re-enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

