<?php

// Crea y elimina exclusivamente una BD temporal. Nunca migra la BD configurada.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\ProductVariant;
use App\Services\AuditQueryService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
});

function api(string $method, string $uri, array $payload = [], ?string $token = null): array
{
    $kernel = app(HttpKernel::class);
    $request = Request::create($uri, $method, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => $token ? 'Bearer '.$token : ''], content: json_encode($payload));
    $response = $kernel->handle($request);
    $data = json_decode($response->getContent(), true);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $data];
}

function requireSuccess(array $response): array
{
    if ($response[0] < 200 || $response[0] >= 300) {
        throw new RuntimeException('HTTP '.$response[0].': '.($response[1]['message'] ?? 'Unknown response'));
    }

    return $response[1]['data'];
}

if (($argv[1] ?? null) === '--worker') {
    if (! preg_match('/^tienda_v2_audit_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
        throw new RuntimeException('La BD del worker no es temporal.');
    }
    $kind = $argv[2];
    $token = getenv('AUDIT_TEST_TOKEN');
    $result = match ($kind) {
        'product' => api('POST', '/api/products', ['categories' => [1], 'idSizeGroup' => 1, 'name' => 'Concurrent audit product',
            'price' => 100, 'cost' => 50, 'stockControl' => true, 'typeIva' => 1, 'rateIva' => 16,
            'variants' => [['idSize' => 2, 'idColor' => 1, 'stock' => 0]]], $token),
        'rename' => api('PUT', '/api/categories/1', ['key' => 'CMS', 'cascadeKeys' => true], $token),
        'order' => api('POST', '/api/orders', ['details' => [['idProductVariant' => 1, 'quantityOrdered' => 4]]], $token),
        'receive' => api('POST', '/api/orders/'.getenv('AUDIT_TEST_ORDER').'/receive', ['lines' => [['detailId' => (int) getenv('AUDIT_TEST_DETAIL'), 'quantity' => 1]]], $token),
        default => throw new RuntimeException('Worker desconocido.'),
    };
    requireSuccess($result);
    echo $kind.' HTTP '.$result[0].PHP_EOL;
    exit(0);
}

$connection = config('database.default');
if (! in_array(config("database.connections.$connection.driver"), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Configura una conexión MariaDB/MySQL para esta verificación.');
}
config(["database.connections.$connection.options" => array_replace(config("database.connections.$connection.options", []), [PDO::ATTR_TIMEOUT => 5])]);
$original = DB::connection()->getConfig();
$pdo = DB::connection()->getPdo();
echo 'Server: '.$pdo->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
$database = 'tienda_v2_audit_test_'.bin2hex(random_bytes(6));
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $environment = array_merge(getenv(), [
        'DB_CONNECTION' => $connection, 'DB_DATABASE' => $database, 'DB_URL' => '',
        'DB_HOST' => (string) $original['host'], 'DB_PORT' => (string) $original['port'],
        'DB_USERNAME' => (string) $original['username'], 'DB_PASSWORD' => (string) $original['password'],
        'DB_SOCKET' => (string) ($original['unix_socket'] ?? ''),
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
    ]);
    $pipes = [];
    $process = proc_open([PHP_BINARY, 'artisan', 'test', '--compact', '--filter=Audit'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    echo stream_get_contents($pipes[1]);
    echo stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Fallaron las pruebas de auditoría en MariaDB.');
    }

    config(["database.connections.$connection.database" => $database, "database.connections.$connection.url" => null]);
    DB::purge($connection);
    if (DB::connection()->getDatabaseName() !== $database) {
        throw new RuntimeException('La conexión no apunta a la BD temporal.');
    }
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);
    $environment['AUDIT_TEST_TOKEN'] = requireSuccess(api('POST', '/api/login', ['userName' => 'admin', 'password' => 'admin']))['token'];

    $concurrent = function (array $kinds) use (&$environment) {
        $workers = [];
        foreach ($kinds as $kind) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $kind], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Falló worker concurrente: '.$output);
            }
            echo $output;
        }
    };
    $concurrent(['product', 'product', 'product', 'product', 'rename']);
    if (DB::table('products')->where('name', 'Concurrent audit product')->where('key', 'like', 'CMS-%')->count() !== 4) {
        throw new RuntimeException('La cascada concurrente dejó claves inconsistentes.');
    }
    $concurrent(['order', 'order', 'order', 'order']);
    if (DB::table('orders')->distinct()->count('folio') !== 4) {
        throw new RuntimeException('Folios concurrentes inconsistentes.');
    }
    $order = requireSuccess(api('POST', '/api/orders/1/confirm', token: $environment['AUDIT_TEST_TOKEN']));
    $environment['AUDIT_TEST_ORDER'] = (string) $order['id'];
    $environment['AUDIT_TEST_DETAIL'] = (string) $order['details'][0]['id'];
    $before = (float) ProductVariant::findOrFail(1)->stock;
    $concurrent(['receive', 'receive', 'receive', 'receive']);
    if ((float) ProductVariant::findOrFail(1)->stock !== $before + 4
        || DB::table('orders')->where('id', 1)->value('status') !== 'received'
        || DB::table('audit_events')->where('action', 'orders.received')->where('outcome', 'success')->count() !== 4) {
        throw new RuntimeException('Recepción o auditoría concurrente inconsistente.');
    }
    echo 'Concurrency: keys, rename, folios, receipts and stock verified.'.PHP_EOL;

    $started = microtime(true);
    $requestId = (string) Str::uuid();
    for ($batch = 0; $batch < 100; $batch++) {
        $rows = [];
        for ($index = 0; $index < 1000; $index++) {
            $rows[] = ['request_id' => $requestId, 'module' => 'products', 'action' => 'products.updated',
                'outcome' => 'success', 'actor_id' => $index % 100, 'source' => 'system', 'occurred_at' => '2026-01-01 12:00:00'];
        }
        DB::table('audit_events')->insert($rows);
    }
    $result = app(AuditQueryService::class)->index(['w' => ['actorId' => 37]]);
    $explain = DB::selectOne('EXPLAIN SELECT id FROM audit_events WHERE actor_id = 37 AND occurred_at >= ? ORDER BY occurred_at DESC, id DESC LIMIT 15', ['2026-01-01']);
    if (count($result['items']) !== 15 || $result['totalCount'] !== 1000 || ! $explain->key) {
        throw new RuntimeException('Falló la verificación de volumen o índice.');
    }
    echo 'Volume: 100000 events; 15 per page; index='.$explain->key.'; setup/query='.round((microtime(true) - $started) * 1000).'ms'.PHP_EOL;
} finally {
    DB::disconnect($connection);
    if (! preg_match('/^tienda_v2_audit_test_[a-f0-9]{12}$/', $database)) {
        throw new RuntimeException('Nombre temporal inesperado; no se elimina.');
    }
    $pdo->exec("DROP DATABASE `$database`");
    echo 'Temporary database removed.'.PHP_EOL;
}
