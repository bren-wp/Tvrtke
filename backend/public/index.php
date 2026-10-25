<?php
declare(strict_types=1);

use Tvrtke\Database;
use Tvrtke\Identity;
use Tvrtke\SubjectsRepository;
use Tvrtke\DirectoryRepository;
use Tvrtke\Config;

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function reply(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') reply(405, ['error' => 'Metoda nije dopuštena']);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '/v1/health') reply(200, ['status' => 'ok', 'service' => 'tvrtke', 'version' => '0.7.0']);
if (!in_array($path, ['/v1/subjects', '/v1/status', '/v1/directory', '/v1/sources'], true) &&
    !preg_match('~^/v1/subjects/\d{1,9}$~D', $path) &&
    !preg_match('~^/v1/directory/(sudreg|obrt|udruga|zaklada|ostalo)/([a-f0-9]{64}|[0-9]{1,9})$~D', $path)) {
    reply(404, ['error' => 'Ruta nije pronađena']);
}
if ($path === '/v1/status') {
    $expected = Config::get('TVRTKE_ADMIN_TOKEN');
    $provided = preg_replace('/^Bearer\s+/i', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($expected === '' || !hash_equals($expected, $provided)) reply(401, ['error' => 'Neovlašten pristup']);
}
try {
    // Validate request parameters before opening a database connection, so bad
    // input always produces 422 even while the database is offline.
    if ($path === '/v1/subjects' || $path === '/v1/directory') {
        $rawQuery = $_GET['q'] ?? '';
        if (!is_string($rawQuery)) reply(422, ['error' => 'Neispravan upit']);
        $query = trim($rawQuery);
        if (strlen($query) > 400 || (function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) > 100) {
            reply(422, ['error' => 'Upit je predugačak']);
        }
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if ($page === false || $limit === false || ($path === '/v1/directory' && $page > 500)) {
            reply(422, ['error' => 'Neispravna paginacija. Koristite preciznije filtre za udaljene rezultate.']);
        }
        $filters = [];
        foreach (['county', 'settlement', 'activity', 'legal_form', 'status', 'type'] as $field) {
            $value = $_GET[$field] ?? '';
            if (!is_string($value) || strlen($value) > 400 ||
                (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > 100) {
                reply(422, ['error' => 'Neispravan filtar']);
            }
            $filters[$field] = trim($value);
        }
        if ($filters['type'] !== '' && !in_array($filters['type'],
            ['all','sudreg','obrt','udruga','zaklada','ostalo'], true)) {
            reply(422, ['error' => 'Nepoznat registar']);
        }
        if ($filters['type'] === '') $filters['type'] = 'all';
        if ($filters['status'] !== '' && !in_array($filters['status'], ['0', '1'], true)) {
            reply(422, ['error' => 'Status mora biti 0 ili 1']);
        }
        if ($filters['legal_form'] !== '' && !preg_match('/^[0-9]{1,12}$/D', $filters['legal_form'])) {
            reply(422, ['error' => 'Neispravna šifra pravnog oblika']);
        }
    }
    $db = Database::connectReadOnly();
    $repo = new SubjectsRepository($db);
    if ($path === '/v1/status') reply(200, $repo->status());
    if ($path === '/v1/sources') reply(200, ['sources' => (new DirectoryRepository($db))->sources()]);
    if ($path === '/v1/directory') reply(200, (new DirectoryRepository($db))->search($query, $page, $limit, $filters));
    if (preg_match('~^/v1/directory/(sudreg|obrt|udruga|zaklada|ostalo)/([a-f0-9]{64}|[0-9]{1,9})$~D', $path, $match)) {
        $record = (new DirectoryRepository($db))->one($match[1], $match[2]);
        if ($record === null) reply(404, ['error' => 'Zapis nije pronađen']);
        reply(200, ['item' => $record]);
    }
    if ($path === '/v1/subjects') reply(200, $repo->search($query, $page, $limit, $filters));
    $item = $repo->one(Identity::mbs(basename($path)));
    if ($item === null) reply(404, ['error' => 'Subjekt nije pronađen']);
    reply(200, ['item' => $item]);
} catch (\InvalidArgumentException $e) {
    reply(422, ['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('TVRTKE API: ' . $e->getMessage());
    reply(503, ['error' => 'Servis privremeno nije dostupan']);
}
