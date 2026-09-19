<?php

$json = json_encode([
    'status' => 'completed',
    'model' => 'synthetic-runtime-model',
    'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['facts' => [
        ['fact_type' => 'experience', 'assertion' => 'Built a synthetic API.', 'source_excerpt' => 'Built a synthetic API.', 'confidence' => 0.9],
        ['fact_type' => 'skill', 'assertion' => 'Familiar with PostgreSQL.', 'source_excerpt' => 'Familiar with PostgreSQL.', 'confidence' => 0.8],
        ['fact_type' => 'experience', 'assertion' => 'Improved a synthetic workflow.', 'source_excerpt' => 'Improved a synthetic workflow.', 'confidence' => 0.8],
        ['fact_type' => 'experience', 'assertion' => 'Documented a synthetic system.', 'source_excerpt' => 'Documented a synthetic system.', 'confidence' => 0.8],
    ]])]]]],
    'usage' => ['input_tokens' => 20, 'output_tokens' => 40],
], JSON_THROW_ON_ERROR);

$server = stream_socket_server('tcp://0.0.0.0:8000');
if ($server === false) {
    exit(1);
}

while ($client = stream_socket_accept($server, -1)) {
    while (($line = fgets($client)) !== false && trim($line) !== '') {
        // Consume request headers. The fixture response never reads or stores private request content.
    }
    $body = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nx-request-id: req_runtime_smoke\r\nContent-Length: ".strlen($json)."\r\nConnection: close\r\n\r\n".$json;
    fwrite($client, $body);
    fclose($client);
}
