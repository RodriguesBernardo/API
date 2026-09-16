<?php
declare(strict_types=1);

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/data/Paths.php';
require __DIR__ . '/../src/lib/Normalize.php';
require __DIR__ . '/../src/lib/Datas.php';
require __DIR__ . '/../src/data/Feriados.php';
require __DIR__ . '/../src/data/Comemorativas.php';
require __DIR__ . '/../src/data/Localizacao.php';
require __DIR__ . '/../src/routes.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(int $status, $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Remove o prefixo do diretório do front controller, para funcionar tanto na
// raiz do host quanto num subdiretório (ex: DocumentRoot != php/public).
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
if ($path === '') {
    $path = '/';
}

if (!hash_equals(API_TOKEN, (string) ($_GET['token'] ?? ''))) {
    responderJson(401, ['erro' => "Token inválido ou ausente."]);
    return;
}

try {
    $tratada = despachar($_SERVER['REQUEST_METHOD'], $path, $_GET, 'responderJson');
    if (!$tratada) {
        responderJson(404, ['erro' => "Rota não encontrada: {$_SERVER['REQUEST_METHOD']} $path"]);
    }
} catch (Throwable $erro) {
    error_log((string) $erro);
    responderJson(500, ['erro' => 'Erro interno.']);
}
