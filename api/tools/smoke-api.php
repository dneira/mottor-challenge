<?php

declare(strict_types=1);

// Run from api/: php tools/smoke-api.php. Uses configured DB, rolls back its own writes.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$database = Illuminate\Support\Facades\DB::connection();
$before = $database->table('quotes')->count();
$database->beginTransaction();
try {
    $raw = file_get_contents(__DIR__.'/../../fixtures/official.input.json');
    $request = Illuminate\Http\Request::create('/quotes', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);
    $created = $kernel->handle($request);
    if ($created->getStatusCode() !== 201 || !str_contains($created->getContent(), '"total":4722388.00')) {
        throw new RuntimeException('Falló POST /quotes: '.$created->getContent());
    }
    $id = json_decode($created->getContent(), true, 64, JSON_THROW_ON_ERROR)['id'];
    $read = $kernel->handle(Illuminate\Http\Request::create('/quotes/'.$id, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    if ($read->getStatusCode() !== 200 || $read->getContent() !== $created->getContent()) {
        throw new RuntimeException('GET no coincide con el snapshot creado.');
    }
    echo 'POST 201, GET 200, total numérico exacto 4722388.00; motor DB: '.$database->getDriverName().PHP_EOL;
    $batchInput = (new App\Support\ExactJson())->decode($raw);
    $batchInput->budget->client = 'SMOKE-'.bin2hex(random_bytes(12)).'%_!';
    $body = (new App\Support\ExactJson())->encode(['quotes' => [$batchInput, $batchInput]]);
    $batch = $kernel->handle(Illuminate\Http\Request::create('/quotes/batch', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body));
    if ($batch->getStatusCode() !== 201 || json_decode($batch->getContent(), true)['imported'] !== 2) {
        throw new RuntimeException('Falló el batch: '.$batch->getContent());
    }
    $database->enableQueryLog();
    $database->flushQueryLog();
    $url = '/quotes?currency=USD&client='.rawurlencode($batchInput->budget->client).'&per_page=1&page=2';
    $listed = $kernel->handle(Illuminate\Http\Request::create($url, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    $queries = $database->getQueryLog();
    $database->disableQueryLog();
    $document = json_decode($listed->getContent(), true);
    if ($listed->getStatusCode() !== 200 || ($document['meta']['total'] ?? null) !== 2 || ($document['data'][0]['total'] ?? null) !== 3256.82 || count($queries) !== 2) {
        throw new RuntimeException('Falló listado/SQL: '.$listed->getContent());
    }
    echo 'Batch 201, listado USD/paginación/filtro literal correctos, exactamente dos consultas SQL.'.PHP_EOL;
    foreach ($queries as $query) {
        echo $query['query'].PHP_EOL;
    }
    $batchInput->rules[0]->type = 'unknown';
    $invalidBody = (new App\Support\ExactJson())->encode(['quotes' => [(new App\Support\ExactJson())->decode($raw), $batchInput]]);
    $beforeInvalid = $database->table('quotes')->count();
    $invalid = $kernel->handle(Illuminate\Http\Request::create('/quotes/batch', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $invalidBody));
    if ($invalid->getStatusCode() !== 422 || $database->table('quotes')->count() !== $beforeInvalid) {
        throw new RuntimeException('El batch inválido no respetó la atomicidad.');
    }
    echo 'Batch inválido 422 sin nuevas escrituras.'.PHP_EOL;
} finally {
    $database->rollBack();
}
if ($database->table('quotes')->count() !== $before) {
    throw new RuntimeException('La prueba dejó registros inesperados.');
}
echo 'Rollback confirmado; no quedaron presupuestos de prueba.'.PHP_EOL;
