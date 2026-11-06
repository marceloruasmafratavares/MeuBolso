<?php
// ============================================================
//  MeuBolso - Reset de senha pelo administrador (terminal)
//
//  Último recurso para quem perdeu a senha E a chave de
//  recuperação. Só funciona pela linha de comando:
//
//    php tools/reset_senha.php <email> <nova_senha>
//
//  Gera também uma NOVA chave de recuperação e a exibe.
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute somente pela linha de comando.');
}
require_once __DIR__ . '/../config.php';

[$_, $email, $nova] = array_pad($argv, 3, null);

if (!$email || !$nova) {
    fwrite(STDERR, "Uso: php tools/reset_senha.php <email> <nova_senha>\n");
    exit(1);
}
if (strlen($nova) < 6) {
    fwrite(STDERR, "A nova senha deve ter pelo menos 6 caracteres.\n");
    exit(1);
}

$st = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
$st->execute([$email]);
$uid = $st->fetchColumn();

if (!$uid) {
    fwrite(STDERR, "Nenhum usuário com o e-mail '$email'.\n");
    exit(1);
}

db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
   ->execute([password_hash($nova, PASSWORD_BCRYPT), $uid]);
$chave = salvar_nova_chave((int) $uid);

echo "Senha redefinida para $email.\n";
echo "Nova chave de recuperação: $chave\n";
echo "(entregue ao usuário e peça para guardar em local seguro)\n";
