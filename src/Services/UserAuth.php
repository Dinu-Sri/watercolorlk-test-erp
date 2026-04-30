<?php

declare(strict_types=1);

/**
 * Customer-side authentication. Sessions stored in PHP $_SESSION['user_id'].
 * NOT to be confused with AdminAuth (separate session key).
 */
class UserAuth
{
    private const SESSION_KEY = 'wlk_user_id';

    public function __construct(private UserRepository $users) {}

    public function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function currentUserId(): ?int
    {
        $this->ensureSession();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        return $id ? (int)$id : null;
    }

    public function currentUser(): ?array
    {
        $id = $this->currentUserId();
        if (!$id) return null;
        $u = $this->users->findById($id);
        if (!$u || $u['status'] !== 'active') {
            $this->logout();
            return null;
        }
        return $u;
    }

    public function login(int $userId): void
    {
        $this->ensureSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $userId;
        $this->users->touchLogin($userId);
    }

    public function logout(): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public function attemptPassword(string $email, string $password): array
    {
        $u = $this->users->findByEmail($email);
        if (!$u || empty($u['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }
        if ($u['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account has been disabled. Contact support.'];
        }
        if (!password_verify($password, (string)$u['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }
        $this->login((int)$u['id']);
        return ['ok' => true, 'user' => $u];
    }

    public function signup(string $email, string $password, ?string $fullName = null, ?string $phone = null): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if ($this->users->findByEmail($email)) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Try logging in.'];
        }
        $id = $this->users->create([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'full_name' => $fullName,
            'phone' => $phone,
        ]);
        return ['ok' => true, 'user_id' => $id];
    }

    public function require(string $loginUrl = '/account/login.php'): array
    {
        $u = $this->currentUser();
        if (!$u) {
            $next = $_SERVER['REQUEST_URI'] ?? '/account/';
            header('Location: ' . $loginUrl . '?next=' . urlencode($next));
            exit;
        }
        return $u;
    }
}
