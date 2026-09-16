<?php
declare(strict_types=1);

function lerJson(string $caminho): array
{
    return json_decode(file_get_contents($caminho), true);
}

/**
 * Carrega estados e municípios em memória (por requisição) e devolve os mapas
 * de consulta usados pelas demais funções deste arquivo.
 */
function &localizacaoStore(): array
{
    static $store = null;
    if ($store !== null) {
        return $store;
    }

    $ufPorCodigo = [];      // codigo_uf -> "SP"
    $estadoPorUf = [];      // "SP" -> ['uf', 'nome', 'regiao']
    $municipioPorIbge = []; // codigo_ibge -> ['codigoIbge', 'nome', 'uf']
    $ibgePorNomeUf = [];    // "sao paulo|SP" -> [codigo_ibge, ...]
    $municipiosPorUf = [];  // "SP" -> [['codigoIbge', 'nome'], ...]

    $estados = lerJson(DIR_LOCALIZACAO . '/estados/estados.json');
    foreach ($estados as $e) {
        $ufPorCodigo[$e['codigo_uf']] = $e['uf'];
        $estadoPorUf[$e['uf']] = ['uf' => $e['uf'], 'nome' => $e['nome'], 'regiao' => $e['regiao']];
    }

    $municipios = lerJson(DIR_LOCALIZACAO . '/municipios/municipios.json');
    foreach ($municipios as $m) {
        $uf = $ufPorCodigo[$m['codigo_uf']] ?? null;
        if (!$uf) {
            continue;
        }

        $registro = ['codigoIbge' => $m['codigo_ibge'], 'nome' => $m['nome'], 'uf' => $uf];
        $municipioPorIbge[$m['codigo_ibge']] = $registro;

        $chave = normalizar($m['nome']) . '|' . $uf;
        $ibgePorNomeUf[$chave][] = $m['codigo_ibge'];

        $municipiosPorUf[$uf][] = ['codigoIbge' => $m['codigo_ibge'], 'nome' => $m['nome']];
    }

    $collator = new Collator('pt_BR');
    foreach ($municipiosPorUf as &$lista) {
        usort($lista, fn(array $a, array $b): int => $collator->compare($a['nome'], $b['nome']));
    }
    unset($lista);

    $store = compact('ufPorCodigo', 'estadoPorUf', 'municipioPorIbge', 'ibgePorNomeUf', 'municipiosPorUf');
    return $store;
}

/** Carrega estados e municípios em memória. Devolve as contagens carregadas. */
function carregarLocalizacao(): array
{
    $s = localizacaoStore();
    return ['estados' => count($s['estadoPorUf']), 'municipios' => count($s['municipioPorIbge'])];
}

function ufValida(string $uf): bool
{
    return isset(localizacaoStore()['estadoPorUf'][mb_strtoupper($uf)]);
}

function listarEstados(): array
{
    return array_values(localizacaoStore()['estadoPorUf']);
}

function listarMunicipios(string $uf): array
{
    return localizacaoStore()['municipiosPorUf'][mb_strtoupper($uf)] ?? [];
}

/**
 * Resolve o parâmetro `municipio` (código IBGE ou nome) para um registro.
 * Retorna ['ok' => true, 'municipio' => ...] ou ['ok' => false, 'erro' => ..., 'status' => ...].
 */
function resolverMunicipio(string $entrada, ?string $uf): array
{
    $texto = trim($entrada);
    $store = localizacaoStore();

    if (preg_match('/^\d+$/', $texto)) {
        $municipio = $store['municipioPorIbge'][(int) $texto] ?? null;
        if (!$municipio) {
            return ['ok' => false, 'status' => 400, 'erro' => "Código IBGE não encontrado: $texto"];
        }
        if ($uf && $municipio['uf'] !== $uf) {
            return [
                'ok' => false,
                'status' => 400,
                'erro' => "Município {$municipio['nome']} pertence a {$municipio['uf']}, não a $uf.",
            ];
        }
        return ['ok' => true, 'municipio' => $municipio];
    }

    if (!$uf) {
        return ['ok' => false, 'status' => 400, 'erro' => "Informe 'uf' ao buscar município por nome."];
    }

    $codigos = $store['ibgePorNomeUf'][normalizar($texto) . '|' . $uf] ?? [];
    if (count($codigos) === 0) {
        return ['ok' => false, 'status' => 400, 'erro' => 'Município não encontrado para a UF informada.'];
    }
    if (count($codigos) > 1) {
        return [
            'ok' => false,
            'status' => 400,
            'erro' => "Mais de um município chamado \"$texto\" em $uf. Use o código IBGE: " . implode(', ', $codigos) . '.',
        ];
    }
    return ['ok' => true, 'municipio' => $store['municipioPorIbge'][$codigos[0]]];
}
