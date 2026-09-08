<?php
require_once __DIR__ . '/../config.php';
session_start();
@mkdir(dirname(DB_FILE), 0755, true);
$db = new PDO('sqlite:' . DB_FILE); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, state TEXT, city TEXT, login TEXT UNIQUE NOT NULL, password TEXT NOT NULL, created_at TEXT NOT NULL)");
$db->exec("CREATE TABLE IF NOT EXISTS votes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, voter_name TEXT, artist TEXT NOT NULL, title TEXT NOT NULL, vote TEXT NOT NULL, created_at TEXT NOT NULL)");
function json_out($data,$status=200){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
function body(){return json_decode(file_get_contents('php://input'),true) ?: $_POST;}
function current_user(){return $_SESSION['user'] ?? null;}
?>
