<?php

class RestApi
{
    private string $requestMethod;
    private string $requestUri;
    private array $requestData;
    private PDO $db;

    public function __construct(PDO $dbConnection)
    {
        $this->db = $dbConnection;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rawRoute = $_GET['route'] ?? '/';
        $this->requestUri = strtok($rawRoute, '?');
        if (in_array($this->requestMethod, ['POST', 'PUT'])) {
            $this->requestData = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }

    public function handleRequest(): void
    {
        $this->sendCorsHeaders();
        $method = $this->requestMethod;

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // --- ADD THIS CHECK ---
        // If the request isn't starting with /api, don't let the class handle it
        if (strpos($this->requestUri, '/api/') === false) {
            return; 
        }

        if ($method === 'GET') {

            if (preg_match('/^\/api\/user\/(\d+)$/', $this->requestUri, $matches)) {
                $userId = $matches[1];
                $this->handleSingleUser($userId);
                return;
            }

            switch ($this->requestUri) {
                case '/api/user':
                    $this->handleUsers();
                    return;
                default:
                    $this->sendResponse(['error' => 'Not Found'], 404);
                    return;
            }
        }

        if ($method === 'POST') {
            $this->processUserCreation($this->requestData);
            return;
        }

        if ($method === 'DELETE') {
            if (preg_match('/^\/api\/user\/(\d+)$/', $this->requestUri, $matches)) {
                $userId = $matches[1];
                $this->processUserDeletion($userId);
                return;
            }
        }

        if ($method === 'PUT') {
            if (preg_match('/^\/api\/user\/(\d+)$/', $this->requestUri, $matches)) {
                $userId = $matches[1];
                $this->processUserUpdate($userId, $this->requestData);
                return;
            }
        }

        $this->sendResponse(['error' => 'Method Not Allowed'], 405);
    }

    private function handleUsers(): void
    { 
        $users = $this->getUsers();
        $this->sendResponse($users);
        return;
    }

    private function handleSingleUser($id): void
    {
        $user = $this->getUserById($id);
        if ($user) {
            $this->sendResponse($user);
        } else {
            $this->sendResponse(['error' => 'User not found'], 404);
        }
        return;
    }

    private function getUsers(): array
    {
        try {

            $q = $_GET['q'] ?? '';
            $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // Prepare SQL for SQLite
            $sql = "SELECT * FROM users 
                    WHERE name LIKE :q OR email LIKE :q 
                    ORDER BY id ASC 
                    LIMIT :limit OFFSET :start";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':q', "%$q%", PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function getUserById($id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null; // Return null if fetch() returns false
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    private function processUserCreation(array $data): void
    {
        $name = $data['name'] ?? null;
        $age = $data['age'] ?? null;
        $email = $data['email'] ?? null;
        $avatarUrl = $data['avatarUrl'] ?? null;

        if (!$name || !$age || !$email) {
            $this->sendResponse(['error' => 'Missing required fields: name, age, and email are required'], 400);
            return;
        }

        // age must be a number
        if (!is_numeric($age)) {
            $this->sendResponse(['error' => 'Age must be a number'], 400);
            return;
        }

        // validate email format
        $emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (!preg_match($emailRegex, $email)) {
            $this->sendResponse(['error' => 'Invalid email format'], 400);
            return;
        }

        try {
            // unique email
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetchColumn() > 0) {
                $this->sendResponse(['error' => 'Email already exists'], 409); // 409 Conflict
                return;
            }

            // insert
            $sql = "INSERT INTO users (name, age, email, avatarUrl) VALUES (:name, :age, :email, :avatarUrl)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':age' => (int)$age,
                ':email' => $email,
                ':avatarUrl' => $avatarUrl
            ]);
 
            $newId = $this->db->lastInsertId();
            $newUser = $this->getUserById($newId);

            $this->sendResponse($newUser, 201); 

        } catch (PDOException $e) {
            $this->sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    private function processUserUpdate($id, array $data): void
    {
        // check existing
        $existingUser = $this->getUserById($id);
        if (!$existingUser) {
            $this->sendResponse(['error' => 'User not found'], 404);
            return;
        }

        $name = $data['name'] ?? $existingUser['name'];
        $age = $data['age'] ?? $existingUser['age'];
        $email = $data['email'] ?? $existingUser['email'];
        $avatarUrl = $data['avatarUrl'] ?? $existingUser['avatarUrl'];

        // age must be a number
        if (!is_numeric($age)) {
            $this->sendResponse(['error' => 'Age must be a number'], 400);
            return;
        }

        // validate email format
        $emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (!preg_match($emailRegex, $email)) {
            $this->sendResponse(['error' => 'Invalid email format'], 400);
            return;
        }

        try {
            // unique email
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $checkStmt->execute([':email' => $email, ':id' => $id]);
            if ($checkStmt->fetchColumn() > 0) {
                $this->sendResponse(['error' => 'Email is already taken by another user'], 409);
                return;
            }

            // update
            $sql = "UPDATE users 
                    SET name = :name, age = :age, email = :email, avatarUrl = :avatarUrl 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':age' => (int)$age,
                ':email' => $email,
                ':avatarUrl' => $avatarUrl,
                ':id' => $id
            ]);
 
            $updatedUser = $this->getUserById($id);
            $this->sendResponse($updatedUser);

        } catch (PDOException $e) {
            $this->sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    private function processUserDeletion($id): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) { // check if any row was actually deleted
                $this->sendResponse([
                    'status' => 'success',
                    'message' => "User with ID $id deleted successfully"
                ]);
            } else {
                $this->sendResponse([
                    'status' => 'failed',
                    'error' => 'User not found or already deleted'
                ], 404);
            }

        } catch (PDOException $e) {
            $this->sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    private function sendResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    private function getRequestBody(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) return $_REQUEST;
        $decoded = json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $_REQUEST;
    }

    private function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Content-Type: application/json; charset=UTF-8');
    }
}