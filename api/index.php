<?php
// API local do Casa Organizada (MySQL via WampServer) — ponto de entrada único.
// O front-end usa esta API quando o backend é "mysql" (veja assets/js/config.js).
//
// Organização:
//   nucleo/   infraestrutura: HTTP, banco, sessão, limites de tentativas, validação, ponte com o invest
//   modulos/  regras de negócio, uma área por arquivo; cada ação é uma função acao*()
//   Os arquivos de nucleo/ e modulos/ não são acessíveis pela web (Apache + guarda CASA_API).
//
// Rotas (?acao=):
//   GET  sessao                 {user} — também usado pelo Projeto invest para validar o login
//   POST login {email, senha}   POST sair     (POST cadastro: desligado, contas são criadas pelo admin)
//   GET  dados                  todas as coleções + lançamentos e vínculos dos objetivos
//   POST inserir | atualizar | excluir {colecao, id?, dados?}
//   POST objetivo_aporte {objetivo_id, tipo: Dinheiro|Investimento, ...}   POST objetivo_vincular
//   GET  invest_resumo          renda e posição por ativo do Projeto invest
//   GET  usuarios               POST usuario_salvar | usuario_excluir   (página Família)
//   POST meta_salvar | meta_excluir   metas de gastos diárias/mensais/anuais (com histórico de limites)

declare(strict_types=1);

const CASA_API = true;

foreach (['http', 'banco', 'sessao', 'limites', 'validacao', 'invest'] as $arquivo) require_once __DIR__ . "/nucleo/$arquivo.php";
foreach (['autenticacao', 'colecoes', 'objetivos', 'usuarios', 'investimentos', 'metas'] as $arquivo) require_once __DIR__ . "/modulos/$arquivo.php";

// acao => [método HTTP, função]
const ROTAS = [
    'sessao' => ['GET', 'acaoSessao'],
    'login' => ['POST', 'acaoLogin'],
    'cadastro' => ['POST', 'acaoCadastro'],
    'sair' => ['POST', 'acaoSair'],
    'dados' => ['GET', 'acaoDados'],
    'inserir' => ['POST', 'acaoInserir'],
    'atualizar' => ['POST', 'acaoAtualizar'],
    'excluir' => ['POST', 'acaoExcluir'],
    'objetivo_aporte' => ['POST', 'acaoObjetivoAporte'],
    'objetivo_vincular' => ['POST', 'acaoObjetivoVincular'],
    'invest_resumo' => ['GET', 'acaoInvestResumo'],
    'usuarios' => ['GET', 'acaoUsuarios'],
    'usuario_salvar' => ['POST', 'acaoUsuarioSalvar'],
    'usuario_excluir' => ['POST', 'acaoUsuarioExcluir'],
    'meta_salvar' => ['POST', 'acaoMetaSalvar'],
    'meta_excluir' => ['POST', 'acaoMetaExcluir'],
];

cabecalhosApi();
iniciarSessao();

$rota = ROTAS[$_GET['acao'] ?? ''] ?? null;
if (!$rota) falhar('Ação inválida.', 404);
[$metodo, $acao] = $rota;
if ($_SERVER['REQUEST_METHOD'] !== $metodo) falhar('Método não permitido.', 405);
if ($metodo === 'POST') exigirMesmaOrigem();

try {
    $acao();
} catch (PDOException $e) {
    error_log('CasaOrganizada API: ' . $e->getMessage());
    if (conexao()->inTransaction()) conexao()->rollBack();
    falhar('Erro no banco de dados. Confira se database/mysql.sql foi importado por completo.', 500);
}
