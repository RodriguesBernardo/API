<?php
declare(strict_types=1);

/** Normaliza texto para busca: minúsculo, sem acento, sem espaço duplicado. */
function normalizar(string $texto): string
{
    $texto = Normalizer::normalize($texto, Normalizer::FORM_D);
    $texto = preg_replace('/\p{Mn}/u', '', $texto);
    $texto = mb_strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}
