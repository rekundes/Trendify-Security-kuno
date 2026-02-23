<?php
require_once __DIR__ . '/config.php';

/**
 * Simple helper wrappers for prepared statements using global $conn
 */
function db_fetch_all($sql, $types = null, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types !== null && count($params) > 0) {
        $bind_names = [];
        $bind_names[] = $types;
        // Need references for bind_param
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = & $params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_fetch_one($sql, $types = null, $params = []) {
    $rows = db_fetch_all($sql, $types, $params);
    if ($rows === false) return false;
    return count($rows) > 0 ? $rows[0] : null;
}

function db_execute($sql, $types = null, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types !== null && count($params) > 0) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = & $params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $ok = $stmt->execute();
    if ($ok) {
        $insert_id = $stmt->insert_id;
        $affected = $stmt->affected_rows;
        $stmt->close();
        return ['ok' => true, 'insert_id' => $insert_id, 'affected' => $affected];
    }
    $err = $stmt->error;
    $stmt->close();
    return ['ok' => false, 'error' => $err];
}

?>
