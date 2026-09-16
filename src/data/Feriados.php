<?php
declare(strict_types=1);

const TIPOS_FERIADO = ['nacional', 'estadual', 'municipal', 'facultativo'];

/** Anos com arquivo em todos os tipos — lido do disco uma vez por requisição. */
function anos(): array
{
    static $anosDisponiveis = null;
    if ($anosDisponiveis !== null) {
        return $anosDisponiveis;
    }

    $porTipo = [];
    foreach (TIPOS_FERIADO as $tipo) {
        $dir = DIR_FERIADOS . "/$tipo/json";
        $anosDoTipo = [];
        foreach (scandir($dir) as $arquivo) {
            if (preg_match('/^(\d{4})\.json$/', $arquivo, $m)) {
                $anosDoTipo[(int) $m[1]] = true;
            }
        }
        $porTipo[] = $anosDoTipo;
    }

    $lista = array_values(array_filter(
        array_keys($porTipo[0]),
        function (int $ano) use ($porTipo): bool {
            foreach ($porTipo as $set) {
                if (!isset($set[$ano])) {
                    return false;
                }
            }
            return true;
        }
    ));
    sort($lista);

    $anosDisponiveis = ['lista' => $lista, 'min' => $lista[0], 'max' => $lista[count($lista) - 1]];
    return $anosDisponiveis;
}

function anoSuportado(int $ano): bool
{
    $r = anos();
    return $ano >= $r['min'] && $ano <= $r['max'];
}

function lerTipo(string $tipo, int $ano): array
{
    $arquivo = DIR_FERIADOS . "/$tipo/json/$ano.json";
    if (!is_file($arquivo)) {
        return [];
    }
    return json_decode(file_get_contents($arquivo), true);
}

/** Cache de índices por ano, compartilhada entre indicePorAno() e anosEmCache(). */
function &indicePorAnoCache(): array
{
    static $cache = [];
    return $cache;
}

/**
 * Índice de um ano: array["YYYY-MM-DD" => Feriado[]].
 * Monta na primeira chamada e guarda em cache (por requisição).
 */
function indicePorAno(int $ano): array
{
    $cache = &indicePorAnoCache();
    if (isset($cache[$ano])) {
        return $cache[$ano];
    }

    $indice = [];

    foreach (TIPOS_FERIADO as $tipo) {
        foreach (lerTipo($tipo, $ano) as $bruto) {
            $iso = brParaIso($bruto['data'] ?? null);
            if (!$iso) {
                continue;
            }

            $feriado = [
                'data' => $iso,
                'nome' => $bruto['nome'],
                'tipo' => $tipo,
                'descricao' => !empty($bruto['descricao']) ? $bruto['descricao'] : null,
                'uf' => !empty($bruto['uf']) ? mb_strtoupper($bruto['uf']) : null,
                'codigoIbge' => $bruto['codigo_ibge'] ?? null,
            ];

            $indice[$iso][] = $feriado;
        }
    }

    $cache[$ano] = $indice;
    return $indice;
}

/**
 * Filtra os feriados aplicáveis a um local.
 * Nacional vale sempre; estadual exige a UF; municipal exige o código IBGE.
 */
function aplicaveis(array $feriados, array $local): array
{
    $uf = $local['uf'] ?? null;
    $codigoIbge = $local['codigoIbge'] ?? null;

    return array_values(array_filter($feriados, function (array $f) use ($uf, $codigoIbge): bool {
        if ($f['tipo'] === 'nacional' || $f['tipo'] === 'facultativo') {
            // Facultativo estadual/municipal também existe nos dados.
            if ($f['codigoIbge'] !== null) {
                return $f['codigoIbge'] === $codigoIbge;
            }
            if ($f['uf']) {
                return $f['uf'] === $uf;
            }
            return true;
        }
        if ($f['tipo'] === 'estadual') {
            return $uf !== null && $f['uf'] === $uf;
        }
        if ($f['tipo'] === 'municipal') {
            return $codigoIbge !== null && $f['codigoIbge'] === $codigoIbge;
        }
        return false;
    }));
}

/** Todos os feriados do ano aplicáveis ao local, ordenados por data. */
function feriadosDoAno(int $ano, array $local): array
{
    $indice = indicePorAno($ano);
    $saida = [];
    foreach ($indice as $lista) {
        array_push($saida, ...aplicaveis($lista, $local));
    }

    // usort() só é garantidamente estável a partir do PHP 8.0; decoramos com o
    // índice original para preservar o desempate em versões anteriores.
    $collator = new Collator('pt_BR');
    $decorado = array_values($saida);
    $indices = array_keys($decorado);
    usort($indices, function (int $i, int $j) use ($decorado, $collator): int {
        $a = $decorado[$i];
        $b = $decorado[$j];
        $porData = strcmp($a['data'], $b['data']);
        if ($porData !== 0) {
            return $porData;
        }
        $porNome = $collator->compare($a['nome'], $b['nome']);
        return $porNome !== 0 ? $porNome : $i <=> $j;
    });

    return array_map(fn(int $i) => $decorado[$i], $indices);
}

/** Feriados aplicáveis numa data ISO específica. */
function feriadosNaData(string $iso, int $ano, array $local): array
{
    $lista = indicePorAno($ano)[$iso] ?? [];
    return aplicaveis($lista, $local);
}

function anosEmCache(): array
{
    $anos = array_keys(indicePorAnoCache());
    sort($anos);
    return $anos;
}
