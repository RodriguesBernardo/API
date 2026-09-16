<?php
declare(strict_types=1);

/** Lê `uf` e `municipio` da query. Retorna ['ok', 'local'] ou ['ok' => false, 'status', 'erro']. */
function lerLocal(array $query): array
{
    $uf = isset($query['uf']) ? mb_strtoupper(trim((string) $query['uf'])) : null;

    if ($uf && !ufValida($uf)) {
        return ['ok' => false, 'status' => 400, 'erro' => "UF inválida: $uf"];
    }

    if (empty($query['municipio'])) {
        return ['ok' => true, 'local' => ['uf' => $uf, 'codigoIbge' => null, 'municipio' => null]];
    }

    $resolvido = resolverMunicipio((string) $query['municipio'], $uf);
    if (!$resolvido['ok']) {
        return $resolvido;
    }

    $municipio = $resolvido['municipio'];
    return [
        'ok' => true,
        'local' => ['uf' => $municipio['uf'], 'codigoIbge' => $municipio['codigoIbge'], 'municipio' => $municipio['nome']],
    ];
}

function erroDeAno(): array
{
    $r = anos();
    return ['status' => 404, 'erro' => "Ano fora do intervalo suportado entre {$r['min']} e {$r['max']}."];
}

function descreverLocal(array $local): array
{
    return ['uf' => $local['uf'], 'municipio' => $local['municipio'], 'codigoIbge' => $local['codigoIbge']];
}

/**
 * Despacha `$method`/`$path` para a rota correspondente, respondendo via `$responder(status, body)`.
 * Retorna true se alguma rota tratou a requisição, false caso contrário (para o front controller
 * devolver 404).
 */
function despachar(string $method, string $path, array $query, callable $responder): bool
{
    if ($method === 'GET' && $path === '/health') {
        $r = anos();
        $responder(200, ['status' => 'ok', 'anoMin' => $r['min'], 'anoMax' => $r['max'], 'anosEmCache' => anosEmCache()]);
        return true;
    }

    if ($method === 'GET' && $path === '/feriado') {
        $data = validarIso($query['data'] ?? null);
        if (!$data) {
            $responder(400, ['erro' => "Parâmetro 'data' inválido. Use YYYY-MM-DD."]);
            return true;
        }
        if (!anoSuportado($data['ano'])) {
            $e = erroDeAno();
            $responder($e['status'], ['erro' => $e['erro']]);
            return true;
        }

        $local = lerLocal($query);
        if (!$local['ok']) {
            $responder($local['status'], ['erro' => $local['erro']]);
            return true;
        }

        $encontrados = feriadosNaData($data['iso'], $data['ano'], $local['local']);
        $feriados = array_values(array_filter($encontrados, fn(array $f) => $f['tipo'] !== 'facultativo'));
        $facultativos = array_values(array_filter($encontrados, fn(array $f) => $f['tipo'] === 'facultativo'));

        $responder(200, [
            'data' => $data['iso'],
            'diaDaSemana' => diaDaSemana($data['iso']),
            'feriado' => count($feriados) > 0,
            'pontoFacultativo' => count($facultativos) > 0,
            'abrangencia' => array_values(array_unique(array_map(fn(array $f) => $f['tipo'], $feriados))),
            'feriados' => $feriados,
            'pontosFacultativos' => $facultativos,
            'local' => descreverLocal($local['local']),
        ]);
        return true;
    }

    if ($method === 'GET' && preg_match('#^/feriados/([^/]+)$#', $path, $m)) {
        $anoTexto = $m[1];
        $ano = (int) $anoTexto;
        if (!preg_match('/^\d{4}$/', $anoTexto) || !anoSuportado($ano)) {
            $e = erroDeAno();
            $responder($e['status'], ['erro' => $e['erro']]);
            return true;
        }

        $local = lerLocal($query);
        if (!$local['ok']) {
            $responder($local['status'], ['erro' => $local['erro']]);
            return true;
        }

        $lista = array_map(
            fn(array $f) => $f + ['diaDaSemana' => diaDaSemana($f['data'])],
            feriadosDoAno($ano, $local['local'])
        );

        $responder(200, ['ano' => $ano, 'local' => descreverLocal($local['local']), 'total' => count($lista), 'feriados' => $lista]);
        return true;
    }

    if ($method === 'GET' && $path === '/estados') {
        $responder(200, listarEstados());
        return true;
    }

    if ($method === 'GET' && $path === '/municipios') {
        $uf = isset($query['uf']) ? mb_strtoupper(trim((string) $query['uf'])) : null;
        if (!$uf) {
            $responder(400, ['erro' => "Parâmetro 'uf' é obrigatório."]);
            return true;
        }
        if (!ufValida($uf)) {
            $responder(400, ['erro' => "UF inválida: $uf"]);
            return true;
        }

        $lista = listarMunicipios($uf);
        $responder(200, ['uf' => $uf, 'total' => count($lista), 'municipios' => $lista]);
        return true;
    }

    return false;
}
