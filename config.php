<?php
// ============================================================
//  MeuBolso - Configuração e funções utilitárias
//  As credenciais ficam em config.local.php (fora do Git).
// ============================================================

$__local = __DIR__ . '/config.local.php';
if (!file_exists($__local)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Configuração ausente.\n\nCopie 'config.local.example.php' para 'config.local.php' "
      . "e preencha os dados do seu banco de dados.");
}
require_once $__local;

define('DB_CHARSET',       'utf8mb4');
define('SESSION_NAME',     'meubolso_sess');
define('SESSION_LIFETIME', 7 * 24 * 3600); // 7 dias

// --- Chave de recuperação de senha ---
define('CHAVE_ALFABETO',     'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'); // sem I, O, 0, 1 (evita confusão)
define('CHAVE_TAMANHO',      12);
define('REC_MAX_TENTATIVAS', 5);
define('REC_BLOQUEIO_MIN',   15);

// ============================================================
//  Conexão PDO (singleton)
// ============================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['erro' => 'Falha na conexão com o banco de dados.']));
        }
    }
    return $pdo;
}

// ============================================================
//  Sessão segura
// ============================================================
function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ============================================================
//  Helpers JSON
// ============================================================
function resposta(mixed $data, int $code = 200): never
{
    if (ob_get_level() > 0) ob_end_clean(); // descarta output acidental antes do JSON
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function erro(string $msg, int $code = 400): never
{
    resposta(['erro' => $msg], $code);
}

// ============================================================
//  Autenticação
// ============================================================
function usuario_logado(): int
{
    iniciar_sessao();
    if (empty($_SESSION['usuario_id'])) erro('Não autenticado. Faça login.', 401);
    return (int) $_SESSION['usuario_id'];
}

function usuario_logado_opcional(): ?int
{
    iniciar_sessao();
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}

// ============================================================
//  Sanitização / validação
// ============================================================
function str_limpo(string $v): string
{
    return trim(strip_tags($v));
}

function mes_ano_valido(mixed $m, mixed $a): bool
{
    $m = (int) $m;
    $a = (int) $a;
    return $m >= 1 && $m <= 12 && $a >= 2000 && $a <= 2100;
}

function validar_data(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// ============================================================
//  Chave de recuperação de senha
//  Formato: XXXX-XXXX-XXXX (12 caracteres, ~60 bits de entropia).
//  No banco fica apenas o HASH (como uma senha).
// ============================================================
function gerar_chave_recuperacao(): string
{
    $max   = strlen(CHAVE_ALFABETO) - 1;
    $chave = '';
    for ($i = 0; $i < CHAVE_TAMANHO; $i++) {
        $chave .= CHAVE_ALFABETO[random_int(0, $max)];
    }
    return implode('-', str_split($chave, 4));
}

function normalizar_chave(string $c): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($c));
}

/** Grava o hash de uma nova chave e zera o bloqueio. Retorna a chave em texto puro (exibida só uma vez). */
function salvar_nova_chave(int $uid, ?string $chave = null): string
{
    $chave = $chave ?? gerar_chave_recuperacao();
    $hash  = password_hash(normalizar_chave($chave), PASSWORD_BCRYPT);
    db()->prepare(
        'UPDATE usuarios SET chave_recuperacao_hash = ?, rec_tentativas = 0, rec_bloqueado_ate = NULL WHERE id = ?'
    )->execute([$hash, $uid]);
    return $chave;
}
