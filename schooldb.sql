-- -----------------------------------------------------
-- Database: schooldb
-- -----------------------------------------------------
CREATE DATABASE IF NOT EXISTS schooldb;
USE schooldb;

-- -----------------------------------------------------
-- Table structure for `activity_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `classes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS classes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    class_name VARCHAR(100) NOT NULL,
    standard_fee DECIMAL(10,2) DEFAULT 0.00,
    PRIMARY KEY (id)
);

-- Sample data for `classes`
INSERT INTO classes (id, class_name, standard_fee) VALUES
(1,'CE 1',50000.00),
(2,'CE 2',50000.00),
(3,'CINQUIEME',85000.00),
(4,'CLASS 1',50000.00),
(5,'CLASS 2',50000.00),
(6,'CLASS 3',50000.00),
(7,'CLASS 4',50000.00),
(8,'CLASS 5',55000.00),
(9,'CLASS 6',55000.00),
(10,'CM 1',55000.00),
(11,'CM 2',55000.00),
(12,'CP',50000.00),
(13,'FORM 1',85000.00),
(14,'FORM 1 TECHNICAL',120000.00),
(15,'FORM 2',85000.00),
(16,'FORM 2 TECHNICAL',120000.00),
(17,'FORM 3',85000.00),
(18,'FORM 3 TECHNICAL',120000.00),
(19,'FORM 4',85000.00),
(20,'FORM 4 TECHNICAL',120000.00),
(21,'FORM 5',90000.00),
(22,'FORM 5 TECHNICAL',120000.00),
(23,'GS',55000.00),
(24,'LOWERSIXTH ART',100000.00),
(25,'LOWERSIXTH SCIENCE',120000.00),
(26,'LOWERSIXTH TECHNICAL',125000.00),
(27,'MARE 1',120000.00),
(28,'MARE 2',120000.00),
(29,'MARE 3',120000.00),
(30,'MARE 4',120000.00),
(31,'N1',55000.00),
(32,'N2',55000.00),
(33,'PREMIERE',100000.00),
(34,'PS',55000.00),
(35,'QUATRIEME',85000.00),
(36,'SECONDE',100000.00),
(37,'SIL',50000.00),
(38,'SIXIEME',85000.00),
(39,'TERMINALE',100000.00),
(40,'TROISIEME',90000.00),
(41,'UPPERSIXTH ARTS',100000.00),
(42,'UPPERSIXTH SCIENCE',120000.00),
(43,'UPPERSIXTH TECHNICAL',125000.00);

-- -----------------------------------------------------
-- Table structure for `class_fees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS class_fees (
    id INT(11) NOT NULL AUTO_INCREMENT,
    class_name VARCHAR(50) NOT NULL,
    standard_fee DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `dropouts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS dropouts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    dropout_date DATE NOT NULL,
    status ENUM('Dropout','Dismissal') NOT NULL,
    fees_paid DECIMAL(10,2) DEFAULT 0.00,
    remarks VARCHAR(255) DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `expenses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id INT(11) NOT NULL AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    spent_at DATE NOT NULL,
    recorded_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date DATE NOT NULL DEFAULT CURDATE(),
    narration VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `fees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS fees (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    narration VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    term ENUM('1st','2nd','3rd') NOT NULL,
    session_year VARCHAR(10) NOT NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) DEFAULT NULL,
    date DATE NOT NULL DEFAULT CURDATE(),
    date_paid DATE NOT NULL DEFAULT CURDATE(),
    PRIMARY KEY (id)
);

-- Sample data for `fees`
INSERT INTO fees (id, student_id, narration, amount, term, paid_at, date, date_paid) VALUES
(1,1,'Registration Fee',15000.00,'1st','2025-08-16 12:56:17','2025-08-16','2025-08-16'),
(2,1,'School Fee',25000.00,'1st','2025-08-16 12:56:17','2025-08-16','2025-08-16'),
(3,1,'School Fee',10000.00,'1st','2025-08-16 12:56:31','2025-08-16','2025-08-16'),
(4,2,'Registration Fee',15000.00,'1st','2025-08-16 13:05:54','2025-08-16','2025-08-16'),
(5,2,'School Fee',25000.00,'1st','2025-08-16 13:05:54','2025-08-16','2025-08-16'),
(6,3,'Registration Fee',15000.00,'1st','2025-08-16 14:57:13','2025-08-16','2025-08-16'),
(7,3,'School Fee',20000.00,'1st','2025-08-16 14:57:13','2025-08-16','2025-08-16');

-- -----------------------------------------------------
-- Table structure for `fee_config`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS fee_config (
    id INT(11) NOT NULL AUTO_INCREMENT,
    class_id INT(11) NOT NULL,
    amount_expected DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `fee_payments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    narration VARCHAR(255) DEFAULT NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `marks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS marks (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    term ENUM('1st','2nd','3rd') NOT NULL,
    year VARCHAR(10) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    recorded_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `password_resets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- -----------------------------------------------------
-- Table structure for `students`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id INT(11) NOT NULL AUTO_INCREMENT,
    class_id INT(11) DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    class VARCHAR(50) NOT NULL,
    parent_contact VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active TINYINT(1) NOT NULL DEFAULT 1,
    guardian VARCHAR(255) DEFAULT NULL,
    enrollment_date DATE NOT NULL,
    age INT(11) NOT NULL,
    dob DATE DEFAULT NULL,
    contact VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (id)
);

-- Sample data for `students`
INSERT INTO students (id,class_id,name,gender,parent_contact,guardian,enrollment_date,age) VALUES
(1,1,'CHE BANG','male','644458135','Pa Che','2025-08-16',18),
(2,2,'TSE CHOH','male','677787442','PA TSE','2025-08-16',19),
(3,3,'RYAN FOBS','male','6475822521','PA FOBS','2025-08-16',20);

-- -----------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','bursar','teacher','principal') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
);

-- Sample data for `users`
INSERT INTO users (id,username,email,password,role) VALUES
(1,'admin','','admin123','admin'),
(4,'CHE RONALD NDOH','cheronald95@gmail.com','$2y$10$j7bo9Jr5VOazcAJP9tH0Ru7f8q9opHjN7a0pe7uesGvaTU99Mz9Ia','admin');
