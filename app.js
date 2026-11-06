'use strict';

// ============================================================
//  ESTADO GLOBAL
// ============================================================
const E = {
  usuario:      null,
  mes:          new Date().getMonth() + 1,
  ano:          new Date().getFullYear(),
  categorias:   [],
  despesas:     [],
  resumo:       null,
  graficoPizza: null,
  graficoLinha: null,
};

const MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// ============================================================
//  UTILITÁRIOS
// ============================================================
const moeda = v => Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const fmtData = d => { if (!d) return ''; const [a,m,dd]=d.split('-'); return `${dd}/${m}/${a}`; };
const escHtml = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function toast(msg, tipo = 'sucesso', dur = 3500) {
  const el = document.getElementById('toast');
  const icons = { sucesso: '✅', info: 'ℹ️', erro: '❌' };
  el.textContent = `${icons[tipo] || ''} ${msg}`;
  el.className = `toast toast-${tipo}`;
  el.classList.remove('hidden', 'sumindo');
  clearTimeout(el._timer);
  el._timer = setTimeout(() => {
    el.classList.add('sumindo');
    setTimeout(() => el.classList.add('hidden'), 300);
  }, dur);
}

async function api(action, method = 'GET', data = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (data) opts.body = JSON.stringify(data);
  const res  = await fetch(`api.php?action=${action}`, opts);
  const json = await res.json();
  if (!res.ok) throw new Error(json.erro || 'Erro desconhecido.');
  return json;
}
async function apiId(action, id, method, data = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (data) opts.body = JSON.stringify(data);
  const res  = await fetch(`api.php?action=${action}&id=${id}`, opts);
  const json = await res.json();
  if (!res.ok) throw new Error(json.erro || 'Erro.');
  return json;
}
async function apiQ(action, params = {}) {
  const qs  = new URLSearchParams(params).toString();
  const res = await fetch(`api.php?action=${action}&${qs}`);
  const json = await res.json();
  if (!res.ok) throw new Error(json.erro || 'Erro.');
  return json;
}

function erroEl(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg || '';
  el.classList.toggle('hidden', !msg);
}

// ============================================================
//  AUTH
// ============================================================
function initAuth() {
  // Abas
  document.querySelectorAll('.auth-tab').forEach(t => {
    t.addEventListener('click', () => {
      document.querySelectorAll('.auth-tab').forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      const qual = t.dataset.tab;
      document.getElementById('form-login').classList.toggle('hidden', qual !== 'login');
      document.getElementById('form-cadastro').classList.toggle('hidden', qual !== 'cadastro');
      document.getElementById('form-recuperar').classList.add('hidden');
      document.getElementById('login-info').classList.add('hidden');
    });
  });

  // Login
  document.getElementById('form-login').addEventListener('submit', async e => {
    e.preventDefault();
    erroEl('login-erro', '');
    try {
      const r = await api('login', 'POST', {
        email: document.getElementById('login-email').value,
        senha: document.getElementById('login-senha').value,
      });
      autenticar(r.usuario);
    } catch(err) { erroEl('login-erro', err.message); }
  });

  // Cadastro
  document.getElementById('form-cadastro').addEventListener('submit', async e => {
    e.preventDefault();
    erroEl('cad-erro', '');
    try {
      const r = await api('registrar', 'POST', {
        nome:  document.getElementById('cad-nome').value,
        email: document.getElementById('cad-email').value,
        senha: document.getElementById('cad-senha').value,
      });
      autenticar(r.usuario, r.chave_recuperacao);
    } catch(err) { erroEl('cad-erro', err.message); }
  });

  // Esqueci senha -> abre o formulário de recuperação por chave
  document.getElementById('btn-esqueci-senha').addEventListener('click', () => {
    document.getElementById('form-login').classList.add('hidden');
    document.getElementById('form-recuperar').classList.remove('hidden');
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('rec-email').value = document.getElementById('login-email').value;
    erroEl('rec-erro', '');
  });
  document.getElementById('btn-voltar-login').addEventListener('click', voltarAoLogin);

  // Máscara XXXX-XXXX-XXXX no campo da chave
  document.getElementById('rec-chave').addEventListener('input', e => {
    e.target.value = formatarChave(e.target.value);
  });

  // Redefinir senha com a chave
  document.getElementById('form-recuperar').addEventListener('submit', async e => {
    e.preventDefault();
    erroEl('rec-erro', '');
    const senha  = document.getElementById('rec-senha').value;
    const senha2 = document.getElementById('rec-senha2').value;
    if (senha !== senha2) { erroEl('rec-erro', 'As senhas não coincidem.'); return; }
    try {
      const r = await api('recuperar_senha', 'POST', {
        email: document.getElementById('rec-email').value,
        chave: document.getElementById('rec-chave').value,
        senha,
      });
      e.target.reset();
      // A chave usada deixa de valer: mostra a nova antes de voltar ao login
      mostrarChave(r.nova_chave, () => {
        voltarAoLogin();
        const info = document.getElementById('login-info');
        info.textContent = 'Senha redefinida com sucesso! Entre com a nova senha.';
        info.classList.remove('hidden');
      }, 'Sua senha foi redefinida! Esta é a sua NOVA chave de recuperação — a anterior não vale mais.');
    } catch(err) { erroEl('rec-erro', err.message); }
  });

  // Logout
  document.getElementById('btn-logout').addEventListener('click', async () => {
    await api('logout', 'POST');
    location.reload();
  });
}

function voltarAoLogin() {
  document.getElementById('form-recuperar').classList.add('hidden');
  document.getElementById('form-login').classList.remove('hidden');
  document.querySelector('.auth-tab[data-tab="login"]').classList.add('active');
}

function mostrarApp() {
  document.getElementById('tela-auth').classList.add('hidden');
  document.getElementById('app').classList.remove('hidden');
  document.getElementById('bottom-nav').classList.remove('hidden');
  document.getElementById('nome-usuario').textContent = E.usuario.nome;
}

function abrirOnboardingSeNecessario() {
  if (!E.usuario.onboarding_completo) {
    document.getElementById('modal-onboarding').classList.remove('hidden');
  } else if (E.usuario.tem_chave === false) {
    toast('Gere sua chave de recuperação em "Minha conta" para poder recuperar a senha.', 'info', 6000);
  }
}

/** chave só vem preenchida logo após criar a conta */
function autenticar(usuario, chave = null) {
  E.usuario = usuario;
  mostrarApp();
  initApp().then(() => {
    if (chave) mostrarChave(chave, abrirOnboardingSeNecessario);
    else abrirOnboardingSeNecessario();
  });
}

// ============================================================
//  CHAVE DE RECUPERAÇÃO
// ============================================================
function formatarChave(v) {
  const limpo = v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
  return limpo.match(/.{1,4}/g)?.join('-') || '';
}

let _chaveAtual = '';
let _chaveCallback = null;

function mostrarChave(chave, callback, mensagem = null) {
  _chaveAtual = chave;
  _chaveCallback = callback || null;
  document.getElementById('chave-valor').textContent = chave;
  document.getElementById('chave-ok').checked = false;
  document.getElementById('btn-chave-continuar').disabled = true;
  const p = document.querySelector('#modal-chave p');
  p.innerHTML = mensagem
    ? escHtml(mensagem)
    : 'Ela é a <strong>única forma</strong> de recuperar sua senha se você esquecê-la. Por segurança, ela é exibida apenas agora.';
  document.getElementById('modal-chave').classList.remove('hidden');
}

async function copiarTexto(txt) {
  try {
    await navigator.clipboard.writeText(txt);
    return true;
  } catch {
    // Fallback para http (fora de localhost/https o clipboard API não existe)
    const ta = document.createElement('textarea');
    ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch {}
    ta.remove();
    return ok;
  }
}

function initChaveModal() {
  document.getElementById('chave-ok').addEventListener('change', e => {
    document.getElementById('btn-chave-continuar').disabled = !e.target.checked;
  });
  document.getElementById('btn-copiar-chave').addEventListener('click', async () => {
    const ok = await copiarTexto(_chaveAtual);
    toast(ok ? 'Chave copiada!' : 'Não foi possível copiar. Anote a chave manualmente.', ok ? 'sucesso' : 'erro');
  });
  document.getElementById('btn-baixar-chave').addEventListener('click', () => {
    const conteudo = `MeuBolso - Chave de recuperação de senha\n\n${_chaveAtual}\n\nGuarde este arquivo em um local seguro. Quem tiver esta chave e o seu e-mail pode redefinir a sua senha.\n`;
    const url = URL.createObjectURL(new Blob([conteudo], { type: 'text/plain;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url; a.download = 'meubolso-chave-recuperacao.txt';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  document.getElementById('btn-chave-continuar').addEventListener('click', () => {
    document.getElementById('modal-chave').classList.add('hidden');
    _chaveAtual = '';
    const cb = _chaveCallback; _chaveCallback = null;
    if (cb) cb();
  });
}

// ============================================================
//  MINHA CONTA
// ============================================================
function abrirConta() {
  document.getElementById('conta-nome').textContent  = E.usuario.nome;
  document.getElementById('conta-email').textContent = E.usuario.email;
  atualizarStatusChave();
  ['chave-senha', 'senha-atual', 'senha-nova'].forEach(id => document.getElementById(id).value = '');
  erroEl('conta-chave-erro', ''); erroEl('conta-senha-erro', '');
  document.getElementById('modal-conta').classList.remove('hidden');
}
function fecharConta() { document.getElementById('modal-conta').classList.add('hidden'); }

function atualizarStatusChave() {
  const el = document.getElementById('conta-chave-status');
  const tem = E.usuario.tem_chave !== false;
  el.className = 'chave-status ' + (tem ? 'ok' : 'falta');
  el.textContent = tem ? '✓ Você possui uma chave ativa' : '⚠ Você ainda não tem chave de recuperação';
}

function initConta() {
  document.getElementById('btn-conta').addEventListener('click', abrirConta);
  document.getElementById('bnav-conta').addEventListener('click', abrirConta);
  document.getElementById('btn-fechar-conta').addEventListener('click', fecharConta);
  document.getElementById('modal-conta').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-conta')) fecharConta();
  });

  document.getElementById('form-nova-chave').addEventListener('submit', async e => {
    e.preventDefault();
    erroEl('conta-chave-erro', '');
    try {
      const r = await api('chave_gerar', 'POST', { senha: document.getElementById('chave-senha').value });
      E.usuario = r.usuario;
      document.getElementById('chave-senha').value = '';
      atualizarStatusChave();
      fecharConta();
      mostrarChave(r.chave, null, 'Nova chave gerada! A chave anterior não vale mais. Guarde esta com cuidado.');
    } catch(err) { erroEl('conta-chave-erro', err.message); }
  });

  document.getElementById('form-alterar-senha').addEventListener('submit', async e => {
    e.preventDefault();
    erroEl('conta-senha-erro', '');
    try {
      await api('senha_alterar', 'POST', {
        senha_atual: document.getElementById('senha-atual').value,
        senha_nova:  document.getElementById('senha-nova').value,
      });
      e.target.reset();
      fecharConta();
      toast('Senha alterada com sucesso! 🔒', 'sucesso');
    } catch(err) { erroEl('conta-senha-erro', err.message); }
  });
}

// ============================================================
//  ONBOARDING
// ============================================================
let _onbStep = 1;

window.onbProximo = function() {
  document.getElementById('onb-step-1').classList.add('hidden');
  document.getElementById('onb-step-2').classList.remove('hidden');
  _onbStep = 2;
};

window.onbPular = async function() {
  // Marca como completo sem salvar orçamento
  try { await api('onboarding_concluir', 'POST', { limite: 0, renda: 0 }); } catch {}
  document.getElementById('modal-onboarding').classList.add('hidden');
  toast('Bem-vindo! Lembre de configurar seu orçamento.', 'info');
};

window.onbSalvar = async function() {
  erroEl('onb-erro', '');
  const limite = parseFloat(document.getElementById('onb-limite').value) || 0;
  const renda  = parseFloat(document.getElementById('onb-renda').value)  || 0;
  if (!renda && !limite) {
    erroEl('onb-erro', 'Informe pelo menos um dos valores para continuar.');
    return;
  }
  try {
    await api('onboarding_concluir', 'POST', { limite, renda });
    document.getElementById('onb-step-2').classList.add('hidden');
    document.getElementById('onb-step-3').classList.remove('hidden');
    await carregarDashboard();
  } catch(err) { erroEl('onb-erro', err.message); }
};

window.onbFechar = function() {
  document.getElementById('modal-onboarding').classList.add('hidden');
  toast('Configuração concluída! Bom controle financeiro 💰', 'sucesso');
};

// ============================================================
//  NAVEGAÇÃO
// ============================================================
function initNav() {
  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
      const view = btn.dataset.view;
      document.querySelectorAll(`.nav-btn[data-view="${view}"]`).forEach(b => b.classList.add('active'));
      window.scrollTo(0, 0);
      document.querySelectorAll('.view').forEach(v => v.classList.remove('ativa'));
      document.getElementById(`view-${view}`).classList.add('ativa');
      if (view === 'historico')  carregarHistorico();
      if (view === 'orcamento')  carregarOrcamento();
    });
  });
}

function irParaHistorico() {
  const btn = document.querySelector('.nav-btn[data-view="historico"]');
  if (btn) btn.click();
}

// ============================================================
//  DASHBOARD
// ============================================================
function initMesNav() {
  document.getElementById('btn-mes-ant').addEventListener('click', () => {
    E.mes--; if (E.mes < 1) { E.mes = 12; E.ano--; }
    carregarDashboard();
  });
  document.getElementById('btn-mes-prox').addEventListener('click', () => {
    E.mes++; if (E.mes > 12) { E.mes = 1; E.ano++; }
    carregarDashboard();
  });
  document.getElementById('btn-ver-historico-completo')
    .addEventListener('click', irParaHistorico);
}

async function carregarDashboard() {
  document.getElementById('mes-titulo').textContent = `${MESES[E.mes]} ${E.ano}`;
  try {
    const [resumo, listaDeps] = await Promise.all([
      apiQ('resumo', { mes: E.mes, ano: E.ano }),
      apiQ('despesas', { mes: E.mes, ano: E.ano }),
    ]);
    E.resumo   = resumo;
    E.despesas = listaDeps.despesas;

    atualizarCards(resumo);
    atualizarProgresso(resumo);
    atualizarAlerta(resumo.alerta);
    renderGraficoPizza(resumo.por_categoria);
    renderGraficoLinha(resumo.evolucao, E.mes, E.ano);
    renderListaDash(E.despesas.slice(0, 8));
  } catch(err) {
    console.error(err);
    toast('Erro ao carregar dados: ' + err.message, 'erro');
  }
}

function atualizarCards(r) {
  document.getElementById('card-total').textContent  = moeda(r.total_gasto);
  document.getElementById('card-limite').textContent = moeda(r.limite);
  document.getElementById('card-renda').textContent  = moeda(r.renda_total);

  const saldo = r.saldo;
  const el = document.getElementById('card-saldo');
  el.textContent = moeda(saldo);
  el.closest('.card-saldo').classList.toggle('negativo', saldo < 0);

  // Card receitas avulsas (só aparece se houver)
  const cardRec = document.getElementById('card-receita-wrap');
  document.getElementById('cards-grid').classList.toggle('sem-receita', !(r.total_receitas_avulsas > 0));
  if (r.total_receitas_avulsas > 0) {
    document.getElementById('card-receitas-avulsas').textContent = moeda(r.total_receitas_avulsas);
    cardRec.style.display = '';
  } else {
    cardRec.style.display = 'none';
  }
}

function atualizarProgresso(r) {
  const wrap = document.getElementById('progresso-wrap');
  if (!r.limite || r.limite <= 0) { wrap.style.display = 'none'; return; }
  wrap.style.display = '';
  const pct  = Math.min(r.pct_limite || 0, 100);
  document.getElementById('progresso-pct').textContent = `${r.pct_limite || 0}%`;
  const fill = document.getElementById('progresso-fill');
  fill.style.width      = `${pct}%`;
  fill.style.background = pct >= 100 ? 'var(--vermelho)' : pct >= 80 ? 'var(--amarelo)' : 'var(--verde-mid)';
}

function atualizarAlerta(alerta) {
  const el = document.getElementById('alerta-global');
  if (!alerta) { el.classList.add('hidden'); return; }
  el.className = `alerta alerta-${alerta.tipo}`;
  el.innerHTML = `⚠️ ${alerta.mensagem}`;
}

function renderListaDash(deps) {
  const el = document.getElementById('lista-despesas-dash');
  if (!deps.length) {
    el.innerHTML = `<div class="estado-vazio"><span class="vazio-icone">📭</span><p>Nenhum gasto registrado neste mês.</p></div>`;
    return;
  }
  el.innerHTML = deps.map(d => itemHTML(d)).join('');
  bindItemEvents(el);
}

function itemHTML(d) {
  return `
  <div class="item-despesa">
    <div class="item-icone" style="background:${d.categoria_cor}22">${d.categoria_icone}</div>
    <div class="item-info">
      <div class="item-desc">${escHtml(d.descricao)}</div>
      <div class="item-cat-data">${escHtml(d.categoria_nome)} · ${fmtData(d.data)}</div>
    </div>
    <div class="item-valor">-${moeda(d.valor)}</div>
    <div class="item-acoes">
      <button class="btn-tabela btn-editar" data-id="${d.id}" title="Editar">✏️</button>
      <button class="btn-tabela btn-excluir" data-id="${d.id}" title="Excluir">🗑️</button>
    </div>
  </div>`;
}

function bindItemEvents(container) {
  container.querySelectorAll('.btn-editar').forEach(b =>
    b.addEventListener('click', () => abrirEditar(Number(b.dataset.id)))
  );
  container.querySelectorAll('.btn-excluir').forEach(b =>
    b.addEventListener('click', () => confirmarDel(Number(b.dataset.id), 'despesa'))
  );
}

// ============================================================
//  GRÁFICOS
// ============================================================
function renderGraficoPizza(pCat) {
  const canvas = document.getElementById('grafico-pizza');
  if (E.graficoPizza) { E.graficoPizza.destroy(); E.graficoPizza = null; }
  const legEl = document.getElementById('legenda-categorias');
  if (!pCat?.length) {
    legEl.innerHTML = '<span style="color:var(--texto-3);font-size:13px">Nenhum dado disponível</span>';
    return;
  }
  const total = pCat.reduce((s, c) => s + c.total, 0);
  E.graficoPizza = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: pCat.map(c => c.nome),
      datasets: [{ data: pCat.map(c => c.total), backgroundColor: pCat.map(c => c.cor), borderWidth: 2, borderColor: '#fff' }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${moeda(ctx.parsed)}` } } },
    },
  });
  legEl.innerHTML = pCat.map(c =>
    `<span class="legenda-item"><span class="legenda-cor" style="background:${c.cor}"></span>${c.icone} ${escHtml(c.nome)} ${((c.total/total)*100).toFixed(1)}%</span>`
  ).join('');
}

function renderGraficoLinha(evolucao, mes, ano) {
  const canvas = document.getElementById('grafico-linha');
  if (E.graficoLinha) { E.graficoLinha.destroy(); E.graficoLinha = null; }
  if (!evolucao?.length) return;
  const diasNoMes = new Date(ano, mes, 0).getDate();
  const mapa = Object.fromEntries(evolucao.map(e => [e.dia, e.acumulado]));
  const labels = []; const dados = []; let ultimo = 0;
  for (let d = 1; d <= diasNoMes; d++) {
    labels.push(d);
    if (mapa[d] !== undefined) ultimo = mapa[d];
    dados.push(ultimo);
  }
  E.graficoLinha = new Chart(canvas, {
    type: 'line',
    data: { labels, datasets: [{ label: 'Acumulado', data: dados, borderColor: '#1D9E75', backgroundColor: 'rgba(29,158,117,.1)', fill: true, tension: .35, pointRadius: 2, pointHoverRadius: 5 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${moeda(ctx.parsed.y)}` } } },
      scales: {
        x: { ticks: { font: { size: 11 }, color: '#888780', maxTicksLimit: 10 } },
        y: { ticks: { callback: v => `R$${(v/1000).toFixed(1)}k`, font: { size: 11 }, color: '#888780' } },
      },
    },
  });
}

// ============================================================
//  MODAL DESPESA
// ============================================================
let _editMode = false;

function initModalDespesa() {
  document.getElementById('btn-add-dash').addEventListener('click', abrirNovo);
  document.getElementById('btn-fechar-modal').addEventListener('click', fecharModal);
  document.getElementById('btn-cancelar-modal').addEventListener('click', fecharModal);
  document.getElementById('modal-despesa').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-despesa')) fecharModal();
  });
  document.getElementById('form-despesa').addEventListener('submit', salvarDespesa);
}

function abrirNovo() {
  _editMode = false;
  document.getElementById('modal-titulo').textContent = 'Nova despesa';
  document.getElementById('btn-salvar-modal').textContent = 'Salvar despesa';
  document.getElementById('desp-id').value = '';
  document.getElementById('desp-valor').value = '';
  document.getElementById('desp-descricao').value = '';
  document.getElementById('desp-data').value = new Date().toISOString().split('T')[0];
  document.getElementById('desp-categoria').value = '';
  erroEl('modal-erro', '');
  renderGradeCat(null);
  document.getElementById('modal-despesa').classList.remove('hidden');
  setTimeout(() => document.getElementById('desp-valor').focus(), 100);
}

function abrirEditar(id) {
  const d = E.despesas.find(x => x.id === id);
  if (!d) { toast('Despesa não encontrada.', 'erro'); return; }
  _editMode = true;
  document.getElementById('modal-titulo').textContent = 'Editar despesa';
  document.getElementById('btn-salvar-modal').textContent = 'Atualizar despesa';
  document.getElementById('desp-id').value = d.id;
  document.getElementById('desp-valor').value = d.valor;
  document.getElementById('desp-descricao').value = d.descricao;
  document.getElementById('desp-data').value = d.data;
  document.getElementById('desp-categoria').value = d.categoria_id;
  erroEl('modal-erro', '');
  renderGradeCat(d.categoria_id);
  document.getElementById('modal-despesa').classList.remove('hidden');
}

function fecharModal() {
  document.getElementById('modal-despesa').classList.add('hidden');
}

function renderGradeCat(selId) {
  const grade = document.getElementById('grade-categorias');
  grade.innerHTML = E.categorias.map(c => `
    <button type="button" class="cat-opcao ${selId == c.id ? 'selecionada' : ''}" data-id="${c.id}">
      <span class="cat-emoji">${c.icone}</span>${escHtml(c.nome)}
    </button>`).join('');
  grade.querySelectorAll('.cat-opcao').forEach(btn => {
    btn.addEventListener('click', () => {
      grade.querySelectorAll('.cat-opcao').forEach(b => b.classList.remove('selecionada'));
      btn.classList.add('selecionada');
      document.getElementById('desp-categoria').value = btn.dataset.id;
    });
  });
}

async function salvarDespesa(e) {
  e.preventDefault();
  erroEl('modal-erro', '');
  const dados = {
    valor:        parseFloat(document.getElementById('desp-valor').value),
    descricao:    document.getElementById('desp-descricao').value,
    data:         document.getElementById('desp-data').value,
    categoria_id: parseInt(document.getElementById('desp-categoria').value),
  };
  if (!dados.categoria_id) { erroEl('modal-erro', 'Selecione uma categoria.'); return; }
  try {
    let r;
    if (_editMode) {
      const id = parseInt(document.getElementById('desp-id').value);
      r = await apiId('despesa_edit', id, 'PUT', dados);
      toast('Despesa atualizada com sucesso!', 'sucesso');
    } else {
      r = await api('despesa_add', 'POST', dados);
      toast('Despesa registrada com sucesso!', 'sucesso');
      if (r.alerta) setTimeout(() => toast(`⚠️ ${r.alerta.mensagem}`, 'info', 5000), 1000);
    }
    fecharModal();
    await carregarDashboard();
    if (document.getElementById('view-historico').classList.contains('ativa')) carregarHistorico();
  } catch(err) { erroEl('modal-erro', err.message); }
}

// ============================================================
//  RECEITAS AVULSAS
// ============================================================
function initModalReceita() {
  document.getElementById('btn-add-receita').addEventListener('click', abrirModalReceita);
  document.getElementById('btn-fechar-receita').addEventListener('click', fecharModalReceita);
  document.getElementById('btn-cancelar-receita').addEventListener('click', fecharModalReceita);
  document.getElementById('modal-receita').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-receita')) fecharModalReceita();
  });
  document.getElementById('form-receita').addEventListener('submit', salvarReceita);
}

async function abrirModalReceita() {
  document.getElementById('rec-valor').value = '';
  document.getElementById('rec-descricao').value = '';
  document.getElementById('rec-data').value = new Date().toISOString().split('T')[0];
  erroEl('modal-rec-erro', '');
  document.getElementById('modal-receita').classList.remove('hidden');
  await carregarListaReceitas();
  setTimeout(() => document.getElementById('rec-valor').focus(), 100);
}

function fecharModalReceita() {
  document.getElementById('modal-receita').classList.add('hidden');
}

async function carregarListaReceitas() {
  try {
    const r = await apiQ('receitas', { mes: E.mes, ano: E.ano });
    const lista = document.getElementById('receitas-lista');
    const wrap  = document.getElementById('receitas-lista-wrap');
    if (!r.receitas.length) {
      lista.innerHTML = '<p style="color:var(--texto-3);font-size:13px;padding:8px 0">Nenhuma receita extra neste mês.</p>';
    } else {
      lista.innerHTML = r.receitas.map(rc => `
        <div class="receita-item">
          <div class="receita-item-info">
            <div class="receita-item-desc">${escHtml(rc.descricao)}</div>
            <div class="receita-item-data">${fmtData(rc.data)}</div>
          </div>
          <div class="receita-item-val">+${moeda(rc.valor)}</div>
          <button class="btn-tabela btn-del-receita" data-id="${rc.id}" title="Remover">🗑️</button>
        </div>`).join('');
      lista.querySelectorAll('.btn-del-receita').forEach(b =>
        b.addEventListener('click', () => confirmarDel(Number(b.dataset.id), 'receita'))
      );
    }
    wrap.style.display = '';
  } catch {}
}

async function salvarReceita(e) {
  e.preventDefault();
  erroEl('modal-rec-erro', '');
  const dados = {
    valor:     parseFloat(document.getElementById('rec-valor').value),
    descricao: document.getElementById('rec-descricao').value,
    data:      document.getElementById('rec-data').value,
  };
  try {
    await api('receita_add', 'POST', dados);
    toast('Receita extra registrada com sucesso! 💹', 'sucesso');
    document.getElementById('form-receita').reset();
    document.getElementById('rec-data').value = new Date().toISOString().split('T')[0];
    await carregarListaReceitas();
    await carregarDashboard();
  } catch(err) { erroEl('modal-rec-erro', err.message); }
}

// ============================================================
//  EXCLUSÃO UNIFICADA (despesas e receitas)
// ============================================================
let _delId   = null;
let _delTipo = null;

function confirmarDel(id, tipo) {
  _delId   = id;
  _delTipo = tipo;
  document.getElementById('modal-confirmar').classList.remove('hidden');
}

function initConfirmarDel() {
  document.getElementById('btn-cancelar-del').addEventListener('click', () => {
    document.getElementById('modal-confirmar').classList.add('hidden');
    _delId = null; _delTipo = null;
  });
  document.getElementById('btn-confirmar-del').addEventListener('click', async () => {
    if (!_delId) return;
    try {
      if (_delTipo === 'despesa') {
        await apiId('despesa_del', _delId, 'DELETE');
        toast('Despesa removida.', 'info');
      } else {
        await apiId('receita_del', _delId, 'DELETE');
        toast('Receita removida.', 'info');
        await carregarListaReceitas();
      }
      document.getElementById('modal-confirmar').classList.add('hidden');
      _delId = null; _delTipo = null;
      await carregarDashboard();
      if (document.getElementById('view-historico').classList.contains('ativa')) carregarHistorico();
    } catch(err) { toast('Erro: ' + err.message, 'erro'); }
  });
}

// ============================================================
//  HISTÓRICO
// ============================================================
async function carregarHistorico() {
  const mes = document.getElementById('filtro-mes').value;
  const ano = document.getElementById('filtro-ano').value;
  const cat = document.getElementById('filtro-cat').value;
  try {
    const r   = await apiQ('despesas', { mes, ano });
    let deps  = r.despesas;
    if (cat) deps = deps.filter(d => d.categoria_id == cat);
    E.despesas = deps; // atualiza ref para edição
    renderTabela(deps);
  } catch(err) { toast('Erro: ' + err.message, 'erro'); }
}

function renderTabela(deps) {
  const body  = document.getElementById('tabela-body');
  const total = deps.reduce((s, d) => s + d.valor, 0);
  document.getElementById('tabela-total-txt').textContent =
    `Total: ${moeda(total)} (${deps.length} registro${deps.length !== 1 ? 's' : ''})`;
  if (!deps.length) {
    body.innerHTML = '<tr><td colspan="5" class="tabela-vazio">Nenhuma despesa encontrada.</td></tr>';
    return;
  }
  body.innerHTML = deps.map(d => `
    <tr class="linha-despesa">
      <td>${fmtData(d.data)}</td>
      <td>${escHtml(d.descricao)}</td>
      <td><span class="badge-cat" style="background:${d.categoria_cor}22;color:${d.categoria_cor}">${d.categoria_icone} ${escHtml(d.categoria_nome)}</span></td>
      <td class="col-valor valor-vermelho">-${moeda(d.valor)}</td>
      <td class="col-acoes">
        <button class="btn-tabela btn-editar" data-id="${d.id}">✏️</button>
        <button class="btn-tabela btn-excluir" data-id="${d.id}">🗑️</button>
      </td>
    </tr>`).join('');
  body.querySelectorAll('.btn-editar').forEach(b =>
    b.addEventListener('click', () => abrirEditar(Number(b.dataset.id)))
  );
  body.querySelectorAll('.btn-excluir').forEach(b =>
    b.addEventListener('click', () => confirmarDel(Number(b.dataset.id), 'despesa'))
  );
}

function preencherFiltrosCat() {
  const sel = document.getElementById('filtro-cat');
  E.categorias.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.textContent = `${c.icone} ${c.nome}`;
    sel.appendChild(opt);
  });
}

// ============================================================
//  ORÇAMENTO
// ============================================================
async function carregarOrcamento() {
  // Carrega orçamento padrão
  try {
    const op = await api('orcamento_padrao_get');
    document.getElementById('op-limite').value = op.limite || '';
    document.getElementById('op-renda').value  = op.renda  || '';
  } catch {}

  // Carrega override do mês selecionado
  await carregarOverrideMes();

  // Histórico de meses
  try {
    const h = await api('historico');
    renderHistoricoMeses(h.meses);
  } catch {}
}

async function carregarOverrideMes() {
  const mes = document.getElementById('orc-mes').value;
  const ano = document.getElementById('orc-ano').value;
  try {
    const r = await apiQ('orcamento', { mes, ano });
    if (r.override) {
      document.getElementById('orc-limite').value = r.limite || '';
      document.getElementById('orc-renda').value  = r.renda  || '';
      document.getElementById('override-info').classList.remove('hidden');
      document.getElementById('btn-limpar-override').style.display = '';
    } else {
      document.getElementById('orc-limite').value = '';
      document.getElementById('orc-renda').value  = '';
      document.getElementById('override-info').classList.add('hidden');
      document.getElementById('btn-limpar-override').style.display = 'none';
    }
  } catch {}
}

function renderHistoricoMeses(meses) {
  const el = document.getElementById('historico-meses-lista');
  if (!meses.length) { el.innerHTML = '<p style="color:var(--texto-3);font-size:14px">Nenhum histórico disponível.</p>'; return; }
  el.innerHTML = meses.map(m => `
    <div class="hist-item">
      <div>
        <div class="hist-item-mes">${MESES[m.mes]} ${m.ano}</div>
        <div class="hist-item-qtd">${m.qtd_despesas} despesa${m.qtd_despesas != 1 ? 's' : ''}</div>
      </div>
      <div class="hist-item-val">-${moeda(m.total_gasto)}</div>
    </div>`).join('');
}

function initOrcamento() {
  // Salvar padrão
  document.getElementById('btn-salvar-op').addEventListener('click', async () => {
    const dados = {
      limite: parseFloat(document.getElementById('op-limite').value) || 0,
      renda:  parseFloat(document.getElementById('op-renda').value)  || 0,
    };
    try {
      await api('orcamento_padrao_set', 'POST', dados);
      toast('Orçamento padrão salvo com sucesso! ⭐', 'sucesso');
      await carregarDashboard();
    } catch(err) { toast('Erro: ' + err.message, 'erro'); }
  });

  // Salvar override mensal
  document.getElementById('btn-salvar-orc').addEventListener('click', async () => {
    const dados = {
      mes:    parseInt(document.getElementById('orc-mes').value),
      ano:    parseInt(document.getElementById('orc-ano').value),
      limite: parseFloat(document.getElementById('orc-limite').value) || 0,
      renda:  parseFloat(document.getElementById('orc-renda').value)  || 0,
    };
    try {
      await api('orcamento_set', 'POST', dados);
      toast('Orçamento específico do mês salvo! 🗓️', 'sucesso');
      await carregarOverrideMes();
      if (dados.mes === E.mes && dados.ano === E.ano) await carregarDashboard();
    } catch(err) { toast('Erro: ' + err.message, 'erro'); }
  });

  // Limpar override
  document.getElementById('btn-limpar-override').addEventListener('click', async () => {
    const mes = parseInt(document.getElementById('orc-mes').value);
    const ano = parseInt(document.getElementById('orc-ano').value);
    try {
      const res = await fetch(`api.php?action=orcamento_limpar&mes=${mes}&ano=${ano}`, { method: 'DELETE' });
      const json = await res.json();
      toast(json.mensagem || 'Override removido.', 'info');
      await carregarOverrideMes();
      if (mes === E.mes && ano === E.ano) await carregarDashboard();
    } catch(err) { toast('Erro: ' + err.message, 'erro'); }
  });

  // Recarregar ao mudar mês/ano
  ['orc-mes', 'orc-ano'].forEach(id =>
    document.getElementById(id).addEventListener('change', carregarOverrideMes)
  );
}

// ============================================================
//  INICIALIZAÇÃO DO APP
// ============================================================
async function initApp() {
  try {
    const r = await api('categorias');
    E.categorias = r;
    preencherFiltrosCat();
  } catch(err) { console.error('Categorias:', err); }

  initNav();
  initMesNav();
  initModalDespesa();
  initModalReceita();
  initConfirmarDel();
  initOrcamento();
  initConta();
  document.getElementById('bnav-add').addEventListener('click', abrirNovo);

  document.getElementById('btn-filtrar').addEventListener('click', carregarHistorico);

  await carregarDashboard();
}

// ============================================================
//  BOOT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  initAuth();
  initChaveModal(); // precisa funcionar antes do login (fluxo "esqueci minha senha")
  if (window.APP_INITIAL?.logado) {
    E.usuario = window.APP_INITIAL.usuario;
    mostrarApp();
    initApp().then(abrirOnboardingSeNecessario);
  }
});
