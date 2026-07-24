<?php
/**
 * Database.php - Prepared Statements 전용 DB 래퍼 (상속 기반).
 * mysqli 직접 조작 회피. 모든 쿼리는 prepare/bind/execute.
 * (참고 게시판의 $sql=mysqli stmt 사용 패턴을 클래스로 캡슐화)
 *
 * 상속 허용(BaseConn -> Database). Polymorphism/magic method 배제.
 */
if (!defined('main_index_files')) {
    http_response_code(503); echo "503 : Direct Access is Denied!"; exit;
}

class Database {
    protected $conn;

    public function __construct() {
        $this->conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    public function connect_errno(): int {
        return $this->conn ? $this->conn->connect_errno : 1;
    }

    public function connect_error(): string {
        return $this->conn ? $this->conn->connect_error : 'no conn';
    }

    public function set_charset(string $cs): void {
        if ($this->conn) $this->conn->set_charset($cs);
    }

    /**
     * Prepared 쿼리 실행. SELECT면 결과행 배열 반환, 그 외는 affected_rows 반환.
     * @param string $q   '? ' 플레이스홀더 쿼리
     * @param string $types  i/d/s/b
     * @param array $params
     * @return array|int  SELECT: 연관배열의 배열(int fetchAll). 그 외: affected_rows.
     */
    public function run(string $q, string $types = '', array $params = []) {
        $stmt = $this->conn->prepare($q);
        if ($stmt === false) {
            error_log("DB prepare fail: $q :: " . $this->conn->error);
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res !== false) {
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
            return $rows;
        }
        $aff = $stmt->affected_rows;
        $this->_insert_id = $this->conn->insert_id;
        $stmt->close();
        return $aff;
    }

    /** 단일행 반환 (없으면 null) */
    public function one(string $q, string $types = '', array $params = []) {
        $rows = $this->run($q, $types, $params);
        return $rows ? $rows[0] : null;
    }

    /** 단일 스칼라 반환 (첫 컬럼) */
    public function scalar(string $q, string $types = '', array $params = []) {
        $row = $this->one($q, $types, $params);
        if (!$row) return null;
        return reset($row);
    }

    public int|string $_insert_id = 0;
    public function insert_id() { return $this->_insert_id; }

    public function error(): string { return $this->conn ? $this->conn->error : ''; }
    public function real_escape(string $s): string { return $this->conn ? $this->conn->real_escape_string($s) : addslashes($s); }

    /** 트랜잭션 */
    public function begin(): void { $this->conn->begin_transaction(); }
    public function commit(): void { $this->conn->commit(); }
    public function rollback(): void { $this->conn->rollback(); }
}