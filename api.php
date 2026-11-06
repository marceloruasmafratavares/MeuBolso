<?php
// ============================================================
//  MeuBolso - API REST
//  Endpoint único: api.php?action=<acao>
// ============================================================
declare(strict_types=1);

// ob_start() captura qualquer output acidental (warnings PHP, erros,
// output do mailer) que quebraria o JSON no cliente.
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config.php';
iniciar_sessao();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

// ============================================================
//  ROTEADOR
// ============================================================
match (true) {
    // Auth
    $action === 'registrar'           && $method === 'POST'   => registrar($body),
    $action === 'login'               && $method === 'POST'   => login($body),
    $action === 'logout'              && $method === 'POST'   => logout(),
    $action === 'sessao'              && $method === 'GET'    => sessao_atual(),
    $action === 'recuperar_senha'     && $method === 'POST'   => recuperar_senha($body),
    $action === 'chave_gerar'         && $method === 'POST'   => chave_gerar($body),
    $action === 'senha_alterar'       && $method === 'POST'   => senha_alterar($body),
    $action === 'onboarding_concluir' && $method === 'POST'   => onboarding_concluir($body),

    // Categorias
    $action === 'categorias'          && $method === 'GET'    => listar_categorias(),

    // Despesas
    $action === 'despesas'            && $method === 'GET'    => listar_despesas(),
    $action === 'despesa_add'         && $method === 'POST'   => adicionar_despesa($body),
    $action === 'despesa_edit'        && $method === 'PUT'    => editar_despesa($body),
    $action === 'despesa_del'         && $method === 'DELETE' => deletar_despesa(),

    // Receitas avulsas
    $action === 'receitas'            && $method === 'GET'    => listar_receitas(),
    $action === 'receita_add'         && $method === 'POST'   => adicionar_receita($body),
    $action === 'receita_del'         && $method === 'DELETE' => deletar_receita(),

    // Orçamento
    $action === 'orcamento'           && $method === 'GET'    => buscar_orcamento(),
    $action === 'orcamento_set'       && $method === 'POST'   => salvar_orcamento($body),
    $action === 'orcamento_padrao_get'&& $method === 'GET'    => buscar_orcamento_padrao(),
    $action === 'orcamento_padrao_set'&& $method === 'POST'   => salvar_orcamento_padrao($body),
    $action === 'orcamento_limpar'    && $method === 'DELETE' => limpar_orcamento_mensal(),

    // Resumo / histórico
    $action === 'resumo'              && $method === 'GET'    => resumo_mensal(),
    $action === 'historico'           && $method === 'GET'    => historico_meses(),

    default => erro("Ação '$action' não encontrada.", 404),
};

// ============================================================
//  AUTH
// ============================================================

function registrar(array $b): never
{
    $nome  = str_limpo($b['nome']  ?? '');
    $email = filter_var($b['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senha = $b['senha'] ?? '';

    if (!$nome)             erro('Nome é obrigatório.');
    if (!$email)            erro('E-mail inválido.');
    if (strlen($senha) < 6) erro('A senha deve ter pelo menos 6 caracteres.');

    $pdo = db();
    $st  = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) erro('Este e-mail já está cadastrado.');

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $st   = $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha_hash, onboarding_completo) VALUES (?, ?, ?, 0)'
    );
    $st->execute([$nome, $email, $hash]);
    $id = (int) $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['usuario_id']    = $id;
    $_SESSION['usuario_nome']  = $nome;
    $_SESSION['usuario_email'] = $email;

    // Chave de recuperação de senha: exibida UMA vez ao usuário
    $chave = salvar_nova_chave($id);

    resposta([
        'mensagem'          => 'Conta criada com sucesso.',
        'usuario'           => usuario_publico($id),
        'chave_recuperacao' => $chave,
    ], 201);
}

function login(array $b): never
{
    $email = str_limpo($b['email'] ?? '');
    $senha = $b['senha'] ?? '';
    if (!$email || !$senha) erro('E-mail e senha são obrigatórios.');

    $st = db()->prepare('SELECT id, nome, email, senha_hash FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || !password_verify($senha, $u['senha_hash'])) {
        erro('E-mail ou senha incorretos.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']    = $u['id'];
    $_SESSION['usuario_nome']  = $u['nome'];
    $_SESSION['usuario_email'] = $u['email'];

    resposta(['mensagem' => 'Login realizado.', 'usuario' => usuario_publico((int) $u['id'])]);
}

function logout(): never
{
    session_unset();
    session_destroy();
    resposta(['mensagem' => 'Logout realizado.']);
}

function sessao_atual(): never
{
    $id = usuario_logado_opcional();
    if (!$id) resposta(['autenticado' => false]);
    resposta(['autenticado' => true, 'usuario' => usuario_publico($id)]);
}

/**
 * Recuperação de senha SEM e-mail: o usuário informa o e-mail da conta
 * + a chave de recuperação recebida no cadastro.
 * Proteção: 5 tentativas erradas bloqueiam a conta por 15 minutos.
 */
function recuperar_senha(array $b): never
{
    $email = trim((string) ($b['email'] ?? ''));
    $chave = normalizar_chave((string) ($b['chave'] ?? ''));
    $senha = (string) ($b['senha'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) erro('E-mail inválido.');
    if (strlen($chave) !== CHAVE_TAMANHO)           erro('A chave de recuperação deve ter 12 caracteres (formato XXXX-XXXX-XXXX).');
    if (strlen($senha) < 6)                         erro('A nova senha deve ter pelo menos 6 caracteres.');

    $st = db()->prepare(
        'SELECT id, chave_recuperacao_hash, rec_tentativas,
                (rec_bloqueado_ate IS NOT NULL AND rec_bloqueado_ate > NOW()) AS bloqueado
         FROM usuarios WHERE email = ?'
    );
    $st->execute([$email]);
    $u = $st->fetch();

    $generico = 'E-mail ou chave de recuperação incorretos.';

    if (!$u) erro($generico, 401); // não revela se o e-mail existe

    if ((int) $u['bloqueado'] === 1) {
        erro('Muitas tentativas incorretas. Tente novamente em ' . REC_BLOQUEIO_MIN . ' minutos.', 429);
    }

    if (!$u['chave_recuperacao_hash'] || !password_verify($chave, $u['chave_recuperacao_hash'])) {
        $tentativas = (int) $u['rec_tentativas'] + 1;
        if ($tentativas >= REC_MAX_TENTATIVAS) {
            db()->prepare(
                'UPDATE usuarios SET rec_tentativas = 0, rec_bloqueado_ate = DATE_ADD(NOW(), INTERVAL ' . REC_BLOQUEIO_MIN . ' MINUTE) WHERE id = ?'
            )->execute([$u['id']]);
        } else {
            db()->prepare('UPDATE usuarios SET rec_tentativas = ? WHERE id = ?')
               ->execute([$tentativas, $u['id']]);
        }
        erro($generico, 401);
    }

    // Chave correta: troca a senha e GIRA a chave (a antiga deixa de valer)
    db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
       ->execute([password_hash($senha, PASSWORD_BCRYPT), $u['id']]);
    $nova = salvar_nova_chave((int) $u['id']);

    resposta([
        'mensagem'   => 'Senha redefinida com sucesso!',
        'nova_chave' => $nova,
    ]);
}

/** Usuário logado gera uma nova chave de recuperação (exige a senha atual). */
function chave_gerar(array $b): never
{
    $uid   = usuario_logado();
    $senha = (string) ($b['senha'] ?? '');

    $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$uid]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($senha, $hash)) erro('Senha atual incorreta.', 401);

    resposta([
        'mensagem' => 'Nova chave de recuperação gerada.',
        'chave'    => salvar_nova_chave($uid),
        'usuario'  => usuario_publico($uid),
    ]);
}

/** Usuário logado altera a própria senha (exige a senha atual). */
function senha_alterar(array $b): never
{
    $uid   = usuario_logado();
    $atual = (string) ($b['senha_atual'] ?? '');
    $nova  = (string) ($b['senha_nova']  ?? '');

    if (strlen($nova) < 6) erro('A nova senha deve ter pelo menos 6 caracteres.');

    $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$uid]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($atual, $hash)) erro('Senha atual incorreta.', 401);

    db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
       ->execute([password_hash($nova, PASSWORD_BCRYPT), $uid]);

    resposta(['mensagem' => 'Senha alterada com sucesso!']);
}

function onboarding_concluir(array $b): never
{
    $uid    = usuario_logado();
    $limite = (float) ($b['limite'] ?? 0);
    $renda  = (float) ($b['renda']  ?? 0);

    // Salva orçamento padrão
    $st = db()->prepare("
        INSERT INTO orcamento_padrao (usuario_id, limite, renda)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE limite = VALUES(limite), renda = VALUES(renda)
    ");
    $st->execute([$uid, $limite, $renda]);

    // Marca onboarding como completo
    db()->prepare('UPDATE usuarios SET onboarding_completo = 1 WHERE id = ?')
       ->execute([$uid]);

    resposta(['mensagem' => 'Configuração inicial concluída!', 'usuario' => usuario_publico($uid)]);
}

function usuario_publico(int $id): array
{
    $st = db()->prepare(
        'SELECT id, nome, email, onboarding_completo, criado_em,
                (chave_recuperacao_hash IS NOT NULL) AS tem_chave
         FROM usuarios WHERE id = ?'
    );
    $st->execute([$id]);
    $u = $st->fetch() ?: [];
    if ($u) {
        $u['onboarding_completo'] = (bool) $u['onboarding_completo'];
        $u['tem_chave']           = (bool) $u['tem_chave'];
    }
    return $u;
}

// ============================================================
//  CATEGORIAS
// ============================================================

function listar_categorias(): never
{
    $st = db()->query('SELECT id, nome, icone, cor FROM categorias ORDER BY nome');
    resposta($st->fetchAll());
}

// ============================================================
//  DESPESAS
// ============================================================

function listar_despesas(): never
{
    $uid = usuario_logado();
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $ano = (int) ($_GET['ano'] ?? date('Y'));
    if (!mes_ano_valido($mes, $ano)) erro('Mês ou ano inválido.');

    $st = db()->prepare("
        SELECT d.id, d.descricao, d.valor, d.data, d.criado_em,
               c.id AS categoria_id, c.nome AS categoria_nome,
               c.icone AS categoria_icone, c.cor AS categoria_cor
        FROM despesas d
        JOIN categorias c ON c.id = d.categoria_id
        WHERE d.usuario_id = ? AND MONTH(d.data) = ? AND YEAR(d.data) = ?
        ORDER BY d.data DESC, d.criado_em DESC
    ");
    $st->execute([$uid, $mes, $ano]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['valor'] = (float) $r['valor'];

    resposta(['despesas' => $rows, 'total' => count($rows)]);
}

function adicionar_despesa(array $b): never
{
    $uid       = usuario_logado();
    $cat_id    = (int) ($b['categoria_id'] ?? 0);
    $descricao = str_limpo($b['descricao']  ?? '');
    $valor     = (float) ($b['valor']       ?? 0);
    $data      = $b['data'] ?? date('Y-m-d');

    if ($cat_id <= 0)         erro('Categoria inválida.');
    if (!$descricao)          erro('Descrição é obrigatória.');
    if ($valor <= 0)          erro('O valor deve ser maior que zero.');
    if (!validar_data($data)) erro('Data inválida.');

    $st = db()->prepare('SELECT id FROM categorias WHERE id = ?');
    $st->execute([$cat_id]);
    if (!$st->fetch()) erro('Categoria não encontrada.');

    $st = db()->prepare(
        'INSERT INTO despesas (usuario_id, categoria_id, descricao, valor, data) VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$uid, $cat_id, $descricao, $valor, $data]);
    $id = (int) db()->lastInsertId();

    $alerta = verificar_limite(
        $uid,
        (int) date('n', strtotime($data)),
        (int) date('Y', strtotime($data))
    );

    resposta([
        'mensagem' => 'Despesa registrada com sucesso!',
        'id'       => $id,
        'alerta'   => $alerta,
    ], 201);
}

function editar_despesa(array $b): never
{
    $uid       = usuario_logado();
    $id        = (int) ($_GET['id']          ?? 0);
    $cat_id    = (int) ($b['categoria_id']   ?? 0);
    $descricao = str_limpo($b['descricao']   ?? '');
    $valor     = (float) ($b['valor']        ?? 0);
    $data      = $b['data'] ?? '';

    if ($id <= 0)             erro('ID inválido.');
    if ($cat_id <= 0)         erro('Categoria inválida.');
    if (!$descricao)          erro('Descrição é obrigatória.');
    if ($valor <= 0)          erro('Valor inválido.');
    if (!validar_data($data)) erro('Data inválida.');

    $st = db()->prepare('SELECT id FROM despesas WHERE id = ? AND usuario_id = ?');
    $st->execute([$id, $uid]);
    if (!$st->fetch()) erro('Despesa não encontrada.', 404);

    db()->prepare("
        UPDATE despesas SET categoria_id = ?, descricao = ?, valor = ?, data = ?
        WHERE id = ? AND usuario_id = ?
    ")->execute([$cat_id, $descricao, $valor, $data, $id, $uid]);

    resposta(['mensagem' => 'Despesa atualizada com sucesso!']);
}

function deletar_despesa(): never
{
    $uid = usuario_logado();
    $id  = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) erro('ID inválido.');

    $st = db()->prepare('DELETE FROM despesas WHERE id = ? AND usuario_id = ?');
    $st->execute([$id, $uid]);
    if ($st->rowCount() === 0) erro('Despesa não encontrada.', 404);

    resposta(['mensagem' => 'Despesa removida.']);
}

// ============================================================
//  RECEITAS AVULSAS
// ============================================================

function listar_receitas(): never
{
    $uid = usuario_logado();
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $ano = (int) ($_GET['ano'] ?? date('Y'));
    if (!mes_ano_valido($mes, $ano)) erro('Mês ou ano inválido.');

    $st = db()->prepare("
        SELECT id, descricao, valor, data, criado_em
        FROM receitas_avulsas
        WHERE usuario_id = ? AND MONTH(data) = ? AND YEAR(data) = ?
        ORDER BY data DESC, criado_em DESC
    ");
    $st->execute([$uid, $mes, $ano]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['valor'] = (float) $r['valor'];

    resposta(['receitas' => $rows]);
}

function adicionar_receita(array $b): never
{
    $uid       = usuario_logado();
    $descricao = str_limpo($b['descricao'] ?? '');
    $valor     = (float) ($b['valor']      ?? 0);
    $data      = $b['data'] ?? date('Y-m-d');

    if (!$descricao)          erro('Descrição é obrigatória.');
    if ($valor <= 0)          erro('O valor deve ser maior que zero.');
    if (!validar_data($data)) erro('Data inválida.');

    $st = db()->prepare(
        'INSERT INTO receitas_avulsas (usuario_id, descricao, valor, data) VALUES (?, ?, ?, ?)'
    );
    $st->execute([$uid, $descricao, $valor, $data]);
    $id = (int) db()->lastInsertId();

    resposta([
        'mensagem' => 'Receita extra registrada com sucesso!',
        'id'       => $id,
    ], 201);
}

function deletar_receita(): never
{
    $uid = usuario_logado();
    $id  = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) erro('ID inválido.');

    $st = db()->prepare('DELETE FROM receitas_avulsas WHERE id = ? AND usuario_id = ?');
    $st->execute([$id, $uid]);
    if ($st->rowCount() === 0) erro('Receita não encontrada.', 404);

    resposta(['mensagem' => 'Receita removida.']);
}

// ============================================================
//  ORÇAMENTO
// ============================================================

function buscar_orcamento_padrao(): never
{
    $uid = usuario_logado();
    $st  = db()->prepare('SELECT limite, renda FROM orcamento_padrao WHERE usuario_id = ?');
    $st->execute([$uid]);
    $orc = $st->fetch();

    resposta($orc
        ? ['limite' => (float) $orc['limite'], 'renda' => (float) $orc['renda']]
        : ['limite' => 0.0, 'renda' => 0.0]
    );
}

function salvar_orcamento_padrao(array $b): never
{
    $uid    = usuario_logado();
    $limite = (float) ($b['limite'] ?? 0);
    $renda  = (float) ($b['renda']  ?? 0);

    if ($limite < 0) erro('Limite não pode ser negativo.');
    if ($renda  < 0) erro('Renda não pode ser negativa.');

    db()->prepare("
        INSERT INTO orcamento_padrao (usuario_id, limite, renda)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE limite = VALUES(limite), renda = VALUES(renda)
    ")->execute([$uid, $limite, $renda]);

    resposta(['mensagem' => 'Orçamento padrão salvo!', 'limite' => $limite, 'renda' => $renda]);
}

function buscar_orcamento(): never
{
    $uid = usuario_logado();
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $ano = (int) ($_GET['ano'] ?? date('Y'));
    if (!mes_ano_valido($mes, $ano)) erro('Mês ou ano inválido.');

    // 1. Verifica override específico do mês
    $st = db()->prepare(
        'SELECT id, mes, ano, limite, renda FROM orcamentos WHERE usuario_id = ? AND mes = ? AND ano = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $orc = $st->fetch();

    if ($orc) {
        resposta([
            'limite'    => (float) $orc['limite'],
            'renda'     => (float) $orc['renda'],
            'mes'       => $mes,
            'ano'       => $ano,
            'override'  => true,
        ]);
    }

    // 2. Fallback: orçamento padrão
    $st2 = db()->prepare('SELECT limite, renda FROM orcamento_padrao WHERE usuario_id = ?');
    $st2->execute([$uid]);
    $pad = $st2->fetch();

    resposta([
        'limite'   => $pad ? (float) $pad['limite'] : 0.0,
        'renda'    => $pad ? (float) $pad['renda']  : 0.0,
        'mes'      => $mes,
        'ano'      => $ano,
        'override' => false,
    ]);
}

function salvar_orcamento(array $b): never
{
    $uid    = usuario_logado();
    $mes    = (int)   ($b['mes']    ?? date('n'));
    $ano    = (int)   ($b['ano']    ?? date('Y'));
    $limite = (float) ($b['limite'] ?? 0);
    $renda  = (float) ($b['renda']  ?? 0);

    if (!mes_ano_valido($mes, $ano)) erro('Mês ou ano inválido.');
    if ($limite < 0) erro('Limite não pode ser negativo.');
    if ($renda  < 0) erro('Renda não pode ser negativa.');

    db()->prepare("
        INSERT INTO orcamentos (usuario_id, mes, ano, limite, renda)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE limite = VALUES(limite), renda = VALUES(renda)
    ")->execute([$uid, $mes, $ano, $limite, $renda]);

    resposta(['mensagem' => 'Orçamento do mês salvo!', 'limite' => $limite, 'renda' => $renda]);
}

function limpar_orcamento_mensal(): never
{
    $uid = usuario_logado();
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $ano = (int) ($_GET['ano'] ?? date('Y'));

    db()->prepare('DELETE FROM orcamentos WHERE usuario_id = ? AND mes = ? AND ano = ?')
       ->execute([$uid, $mes, $ano]);

    resposta(['mensagem' => 'Override removido. O mês voltará a usar o orçamento padrão.']);
}

// ============================================================
//  RESUMO / DASHBOARD
// ============================================================

function resumo_mensal(): never
{
    $uid = usuario_logado();
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $ano = (int) ($_GET['ano'] ?? date('Y'));
    if (!mes_ano_valido($mes, $ano)) erro('Mês ou ano inválido.');

    // Total de despesas
    $st = db()->prepare(
        'SELECT COALESCE(SUM(valor), 0) FROM despesas WHERE usuario_id = ? AND MONTH(data) = ? AND YEAR(data) = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $total_gasto = (float) $st->fetchColumn();

    // Total de receitas avulsas no mês
    $st = db()->prepare(
        'SELECT COALESCE(SUM(valor), 0) FROM receitas_avulsas WHERE usuario_id = ? AND MONTH(data) = ? AND YEAR(data) = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $total_receitas_avulsas = (float) $st->fetchColumn();

    // Orçamento (override ou padrão)
    $st = db()->prepare(
        'SELECT limite, renda FROM orcamentos WHERE usuario_id = ? AND mes = ? AND ano = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $orc = $st->fetch();

    if (!$orc) {
        $st2 = db()->prepare('SELECT limite, renda FROM orcamento_padrao WHERE usuario_id = ?');
        $st2->execute([$uid]);
        $orc = $st2->fetch() ?: ['limite' => 0, 'renda' => 0];
    }

    $limite = (float) $orc['limite'];
    $renda  = (float) $orc['renda'];
    $renda_total = $renda + $total_receitas_avulsas; // renda + extras

    // Por categoria
    $st = db()->prepare("
        SELECT c.id, c.nome, c.icone, c.cor,
               COUNT(d.id) AS qtd, SUM(d.valor) AS total
        FROM despesas d
        JOIN categorias c ON c.id = d.categoria_id
        WHERE d.usuario_id = ? AND MONTH(d.data) = ? AND YEAR(d.data) = ?
        GROUP BY c.id
        ORDER BY total DESC
    ");
    $st->execute([$uid, $mes, $ano]);
    $por_categoria = $st->fetchAll();
    foreach ($por_categoria as &$pc) $pc['total'] = (float) $pc['total'];

    // Evolução diária (acumulado)
    $st = db()->prepare("
        SELECT DAY(data) AS dia, SUM(valor) AS total_dia,
               SUM(SUM(valor)) OVER (ORDER BY DAY(data)) AS acumulado
        FROM despesas
        WHERE usuario_id = ? AND MONTH(data) = ? AND YEAR(data) = ?
        GROUP BY DAY(data) ORDER BY DAY(data)
    ");
    $st->execute([$uid, $mes, $ano]);
    $evolucao = $st->fetchAll();
    foreach ($evolucao as &$e) {
        $e['total_dia'] = (float) $e['total_dia'];
        $e['acumulado'] = (float) $e['acumulado'];
    }

    $alerta = verificar_limite($uid, $mes, $ano);

    resposta([
        'mes'                    => $mes,
        'ano'                    => $ano,
        'total_gasto'            => $total_gasto,
        'total_receitas_avulsas' => $total_receitas_avulsas,
        'limite'                 => $limite,
        'renda'                  => $renda,
        'renda_total'            => $renda_total,
        'saldo'                  => $renda_total - $total_gasto,
        'disponivel'             => $limite > 0 ? $limite - $total_gasto : null,
        'pct_limite'             => $limite > 0 ? round(($total_gasto / $limite) * 100, 1) : null,
        'por_categoria'          => $por_categoria,
        'evolucao'               => $evolucao,
        'alerta'                 => $alerta,
    ]);
}

function historico_meses(): never
{
    $uid = usuario_logado();
    $st  = db()->prepare("
        SELECT MONTH(data) AS mes, YEAR(data) AS ano,
               COUNT(id)   AS qtd_despesas, SUM(valor) AS total_gasto
        FROM despesas WHERE usuario_id = ?
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY YEAR(data) DESC, MONTH(data) DESC
        LIMIT 12
    ");
    $st->execute([$uid]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['total_gasto'] = (float) $r['total_gasto'];
    resposta(['meses' => $rows]);
}

// ============================================================
//  HELPER: verificar limite
// ============================================================

function verificar_limite(int $uid, int $mes, int $ano): ?array
{
    // Orçamento do mês (override ou padrão)
    $st = db()->prepare(
        'SELECT limite FROM orcamentos WHERE usuario_id = ? AND mes = ? AND ano = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $orc = $st->fetch();

    if (!$orc) {
        $st2 = db()->prepare('SELECT limite FROM orcamento_padrao WHERE usuario_id = ?');
        $st2->execute([$uid]);
        $orc = $st2->fetch();
    }

    if (!$orc || (float) $orc['limite'] <= 0) return null;

    $limite = (float) $orc['limite'];
    $st     = db()->prepare(
        'SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id = ? AND MONTH(data) = ? AND YEAR(data) = ?'
    );
    $st->execute([$uid, $mes, $ano]);
    $gasto = (float) $st->fetchColumn();
    $pct   = ($gasto / $limite) * 100;

    if ($pct >= 100) {
        return [
            'tipo'     => 'perigo',
            'mensagem' => sprintf('Limite ultrapassado em R$ %.2f!', $gasto - $limite),
            'pct'      => round($pct, 1),
        ];
    }
    if ($pct >= 80) {
        return [
            'tipo'     => 'aviso',
            'mensagem' => sprintf('Atenção: você usou %.1f%% do limite mensal.', $pct),
            'pct'      => round($pct, 1),
        ];
    }
    return null;
}
