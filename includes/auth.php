<?php
/**
 * Authentication Class
 * Handles user authentication, login, registration and session management
 */

class Auth {
    private $db;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Register a new user
     * 
     * @param array $userData User data (username, password, email, first_name, last_name, role)
     * @return array Response with status and message
     */
    public function register($userData) {
        try {
            // Check if username already exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $userData['username']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Username already exists'];
            }
            
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $userData['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Email already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, password, email, first_name, last_name, role) 
                      VALUES (:username, :password, :email, :first_name, :last_name, :role)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':first_name', $userData['first_name']);
            $stmt->bindParam(':last_name', $userData['last_name']);
            $stmt->bindParam(':role', $userData['role']);
            
            $stmt->execute();
            
            $userId = $this->db->lastInsertId();
            
            // Log the activity
            logActivity('register', 'user', $userId, 'New user registered');
            
            return ['status' => true, 'message' => 'Registration successful', 'user_id' => $userId];
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Login a user
     * 
     * @param string $username Username
     * @param string $password Password
     * @param bool $remember Remember login
     * @return array Response with status and message
     */
    public function login($username, $password, $remember = false) {
        try {
            // Get user by username
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Invalid username or password'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ['status' => false, 'message' => 'Invalid username or password'];
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30); // 30 days
                
                // Store token in database
                $stmt = $this->db->prepare("UPDATE users SET remember_token = :token WHERE user_id = :user_id");
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':user_id', $user['user_id']);
                $stmt->execute();
                
                // Set cookie
                setcookie('remember_token', $token, $expires, '/');
                setcookie('remember_user', $user['user_id'], $expires, '/');
            }
            
            // Log the activity
            logActivity('login', 'user', $user['user_id'], 'User logged in');
            
            return ['status' => true, 'message' => 'Login successful', 'user' => $user];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if a remember me token is valid and log in the user
     * 
     * @return bool True if auto-login was successful, false otherwise
     */
    public function checkRememberToken() {
        if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
            $token = $_COOKIE['remember_token'];
            $userId = $_COOKIE['remember_user'];
            
            try {
                $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = :user_id AND remember_token = :token AND is_active = 1");
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    
                    // Log the activity
                    logActivity('auto_login', 'user', $user['user_id'], 'User auto-logged in via remember token');
                    
                    return true;
                }
            } catch (PDOException $e) {
                error_log("Auto-login error: " . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Logout a user
     * 
     * @return void
     */
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Clear session
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Clear remember me cookie
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user', '', time() - 3600, '/');
        
        // Update database to remove remember token
        if ($userId) {
            try {
                $stmt = $this->db->prepare("UPDATE users SET remember_token = NULL WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                // Log the activity
                logActivity('logout', 'user', $userId, 'User logged out');
            } catch (PDOException $e) {
                error_log("Logout error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Reset a user's password
     * 
     * @param string $email User's email
     * @return array Response with status and message
     */
    public function resetPassword($email) {
        try {
            // Check if email exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Email not found'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Save token to database
            $stmt = $this->db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE user_id = :user_id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            $stmt->bindParam(':user_id', $user['user_id']);
            $stmt->execute();
            
            // TODO: Send email with reset link (implementation depends on mailer library)
            
            // Log the activity
            logActivity('password_reset_request', 'user', $user['user_id'], 'Password reset requested');
            
            return ['status' => true, 'message' => 'Password reset instructions sent to your email'];
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Password reset failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update a user's profile
     * 
     * @param int $userId User ID
     * @param array $userData User data to update
     * @return array Response with status and message
     */
    public function updateProfile($userId, $userData) {
        try {
            $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email";
            $params = [
                ':first_name' => $userData['first_name'],
                ':last_name' => $userData['last_name'],
                ':email' => $userData['email'],
                ':user_id' => $userId
            ];
            
            // Only update password if a new one is provided
            if (!empty($userData['password'])) {
                $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                $query .= ", password = :password";
                $params[':password'] = $hashedPassword;
            }
            
            $query .= " WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            // Update session variables if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                $_SESSION['first_name'] = $userData['first_name'];
                $_SESSION['last_name'] = $userData['last_name'];
            }
            
            // Log the activity
            logActivity('profile_update', 'user', $userId, 'User profile updated');
            
            return ['status' => true, 'message' => 'Profile updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Profile update failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get user information
     * 
     * @param int $userId User ID
     * @return array|bool User data or false if not found
     */
    public function getUser($userId) {
        try {
            $stmt = $this->db->prepare("SELECT user_id, username, email, first_name, last_name, role, is_active, created_at FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return false;
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
}
?> 