<?php
declare(strict_types=1);

/** Anos com arquivo de comemorativas — lido do disco uma vez por requisição. */
function anosComemorativas(): array
{
    static $anosDisponiveis = null;
    if ($anosDisponiveis !== null) {
        return $anosDisponiveis;
    }

    $lista = [];
    foreach (scandir(DIR_COMEMORATIVAS . '/json') as $arquivo) {
        if (preg_match('/^(\d{4})\.json$/', $arquivo, $m)) {
            $lista[] = (int) $m[1];
        }
    }
    sort($lista);

    $anosDisponiveis = $lista;
    return $anosDisponiveis;
}

function anoSuportadoComemorativas(int $ano): bool
{
    return in_array($ano, anosComemorativas(), true);
}

/** Datas comemorativas do ano, ordenadas por data. Cache por requisição. */
function comemorativasDoAno(int $ano): array
{
    static $cache = [];
    if (isset($cache[$ano])) {
        return $cache[$ano];
    }

    $arquivo = DIR_COMEMORATIVAS . "/json/$ano.json";
    $lista = [];
    if (is_file($arquivo)) {
        foreach (json_decode(file_get_contents($arquivo), true) as $bruto) {
            $iso = brParaIso($bruto['data'] ?? null);
            if (!$iso) {
                continue;
            }
            $lista[] = [
                'data' => $iso,
                'nome' => $bruto['nome'],
                'tipo' => 'comemorativa',
                'descricao' => !empty($bruto['descricao']) ? $bruto['descricao'] : null,
                'uf' => null,
                'codigoIbge' => null,
            ];
        }
        usort($lista, fn(array $a, array $b): int => $a['data'] <=> $b['data']);
    }

    $cache[$ano] = $lista;
    return $lista;
}

/** Próximas datas comemorativas a partir de (e incluindo) $deIso, ordenadas por data. */
function proximasComemorativas(string $deIso, int $limite): array
{
    $ano = (int) substr($deIso, 0, 4);
    $eventos = array_values(array_filter(
        comemorativasDoAno($ano),
        fn(array $c) => $c['data'] >= $deIso
    ));

    while (count($eventos) < $limite && anoSuportadoComemorativas($ano + 1)) {
        $ano++;
        array_push($eventos, ...comemorativasDoAno($ano));
    }

    return array_slice($eventos, 0, $limite);
}
