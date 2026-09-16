<?php
declare(strict_types=1);

/** "01/01/2026" -> "2026-01-01". Retorna null se não casar o formato. */
function brParaIso(?string $data): ?string
{
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim((string) $data), $m)) {
        return null;
    }
    [, $dia, $mes, $ano] = $m;
    return "$ano-$mes-$dia";
}

/** Valida "YYYY-MM-DD" e confere que a data existe de fato (rejeita 2026-02-30). */
function validarIso(?string $data): ?array
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim((string) $data), $m)) {
        return null;
    }
    [, $anoStr, $mesStr, $diaStr] = $m;
    if (!checkdate((int) $mesStr, (int) $diaStr, (int) $anoStr)) {
        return null;
    }
    return ['iso' => "$anoStr-$mesStr-$diaStr", 'ano' => (int) $anoStr];
}

const DIAS_DA_SEMANA = [
    'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira',
    'quinta-feira', 'sexta-feira', 'sábado',
];

/** Dia da semana em português para uma data ISO, sem depender do locale do SO. */
function diaDaSemana(string $iso): string
{
    [$ano, $mes, $dia] = array_map('intval', explode('-', $iso));
    $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia), new DateTimeZone('UTC'));
    return DIAS_DA_SEMANA[(int) $dt->format('w')];
}
