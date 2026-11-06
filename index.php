<?php
require_once __DIR__ . '/config.php';
iniciar_sessao();
$logado = !empty($_SESSION['usuario_id']);
$onb_ok = true;
$tem_chave = true;
if ($logado) {
    $st = db()->prepare('SELECT onboarding_completo, (chave_recuperacao_hash IS NOT NULL) AS tem_chave FROM usuarios WHERE id = ?');
    $st->execute([$_SESSION['usuario_id']]);
    $row = $st->fetch();
    if (!$row) { session_unset(); session_destroy(); $logado = false; }   // usuário não existe mais
    else { $onb_ok = (bool) $row['onboarding_completo']; $tem_chave = (bool) $row['tem_chave']; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0F6E56">
  <title>MeuBolso – Controle Financeiro</title>
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap">
</head>
<body>

<div id="toast" class="toast hidden"></div>

<!-- ============================================================
     TELA DE AUTH
============================================================ -->
<div id="tela-auth" class="<?= $logado ? 'hidden' : '' ?>">
  <div class="auth-container">
    <div class="auth-logo">
      <span class="logo-icon">💰</span>
      <h1>MeuBolso</h1>
      <p>Controle financeiro simples e eficiente</p>
    </div>

    <div class="auth-tabs">
      <button class="auth-tab active" data-tab="login">Entrar</button>
      <button class="auth-tab"        data-tab="cadastro">Criar conta</button>
    </div>

    <!-- Login -->
    <form id="form-login" class="auth-form" autocomplete="on">
      <div class="campo">
        <label>E-mail</label>
        <input type="email" id="login-email" placeholder="seu@email.com" required autocomplete="email">
      </div>
      <div class="campo">
        <label>Senha</label>
        <input type="password" id="login-senha" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <div id="login-info" class="msg-sucesso hidden"></div>
      <div id="login-erro" class="msg-erro hidden"></div>
      <button type="submit" class="btn-primario">Entrar</button>
      <div class="auth-extra">
        <button type="button" class="link-btn" id="btn-esqueci-senha">Esqueci minha senha</button>
      </div>
    </form>

    <!-- Cadastro -->
    <form id="form-cadastro" class="auth-form hidden" autocomplete="on">
      <div class="campo">
        <label>Nome</label>
        <input type="text" id="cad-nome" placeholder="Seu nome" required autocomplete="name">
      </div>
      <div class="campo">
        <label>E-mail</label>
        <input type="email" id="cad-email" placeholder="seu@email.com" required autocomplete="email">
      </div>
      <div class="campo">
        <label>Senha <small>(mín. 6 caracteres)</small></label>
        <input type="password" id="cad-senha" placeholder="••••••••" required autocomplete="new-password" minlength="6">
      </div>
      <div id="cad-erro" class="msg-erro hidden"></div>
      <button type="submit" class="btn-primario">Criar conta</button>
    </form>

    <!-- Recuperação de senha (por chave) -->
    <form id="form-recuperar" class="auth-form hidden" autocomplete="off">
      <div class="recuperar-header">
        <button type="button" class="link-btn" id="btn-voltar-login">← Voltar ao login</button>
        <h2 class="recuperar-titulo">Recuperar senha</h2>
        <p>Informe o e-mail da conta e a <strong>chave de recuperação</strong> que você recebeu ao criar a conta.</p>
      </div>
      <div class="campo">
        <label>E-mail da conta</label>
        <input type="email" id="rec-email" placeholder="seu@email.com" required autocomplete="email">
      </div>
      <div class="campo">
        <label>Chave de recuperação</label>
        <input type="text" id="rec-chave" class="input-chave" placeholder="XXXX-XXXX-XXXX" maxlength="14"
               required autocomplete="off" autocapitalize="characters" spellcheck="false">
      </div>
      <div class="campo">
        <label>Nova senha <small>(mín. 6 caracteres)</small></label>
        <input type="password" id="rec-senha" placeholder="••••••••" required minlength="6" autocomplete="new-password">
      </div>
      <div class="campo">
        <label>Confirmar nova senha</label>
        <input type="password" id="rec-senha2" placeholder="••••••••" required minlength="6" autocomplete="new-password">
      </div>
      <div id="rec-erro" class="msg-erro hidden"></div>
      <button type="submit" class="btn-primario">Redefinir senha</button>
      <p class="rec-dica">Perdeu a chave? Sem ela não é possível recuperar a senha automaticamente. Entre em contato com quem administra o sistema.</p>
    </form>
  </div>
</div>

<!-- ============================================================
     APP PRINCIPAL
============================================================ -->
<div id="app" class="<?= $logado ? '' : 'hidden' ?>">

  <header class="header">
    <div class="header-inner">
      <div class="header-logo">💰 MeuBolso</div>
      <nav class="header-nav">
        <button class="nav-btn active" data-view="dashboard">Dashboard</button>
        <button class="nav-btn" data-view="historico">Histórico</button>
        <button class="nav-btn" data-view="orcamento">Orçamento</button>
      </nav>
      <div class="header-user">
        <button id="btn-conta" class="btn-conta desktop-only" title="Minha conta">
          <span class="conta-avatar">👤</span><span id="nome-usuario"></span>
        </button>
        <button id="btn-logout" class="btn-icone" title="Sair" aria-label="Sair">⎋</button>
      </div>
    </div>
  </header>

  <div id="alerta-global" class="alerta hidden"></div>

  <!-- =========================================================
       VIEW: DASHBOARD
  ========================================================= -->
  <section id="view-dashboard" class="view ativa">

    <div class="mes-nav">
      <button class="btn-mes" id="btn-mes-ant">&#8249;</button>
      <h2 id="mes-titulo"></h2>
      <button class="btn-mes" id="btn-mes-prox">&#8250;</button>
    </div>

    <!-- Cards -->
    <div class="cards-grid" id="cards-grid">
      <div class="card card-gasto">
        <div class="card-icone">💸</div>
        <div class="card-info">
          <span class="card-label">Total gasto</span>
          <span class="card-valor" id="card-total">R$ 0,00</span>
        </div>
      </div>
      <div class="card card-receita" id="card-receita-wrap" style="display:none">
        <div class="card-icone">💹</div>
        <div class="card-info">
          <span class="card-label">Receitas extras</span>
          <span class="card-valor" id="card-receitas-avulsas">R$ 0,00</span>
        </div>
      </div>
      <div class="card card-limite">
        <div class="card-icone">🎯</div>
        <div class="card-info">
          <span class="card-label">Limite mensal</span>
          <span class="card-valor" id="card-limite">R$ 0,00</span>
        </div>
      </div>
      <div class="card card-renda">
        <div class="card-icone">💵</div>
        <div class="card-info">
          <span class="card-label">Renda total</span>
          <span class="card-valor" id="card-renda">R$ 0,00</span>
        </div>
      </div>
      <div class="card card-saldo">
        <div class="card-icone">📊</div>
        <div class="card-info">
          <span class="card-label">Saldo disponível</span>
          <span class="card-valor" id="card-saldo">R$ 0,00</span>
        </div>
      </div>
    </div>

    <!-- Progresso -->
    <div class="progresso-container" id="progresso-wrap" style="display:none">
      <div class="progresso-header">
        <span>Uso do limite mensal</span>
        <span id="progresso-pct">0%</span>
      </div>
      <div class="progresso-barra">
        <div class="progresso-fill" id="progresso-fill"></div>
      </div>
    </div>

    <!-- Gráficos -->
    <div class="graficos-grid">
      <div class="grafico-card">
        <h3>Gastos por categoria</h3>
        <div class="grafico-wrap"><canvas id="grafico-pizza"></canvas></div>
        <div id="legenda-categorias" class="legenda"></div>
      </div>
      <div class="grafico-card">
        <h3>Evolução do mês</h3>
        <div class="grafico-wrap"><canvas id="grafico-linha"></canvas></div>
      </div>
    </div>

    <!-- Últimas despesas -->
    <div class="secao">
      <div class="secao-header">
        <h3>Últimas despesas</h3>
        <div class="secao-acoes">
          <button class="btn-link" id="btn-ver-historico-completo">Ver histórico completo →</button>
          <button class="btn-secundario" id="btn-add-receita">+ Receita extra</button>
          <button class="btn-primario"   id="btn-add-dash">+ Adicionar despesa</button>
        </div>
      </div>
      <div id="lista-despesas-dash" class="lista-despesas">
        <div class="estado-vazio">
          <span class="vazio-icone">📭</span>
          <p>Nenhum gasto registrado neste mês.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================
       VIEW: HISTÓRICO
  ========================================================= -->
  <section id="view-historico" class="view">
    <div class="view-header">
      <h2>Histórico de gastos</h2>
      <div class="filtros">
        <select id="filtro-mes" class="select-filtro">
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
              <?= ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'][$m] ?>
            </option>
          <?php endfor; ?>
        </select>
        <select id="filtro-ano" class="select-filtro">
          <?php for ($a = date('Y'); $a >= date('Y')-3; $a--): ?>
            <option value="<?= $a ?>"><?= $a ?></option>
          <?php endfor; ?>
        </select>
        <select id="filtro-cat" class="select-filtro">
          <option value="">Todas as categorias</option>
        </select>
        <button class="btn-primario btn-sm" id="btn-filtrar">Filtrar</button>
      </div>
    </div>

    <div class="tabela-wrap">
      <table class="tabela">
        <thead>
          <tr>
            <th>Data</th><th>Descrição</th><th>Categoria</th>
            <th class="col-valor">Valor</th><th class="col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody id="tabela-body">
          <tr><td colspan="5" class="tabela-vazio">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="tabela-rodape"><span id="tabela-total-txt"></span></div>
  </section>

  <!-- =========================================================
       VIEW: ORÇAMENTO
  ========================================================= -->
  <section id="view-orcamento" class="view">
    <div class="view-header"><h2>Configurar orçamento</h2></div>

    <div class="orcamento-grid">

      <!-- Orçamento Padrão -->
      <div class="orcamento-card orcamento-padrao-card">
        <div class="orcamento-badge">⭐ Padrão (recorrente)</div>
        <h3>Orçamento padrão</h3>
        <p class="orcamento-desc">
          Definido uma vez, aplicado automaticamente em todos os meses que não tiverem um valor específico.
        </p>
        <div class="campo">
          <label>Renda mensal (R$)</label>
          <input type="number" inputmode="decimal" id="op-renda" class="input-campo" placeholder="Ex: 3000.00" min="0" step="0.01">
        </div>
        <div class="campo">
          <label>Limite de gastos (R$)</label>
          <input type="number" inputmode="decimal" id="op-limite" class="input-campo" placeholder="Ex: 2000.00" min="0" step="0.01">
        </div>
        <button class="btn-primario btn-full" id="btn-salvar-op">Salvar orçamento padrão</button>
      </div>

      <!-- Override mensal -->
      <div class="orcamento-card">
        <div class="orcamento-badge orcamento-badge-override">🗓️ Exceção mensal</div>
        <h3>Orçamento específico</h3>
        <p class="orcamento-desc">
          Defina um valor diferente para um mês específico. Nos demais meses, o padrão continua valendo.
        </p>
        <div class="campo">
          <label>Mês / Ano</label>
          <div class="campo-linha">
            <select id="orc-mes" class="select-campo">
              <?php for ($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                  <?= ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$m] ?>
                </option>
              <?php endfor; ?>
            </select>
            <select id="orc-ano" class="select-campo">
              <?php for ($a = date('Y'); $a >= date('Y')-2; $a--): ?>
                <option value="<?= $a ?>"><?= $a ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div id="override-info" class="override-info hidden">
          <span class="override-ativo">✓ Este mês tem um valor específico definido</span>
        </div>
        <div class="campo">
          <label>Renda do mês (R$)</label>
          <input type="number" inputmode="decimal" id="orc-renda" class="input-campo" placeholder="Deixe vazio para usar o padrão" min="0" step="0.01">
        </div>
        <div class="campo">
          <label>Limite do mês (R$)</label>
          <input type="number" inputmode="decimal" id="orc-limite" class="input-campo" placeholder="Deixe vazio para usar o padrão" min="0" step="0.01">
        </div>
        <div class="override-botoes">
          <button class="btn-primario btn-full" id="btn-salvar-orc">Salvar para este mês</button>
          <button class="btn-ghost btn-sm" id="btn-limpar-override" style="display:none">🗑️ Remover e usar padrão</button>
        </div>
      </div>

      <!-- Histórico de meses -->
      <div class="orcamento-card orcamento-historico-card">
        <h3>Gastos dos últimos meses</h3>
        <div id="historico-meses-lista" class="historico-meses"></div>
      </div>

    </div>
  </section>

</div><!-- /#app -->

<!-- ============================================================
     NAVEGAÇÃO INFERIOR (somente celular)
============================================================ -->
<nav class="bottom-nav hidden" id="bottom-nav" aria-label="Navegação principal">
  <button class="bnav-btn nav-btn active" data-view="dashboard"><span class="bnav-ico">🏠</span><span>Início</span></button>
  <button class="bnav-btn nav-btn" data-view="historico"><span class="bnav-ico">📋</span><span>Histórico</span></button>
  <button class="bnav-add" id="bnav-add" aria-label="Adicionar despesa">+</button>
  <button class="bnav-btn nav-btn" data-view="orcamento"><span class="bnav-ico">🎯</span><span>Orçamento</span></button>
  <button class="bnav-btn" id="bnav-conta"><span class="bnav-ico">👤</span><span>Conta</span></button>
</nav>

<!-- ============================================================
     MODAL: Chave de recuperação (exibida uma única vez)
============================================================ -->
<div id="modal-chave" class="modal-overlay hidden">
  <div class="modal modal-centro">
    <div class="onb-icone">🔑</div>
    <h2>Guarde sua chave de recuperação</h2>
    <p>Ela é a <strong>única forma</strong> de recuperar sua senha se você esquecê-la. Por segurança, ela é exibida apenas agora.</p>
    <div class="chave-box" id="chave-valor">XXXX-XXXX-XXXX</div>
    <div class="chave-acoes">
      <button type="button" class="btn-secundario" id="btn-copiar-chave">📋 Copiar</button>
      <button type="button" class="btn-secundario" id="btn-baixar-chave">⬇️ Baixar .txt</button>
    </div>
    <label class="chave-confirma">
      <input type="checkbox" id="chave-ok"> <span>Guardei minha chave em um lugar seguro</span>
    </label>
    <button type="button" class="btn-primario btn-full" id="btn-chave-continuar" disabled>Continuar →</button>
  </div>
</div>

<!-- ============================================================
     MODAL: Minha conta
============================================================ -->
<div id="modal-conta" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3>Minha conta</h3>
      <button class="modal-fechar" id="btn-fechar-conta" aria-label="Fechar">&#215;</button>
    </div>

    <div class="conta-perfil">
      <div class="conta-avatar-grande">👤</div>
      <div>
        <div class="conta-nome" id="conta-nome"></div>
        <div class="conta-email" id="conta-email"></div>
      </div>
    </div>

    <div class="conta-secao">
      <h4>🔑 Chave de recuperação</h4>
      <div id="conta-chave-status" class="chave-status"></div>
      <p class="conta-desc">Gerar uma nova chave invalida a anterior. Confirme com sua senha atual.</p>
      <form id="form-nova-chave" class="conta-form">
        <input type="password" id="chave-senha" placeholder="Senha atual" required autocomplete="current-password">
        <button type="submit" class="btn-secundario">Gerar nova chave</button>
      </form>
      <div id="conta-chave-erro" class="msg-erro hidden"></div>
    </div>

    <div class="conta-secao">
      <h4>🔒 Alterar senha</h4>
      <form id="form-alterar-senha" class="conta-form conta-form-col">
        <input type="password" id="senha-atual" placeholder="Senha atual" required autocomplete="current-password">
        <input type="password" id="senha-nova" placeholder="Nova senha (mín. 6 caracteres)" required minlength="6" autocomplete="new-password">
        <button type="submit" class="btn-secundario">Alterar senha</button>
      </form>
      <div id="conta-senha-erro" class="msg-erro hidden"></div>
    </div>
  </div>
</div>

<!-- ============================================================
     MODAL: ONBOARDING (aparece após criar conta)
============================================================ -->
<div id="modal-onboarding" class="modal-overlay hidden">
  <div class="modal modal-onboarding">

    <!-- Step 1: Boas-vindas -->
    <div class="onb-step" id="onb-step-1">
      <div class="onb-icone">🎉</div>
      <h2>Bem-vindo ao MeuBolso!</h2>
      <p>Sua conta foi criada com sucesso. Antes de começar, vamos configurar seu orçamento mensal em 2 passos rápidos.</p>
      <div class="onb-lista">
        <div class="onb-item"><span class="onb-num">1</span><span>Definir sua renda e limite de gastos</span></div>
        <div class="onb-item"><span class="onb-num">2</span><span>Pronto — você pode começar a usar!</span></div>
      </div>
      <button class="btn-primario btn-full" onclick="onbProximo()">Configurar agora →</button>
      <button class="btn-link" onclick="onbPular()">Configurar depois</button>
    </div>

    <!-- Step 2: Orçamento padrão -->
    <div class="onb-step hidden" id="onb-step-2">
      <div class="onb-icone">💵</div>
      <h2>Defina seu orçamento padrão</h2>
      <p>Esses valores serão aplicados automaticamente em todos os meses. Você pode alterar depois.</p>
      <div class="campo">
        <label>Qual é sua renda mensal? (R$)</label>
        <input type="number" inputmode="decimal" id="onb-renda" class="input-campo input-grande" placeholder="Ex: 3000,00" min="0" step="0.01">
      </div>
      <div class="campo">
        <label>Qual é o seu limite de gastos? (R$)</label>
        <input type="number" inputmode="decimal" id="onb-limite" class="input-campo input-grande" placeholder="Ex: 2500,00" min="0" step="0.01">
        <small class="campo-dica">💡 Sugestão: defina um limite um pouco abaixo da sua renda para poupar.</small>
      </div>
      <div id="onb-erro" class="msg-erro hidden"></div>
      <button class="btn-primario btn-full" onclick="onbSalvar()">Salvar e começar →</button>
      <button class="btn-link" onclick="onbPular()">Definir depois</button>
    </div>

    <!-- Step 3: Concluído -->
    <div class="onb-step hidden" id="onb-step-3">
      <div class="onb-icone">✅</div>
      <h2>Tudo configurado!</h2>
      <p>Seu orçamento padrão foi salvo. Agora você pode começar a registrar seus gastos.</p>
      <div class="onb-dicas">
        <div class="onb-dica">📍 Use o botão <strong>"+ Adicionar despesa"</strong> no dashboard para registrar gastos</div>
        <div class="onb-dica">💹 Use <strong>"+ Receita extra"</strong> para registrar renda adicional (freelance, vendas, etc.)</div>
        <div class="onb-dica">🎯 Acesse <strong>"Orçamento"</strong> no menu para ajustar os valores quando precisar</div>
      </div>
      <button class="btn-primario btn-full" onclick="onbFechar()">Ir para o dashboard →</button>
    </div>

  </div>
</div>

<!-- ============================================================
     MODAL: Despesa (adicionar / editar)
============================================================ -->
<div id="modal-despesa" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-titulo">Nova despesa</h3>
      <button class="modal-fechar" id="btn-fechar-modal">&#215;</button>
    </div>
    <form id="form-despesa" autocomplete="off">
      <input type="hidden" id="desp-id">
      <div class="campo">
        <label>Valor (R$) <span class="obrigatorio">*</span></label>
        <input type="number" inputmode="decimal" id="desp-valor" class="input-grande" placeholder="0,00" min="0.01" step="0.01" required>
      </div>
      <div class="campo">
        <label>Descrição <span class="obrigatorio">*</span></label>
        <input type="text" id="desp-descricao" placeholder="Ex: Almoço no restaurante" maxlength="200" required>
      </div>
      <div class="campo">
        <label>Data <span class="obrigatorio">*</span></label>
        <input type="date" id="desp-data" required>
      </div>
      <div class="campo">
        <label>Categoria <span class="obrigatorio">*</span></label>
        <div id="grade-categorias" class="grade-cat"></div>
        <input type="hidden" id="desp-categoria" required>
      </div>
      <div id="modal-erro" class="msg-erro hidden"></div>
      <div class="modal-footer">
        <button type="button" class="btn-secundario" id="btn-cancelar-modal">Cancelar</button>
        <button type="submit" class="btn-primario" id="btn-salvar-modal">Salvar despesa</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     MODAL: Receita avulsa
============================================================ -->
<div id="modal-receita" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3>Nova receita extra</h3>
      <button class="modal-fechar" id="btn-fechar-receita">&#215;</button>
    </div>
    <div class="modal-receita-info">
      💡 Use para registrar renda que não faz parte do seu salário fixo:<br>
      <span>freelance, venda de itens, bônus, presentes em dinheiro, etc.</span>
    </div>
    <form id="form-receita" autocomplete="off">
      <div class="campo">
        <label>Valor recebido (R$) <span class="obrigatorio">*</span></label>
        <input type="number" inputmode="decimal" id="rec-valor" class="input-grande" placeholder="0,00" min="0.01" step="0.01" required>
      </div>
      <div class="campo">
        <label>Descrição <span class="obrigatorio">*</span></label>
        <input type="text" id="rec-descricao" placeholder="Ex: Freelance de design, Venda de celular..." maxlength="200" required>
      </div>
      <div class="campo">
        <label>Data <span class="obrigatorio">*</span></label>
        <input type="date" id="rec-data" required>
      </div>
      <div id="modal-rec-erro" class="msg-erro hidden"></div>
      <div class="modal-footer">
        <button type="button" class="btn-secundario" id="btn-cancelar-receita">Cancelar</button>
        <button type="submit" class="btn-primario">Salvar receita</button>
      </div>
    </form>
    <!-- Lista de receitas do mês -->
    <div class="receitas-lista-wrap" id="receitas-lista-wrap">
      <h4 class="receitas-lista-titulo">Receitas extras deste mês</h4>
      <div id="receitas-lista"></div>
    </div>
  </div>
</div>

<!-- ============================================================
     MODAL: Confirmar exclusão
============================================================ -->
<div id="modal-confirmar" class="modal-overlay hidden">
  <div class="modal modal-pequeno">
    <div class="modal-header"><h3>Confirmar exclusão</h3></div>
    <p class="modal-texto">Deseja realmente excluir este item? Esta ação não pode ser desfeita.</p>
    <div class="modal-footer">
      <button class="btn-secundario" id="btn-cancelar-del">Cancelar</button>
      <button class="btn-perigo"     id="btn-confirmar-del">Excluir</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  window.APP_INITIAL = {
    logado: <?= $logado ? 'true' : 'false' ?>,
    usuario: <?= $logado ? json_encode([
      'id'                  => $_SESSION['usuario_id'],
      'nome'                => $_SESSION['usuario_nome'],
      'email'               => $_SESSION['usuario_email'],
      'onboarding_completo' => $onb_ok,
      'tem_chave'           => $tem_chave,
    ], JSON_UNESCAPED_UNICODE) : 'null' ?>
  };
</script>
<script src="app.js"></script>
</body>
</html>
