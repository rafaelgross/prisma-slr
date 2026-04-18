<?php
/**
 * PRISMA-SLR - Proxy de Tradução (server-side para evitar CORS)
 * Usa a API MyMemory (gratuita, sem chave) — divide texto em blocos de 490 chars
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$text = $_GET['text'] ?? '';
if (empty(trim($text))) {
    echo json_encode(['error' => true, 'message' => 'Texto vazio']);
    exit;
}

// Limitar texto total para não demorar demais
$text = mb_substr(trim($text), 0, 3000);

/**
 * Divide o texto em blocos de no máximo $maxLen caracteres,
 * quebrando sempre em fim de frase (. ! ?) ou espaço — nunca no meio de palavra.
 */
function splitText(string $text, int $maxLen = 490): array {
    $chunks = [];
    while (mb_strlen($text) > 0) {
        if (mb_strlen($text) <= $maxLen) {
            $chunks[] = $text;
            break;
        }
        // Pega os primeiros $maxLen chars
        $slice = mb_substr($text, 0, $maxLen);
        // Tenta cortar no último ponto/exclamação/interrogação
        $cut = max(
            mb_strrpos($slice, '. '),
            mb_strrpos($slice, '! '),
            mb_strrpos($slice, '? ')
        );
        // Se não achou fim de frase, corta no último espaço
        if ($cut === false || $cut < 50) {
            $cut = mb_strrpos($slice, ' ');
        }
        // Fallback: corta na posição máxima
        if ($cut === false || $cut < 10) {
            $cut = $maxLen;
        } else {
            $cut += 1; // inclui o espaço/ponto no bloco atual
        }
        $chunks[] = mb_substr($text, 0, $cut);
        $text = ltrim(mb_substr($text, $cut));
    }
    return $chunks;
}

function translateChunk(string $chunk): string {
    $encoded = urlencode($chunk);
    // Adicionar email aumenta o limite gratuito de 1.000 para 10.000 palavras/dia
    // Configure seu e-mail em config/database.php ou deixe vazio para usar o limite básico
    $email = defined('MYMEMORY_EMAIL') ? MYMEMORY_EMAIL : '';
    $emailParam = $email ? "&de=" . urlencode($email) : '';
    $url     = "https://api.mymemory.translated.net/get?q={$encoded}&langpair=en|pt-BR{$emailParam}";

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 12,
            'ignore_errors' => true,
            'header'        => "User-Agent: PRISMA-SLR/1.0\r\n",
        ]
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        throw new RuntimeException('Sem resposta da API de tradução');
    }

    $data = json_decode($response, true);
    if (!$data || ($data['responseStatus'] ?? 0) != 200) {
        $msg = $data['responseDetails'] ?? 'Erro da API';
        throw new RuntimeException($msg);
    }

    return $data['responseData']['translatedText'] ?? $chunk;
}

// --- Main ---
$chunks = splitText($text, 490);
$translated = [];

foreach ($chunks as $chunk) {
    try {
        $translated[] = translateChunk($chunk);
        // Pequena pausa entre requisições para não sobrecarregar a API
        if (count($chunks) > 1) {
            usleep(300000); // 0.3s
        }
    } catch (RuntimeException $e) {
        http_response_code(502);
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode([
    'error'      => false,
    'translated' => implode(' ', $translated),
    'chunks'     => count($chunks),
]);
