<?php
// Validações reutilizáveis. Todas falham com 422 e uma mensagem pronta para mostrar.
defined('CASA_API') || exit;

const PADRAO_UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

/**
 * Valida o payload contra as regras de uma coleção (veja COLECOES) e devolve os campos já
 * convertidos. Regras: tipo (s texto, f decimal, i inteiro, b booleano, d data AAAA-MM-DD),
 * obrig, max (tamanho do texto), min/maxv (faixa numérica), opcoes (valores aceitos).
 * $novo = criação (exige os obrigatórios); em atualizações valida só o que veio.
 * Campos fora da lista são ignorados.
 */
function validarCampos(array $dados, array $colunas, bool $novo, array $nomes = []): array
{
    $campos = [];
    foreach ($colunas as $coluna => $regra) {
        $nome = $nomes[$coluna] ?? $coluna;
        if (!array_key_exists($coluna, $dados)) {
            if ($novo && !empty($regra['obrig'])) falhar("Preencha o campo $nome.", 422);
            continue;
        }
        $valor = is_string($dados[$coluna]) ? trim($dados[$coluna]) : $dados[$coluna];
        if ($valor === null || $valor === '') {
            if (!empty($regra['obrig'])) falhar("Preencha o campo $nome.", 422);
            $campos[$coluna] = null;
            continue;
        }
        if (is_array($valor)) falhar("Valor inválido em $nome.", 422);
        $campos[$coluna] = match ($regra[0]) {
            'f', 'i' => numeroNaFaixa($valor, $regra, $nome),
            'b' => booleano($valor, $nome),
            'd' => data((string)$valor, $nome),
            default => texto((string)$valor, $regra, $nome),
        };
    }
    return $campos;
}

function numeroNaFaixa($valor, array $regra, string $nome)
{
    if (is_bool($valor) || !is_numeric($valor)) falhar("O campo $nome precisa ser um número.", 422);
    if ($regra[0] === 'i' && floor((float)$valor) != (float)$valor) falhar("O campo $nome precisa ser um número inteiro.", 422);
    $numero = $regra[0] === 'i' ? (int)$valor : round((float)$valor, 2);
    if (isset($regra['min']) && $numero < $regra['min']) falhar("O campo $nome precisa ser no mínimo {$regra['min']}.", 422);
    if (isset($regra['maxv']) && $numero > $regra['maxv']) falhar("O campo $nome pode ser no máximo {$regra['maxv']}.", 422);
    return $numero;
}

function booleano($valor, string $nome): int
{
    if (!is_bool($valor) && !in_array($valor, [0, 1, '0', '1'], true)) falhar("Valor inválido em $nome.", 422);
    return $valor ? 1 : 0;
}

function data(string $valor, string $nome = 'data', bool $permiteFuturo = true): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $valor);
    if (!$d || $d->format('Y-m-d') !== $valor) falhar("Data inválida em $nome (use AAAA-MM-DD).", 422);
    if ($valor < '2000-01-01' || $valor > '2100-12-31') falhar("A data em $nome precisa estar entre 2000 e 2100.", 422);
    if (!$permiteFuturo && $valor > date('Y-m-d')) falhar('A data do lançamento não pode ser no futuro.', 422);
    return $valor;
}

function texto(string $valor, array $regra, string $nome): string
{
    if (!mb_check_encoding($valor, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor)) {
        falhar("O campo $nome tem caracteres inválidos.", 422);
    }
    if (isset($regra['max']) && mb_strlen($valor) > $regra['max']) falhar("O campo $nome aceita no máximo {$regra['max']} caracteres.", 422);
    if (isset($regra['opcoes']) && !in_array($valor, $regra['opcoes'], true)) {
        falhar("Valor inválido em $nome. Use: " . implode(', ', $regra['opcoes']) . '.', 422);
    }
    return $valor;
}

function uuidValido($id, string $oQue = 'Registro'): string
{
    $id = (string)$id;
    if (!preg_match(PADRAO_UUID, $id)) falhar("$oQue inválido.", 422);
    return $id;
}

function numeroPositivo($valor, string $nome, float $max = 9999999999): float
{
    if (is_bool($valor) || !is_numeric($valor) || (float)$valor <= 0) falhar("Informe $nome maior que zero.", 422);
    if ((float)$valor > $max) falhar("O valor de $nome é alto demais.", 422);
    return (float)$valor;
}

function tickerValido($ticker): string
{
    $ticker = strtoupper(trim((string)$ticker));
    if (!preg_match('/^[A-Z0-9]{4,8}$/', $ticker)) falhar('Ticker inválido (ex.: TAEE11, VALE3).', 422);
    return $ticker;
}

function emailValido($email): string
{
    $email = trim(strtolower((string)$email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) falhar('Informe um e-mail válido.', 422);
    return $email;
}

function nomeValido($nome): string
{
    $nome = trim((string)$nome);
    if ($nome === '' || mb_strlen($nome) > 100) falhar('Informe o nome (até 100 caracteres).', 422);
    return texto($nome, [], 'nome');
}

/** Senha forte o bastante para entrar de fora de casa: 10+ caracteres, com letras e números. */
function senhaForte(string $senha): bool
{
    return strlen($senha) >= 10 && preg_match('/[A-Za-z]/', $senha) && preg_match('/[0-9]/', $senha);
}

/** Política de senha para contas novas e trocas de senha (a mesma exigida de fora de casa). */
function senhaValida($senha): string
{
    $senha = (string)$senha;
    if (strlen($senha) < 10) falhar('A senha precisa ter pelo menos 10 caracteres.', 422);
    if (strlen($senha) > 200) falhar('Senha longa demais.', 422);
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/[0-9]/', $senha)) falhar('A senha precisa ter letras e números.', 422);
    return $senha;
}
