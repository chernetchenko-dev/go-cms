<?php
declare(strict_types=1);

class Db {
    private static ?self $instance = null;
    private ?PDO $pdo = null;

    private function __construct() { $this->connect(); }

    public static function getInstance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function connect(): void {
        // Сначала сокет
        try {
            $dsn = sprintf('mysql:dbname=%s;unix_socket=%s;charset=utf8mb4', DB_NAME, DB_SOCKET);
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return;
        } catch (PDOException) {}

        // Фоллбек TCP
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function getConnection(): PDO {
        if ($this->pdo === null) $this->connect();
        return $this->pdo;
    }

    public function initTables(): void {
        $pdo = $this->getConnection();

        // Реальная схема таблицы digest_events
        $pdo->exec("CREATE TABLE IF NOT EXISTS digest_events (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            title        VARCHAR(512)  NOT NULL,
            url          VARCHAR(1024) UNIQUE,
            source       VARCHAR(100),
            category     VARCHAR(50)   DEFAULT 'ai',
            description  TEXT,
            tags         JSON,
            ai_summary   TEXT,
            published_at DATETIME      DEFAULT NULL,
            created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            KEY idx_category    (category),
            KEY idx_published   (published_at),
            KEY idx_created     (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Лог вызовов ИИ
        $pdo->exec("CREATE TABLE IF NOT EXISTS digest_ai_log (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            event_id         INT          DEFAULT NULL,
            model_used       VARCHAR(255),
            response_time_ms INT          DEFAULT 0,
            tokens_used      INT          DEFAULT 0,
            status           ENUM('success','fallback','error') DEFAULT 'success',
            error_message    TEXT,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            KEY idx_event (event_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Дневные сводки
        $pdo->exec("CREATE TABLE IF NOT EXISTS digest_daily_summary (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            summary_date DATE          UNIQUE,
            summary_text TEXT,
            model_used   VARCHAR(255),
            items_count  INT           DEFAULT 0,
            created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Настройки админки
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            key_name     VARCHAR(100)  UNIQUE NOT NULL,
            key_value    TEXT,
            encrypted    BOOLEAN       DEFAULT 0,
            description  VARCHAR(255),
            created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Источники дайджеста (добавляются через админку)
        $pdo->exec("CREATE TABLE IF NOT EXISTS digest_sources (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100)  NOT NULL,
            url         VARCHAR(1024) NOT NULL,
            category    ENUM('ai','bim','events','norms') DEFAULT 'ai',
            prompt      TEXT          DEFAULT NULL COMMENT 'Кастомный промпт, оставь пустым для дефолта',
            active      BOOLEAN       DEFAULT 1,
            last_run    DATETIME      DEFAULT NULL,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            KEY idx_active   (active),
            KEY idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
