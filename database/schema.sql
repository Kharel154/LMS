

CREATE DATABASE IF NOT EXISTS lms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms_db;

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default.png',
    statut ENUM('actif', 'suspendu') DEFAULT 'actif',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME NULL
);

-- Catégories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    couleur VARCHAR(7) DEFAULT '#4F46E5'
);

-- Modules (regroupement de cours)
CREATE TABLE modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    categorie_id INT,
    admin_id INT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Cours
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    enseignant_id INT NOT NULL,
    module_id INT NULL,
    categorie_id INT NOT NULL,
    statut ENUM('brouillon', 'en_attente', 'publie', 'rejete') DEFAULT 'brouillon',
    thumbnail VARCHAR(255) DEFAULT NULL,
    duree_estimee INT DEFAULT 0, -- en minutes
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_publication DATETIME NULL,
    FOREIGN KEY (enseignant_id) REFERENCES users(id),
    FOREIGN KEY (module_id) REFERENCES modules(id),
    FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

-- Leçons
CREATE TABLE lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    ordre INT NOT NULL,
    type ENUM('pdf', 'video') NOT NULL,
    fichier_url VARCHAR(255) NOT NULL,
    duree_minutes INT DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Quizzes
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    note_passage INT DEFAULT 50,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Questions de quiz
CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    type ENUM('qcm', 'vrai_faux') NOT NULL,
    ordre INT NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Choix de réponses
CREATE TABLE quiz_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text VARCHAR(255) NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- Inscriptions
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'termine', 'abandonne') DEFAULT 'actif',
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE(student_id, course_id)
);

-- Progression des leçons
CREATE TABLE lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    statut ENUM('non_commence', 'en_cours', 'termine') DEFAULT 'non_commence',
    date_completion DATETIME NULL,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id),
    UNIQUE(student_id, lesson_id)
);

-- Tentatives de quiz
CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    date_tentative DATETIME DEFAULT CURRENT_TIMESTAMP,
    passed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
);

-- Réponses aux quizzes
CREATE TABLE quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    choice_id INT NOT NULL,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id),
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id),
    FOREIGN KEY (choice_id) REFERENCES quiz_choices(id)
);

-- Devoirs
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    date_limite DATETIME NULL,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id)
);

-- Soumissions de devoirs
CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    fichier_url VARCHAR(255) NOT NULL,
    date_soumission DATETIME DEFAULT CURRENT_TIMESTAMP,
    note DECIMAL(5,2) NULL,
    commentaire_prof TEXT NULL,
    date_correction DATETIME NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Certificats
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    module_id INT NOT NULL,
    date_obtention DATETIME DEFAULT CURRENT_TIMESTAMP,
    code_verification VARCHAR(64) UNIQUE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (module_id) REFERENCES modules(id)
);

-- Signalements
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    type ENUM('technique', 'comportemental') NOT NULL,
    description TEXT NOT NULL,
    statut ENUM('ouvert', 'en_cours', 'resolu') DEFAULT 'ouvert',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id)
);

-- Configuration système
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) UNIQUE NOT NULL,
    valeur TEXT
);

-- Logs de connexion
CREATE TABLE connection_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP,
    action VARCHAR(50) NOT NULL
);


-- DONNÉES DE TEST


INSERT INTO users (nom, prenom, email, password_hash, role) VALUES
('Admin', 'Principal', 'admin@lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'), -- password
('Prof', 'Dupont', 'prof1@lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Prof', 'Martin', 'prof2@lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Student', 'Alice', 'student1@lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Student', 'Bob', 'student2@lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- Catégories et modules (à compléter selon besoin)
INSERT INTO categories (nom, couleur) VALUES
('Développement Web', '#4F46E5'),
('Marketing Digital', '#10B981');

-- ... (vous pouvez étendre les données de test)