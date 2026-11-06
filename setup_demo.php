<?php
// ============================================================
//  MeuBolso - setup_demo.php
//
//  Cria uma conta de demonstração com ~6 meses de histórico.
//  As datas são geradas RELATIVAS A HOJE, então o dashboard
//  abre já com dados no mês atual.
//
//  Uso (qualquer um):
//    - Navegador (somente em localhost): http://localhost/meubolso/setup_demo.php
//    - Terminal:  php setup_demo.php
//
//  Pode ser executado várias vezes (recria a conta demo).
//  Em servidor público, DELETE este arquivo.
// ============================================================
require_once __DIR__ . '/config.php';

$cli = PHP_SAPI === 'cli';
if (!$cli && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Acesso negado: este script só roda em localhost ou pelo terminal.');
}

const DEMO_EMAIL = 'demo@meubolso.com';
const DEMO_SENHA = 'demo123';
const DEMO_CHAVE = 'DEMO-DEMO-DEMO';

$msgs  = [];
$erros = [];

// [categoria, descrição, valor, dia do mês, varia mês a mês?]
// categorias: 1 Alimentação, 2 Transporte, 3 Moradia, 4 Saúde, 5 Educação, 6 Lazer, 7 Mercado, 8 Outros
$base = [
    [3, 'Aluguel',                 950.00,  5, false],
    [3, 'Conta de energia',        145.00, 10, true],
    [3, 'Internet residencial',     99.90, 10, false],
    [4, 'Plano de saúde',          189.00, 20, false],
    [6, 'Netflix + Spotify',        55.90, 15, false],
    [5, 'Mensalidade de inglês',   280.00, 17, false],
    [7, 'Mercado semanal',         205.00,  3, true],
    [7, 'Mercado semanal',         198.00, 12, true],
    [7, 'Mercado semanal',         212.00, 23, true],
    [2, 'Gasolina',                118.00,  6, true],
    [2, 'Gasolina',                112.00, 25, true],
    [1, 'Padaria',                  27.50,  1, true],
    [1, 'Almoço de trabalho',      110.00,  8, true],
    [2, 'Uber',                     48.00, 14, true],
    [1, 'Lanche e café',            55.00, 19, true],
    [1, 'Jantar fora',              95.00, 22, true],
    [4, 'Farmácia',                 64.00, 12, true],
];

// Gastos extras, por "meses atrás" (0 = mês atual)
$extras = [
    0 => [[4, 'Consulta médica particular', 200.00, 4]],
    1 => [[6, 'Show com amigos', 180.00, 11], [8, 'Presente de aniversário', 130.00, 19]],
    2 => [[6, 'Viagem de fim de semana – hospedagem', 620.00, 9], [6, 'Viagem – passagem', 280.00, 8], [1, 'Restaurantes na viagem', 190.00, 10]],
    3 => [[4, 'Consulta dermatologista', 220.00, 6], [5, 'Livros técnicos', 95.00, 13]],
    4 => [[8, 'Conserto de celular', 180.00, 15], [6, 'Cinema', 46.00, 21]],
    5 => [[6, 'Festa de fim de mês', 240.00, 27], [7, 'Compras para a festa', 310.00, 26], [8, 'Presentes', 420.00, 20], [8, 'Conserto do carro', 650.00, 15]],
];

// Receitas avulsas, por "meses atrás"
$receitas = [
    0 => [['Cashback do cartão', 38.50, 2]],
    1 => [['Freelance – design de logo', 350.00, 12]],
    2 => [['Venda de notebook antigo', 800.00, 14]],
    3 => [['Freelance – revisão de texto', 180.00, 18], ['Presente de aniversário (dinheiro)', 200.00, 3]],
    4 => [['Venda de roupas (brechó online)', 95.00, 22]],
    5 => [['Freelance – aula particular', 120.00, 10]],
];

function data_no_mes(DateTime $primeiroDia, int $dia): string
{
    $d = min($dia, (int) $primeiroDia->format('t'));
    return $primeiroDia->format('Y-m-') . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
}

try {
    $pdo  = db();
    $hoje = (new DateTime('today'))->format('Y-m-d');
    $mesAtual = new DateTime('first day of this month 00:00:00');

    $pdo->beginTransaction();

    // Recria a conta demo (ON DELETE CASCADE limpa o resto)
    $pdo->prepare('DELETE FROM usuarios WHERE email = ?')->execute([DEMO_EMAIL]);

    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, onboarding_completo) VALUES (?, ?, ?, 1)')
        ->execute(['Ana Demo', DEMO_EMAIL, password_hash(DEMO_SENHA, PASSWORD_BCRYPT)]);
    $uid = (int) $pdo->lastInsertId();
    salvar_nova_chave($uid, DEMO_CHAVE);
    $msgs[] = "Usuário demo criado (ID $uid).";

    // Orçamento padrão
    $pdo->prepare('INSERT INTO orcamento_padrao (usuario_id, limite, renda) VALUES (?, 4000.00, 4800.00)')->execute([$uid]);
    $msgs[] = 'Orçamento padrão: renda R$ 4.800,00 / limite R$ 4.000,00.';

    $stDesp = $pdo->prepare('INSERT INTO despesas (usuario_id, categoria_id, descricao, valor, data) VALUES (?, ?, ?, ?, ?)');
    $stRec  = $pdo->prepare('INSERT INTO receitas_avulsas (usuario_id, descricao, valor, data) VALUES (?, ?, ?, ?)');

    $totDesp = 0; $totRec = 0; $somaGasto = 0.0;

    for ($k = 5; $k >= 0; $k--) {
        $mes = (clone $mesAtual)->modify("-$k months");

        // Exceção mensal: 2 meses atrás teve viagem, então o limite foi maior
        if ($k === 2) {
            $pdo->prepare('INSERT INTO orcamentos (usuario_id, mes, ano, limite, renda) VALUES (?, ?, ?, 4600.00, 4800.00)')
                ->execute([$uid, (int) $mes->format('n'), (int) $mes->format('Y')]);
            $msgs[] = 'Exceção mensal criada em ' . $mes->format('m/Y') . ' (limite R$ 4.600,00).';
        }

        foreach ($base as $idx => [$cat, $desc, $valor, $dia, $varia]) {
            if ($varia) {
                $fator = 0.88 + ((($k * 7) + ($idx * 5)) % 25) / 100; // determinístico: 0,88 a 1,12
                $valor = round($valor * $fator, 2);
            }
            $data = data_no_mes($mes, $dia);
            if ($data > $hoje) continue; // não cria gastos no futuro
            $stDesp->execute([$uid, $cat, $desc, $valor, $data]);
            $totDesp++; $somaGasto += $valor;
        }
        foreach ($extras[$k] ?? [] as [$cat, $desc, $valor, $dia]) {
            $data = data_no_mes($mes, $dia);
            if ($data > $hoje) continue;
            $stDesp->execute([$uid, $cat, $desc, $valor, $data]);
            $totDesp++; $somaGasto += $valor;
        }
        foreach ($receitas[$k] ?? [] as [$desc, $valor, $dia]) {
            $data = data_no_mes($mes, $dia);
            if ($data > $hoje) continue;
            $stRec->execute([$uid, $desc, $valor, $data]);
            $totRec++;
        }
    }

    $pdo->commit();
    $msgs[] = "$totDesp despesas e $totRec receitas extras inseridas (despesas somam R$ " . number_format($somaGasto, 2, ',', '.') . ').';
    $msgs[] = 'Conta demo pronta!  E-mail: ' . DEMO_EMAIL . '  |  Senha: ' . DEMO_SENHA . '  |  Chave de recuperação: ' . DEMO_CHAVE;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $erros[] = 'Erro: ' . $e->getMessage();
}

if ($cli) {
    foreach ($msgs as $m)  echo "[ok]   $m\n";
    foreach ($erros as $e) echo "[erro] $e\n";
    exit($erros ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup Demo – MeuBolso</title>
  <style>
    body { font-family: -apple-system, 'Segoe UI', sans-serif; background: #F3F2ED; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; margin: 0; }
    .card { background: #fff; border-radius: 16px; padding: 28px; max-width: 520px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    h1 { font-size: 21px; margin: 0 0 18px; color: #0F6E56; }
    .msg  { padding: 9px 14px; margin: 6px 0; border-radius: 6px; font-size: 14px; background: #E1F5EE; color: #054030; }
    .erro { background: #FCEBEB; color: #7a1f1f; }
    .aviso { background: #FAEEDA; color: #6b3d00; margin-top: 18px; padding: 12px 14px; border-radius: 8px; font-size: 13px; }
    a.btn { display: block; text-align: center; margin-top: 18px; background: #1D9E75; color: #fff; text-decoration: none; padding: 13px; border-radius: 8px; font-weight: 700; }
  </style>
</head>
<body>
<div class="card">
  <h1>💰 MeuBolso – Setup Demo</h1>
  <?php foreach ($msgs as $m): ?><div class="msg">✅ <?= htmlspecialchars($m) ?></div><?php endforeach; ?>
  <?php foreach ($erros as $e): ?><div class="msg erro">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php if (!$erros): ?>
    <div class="aviso">⚠️ Em servidor público, delete o arquivo <code>setup_demo.php</code> após usar.</div>
    <a class="btn" href="index.php">Ir para o login →</a>
  <?php endif; ?>
</div>
</body>
</html>
