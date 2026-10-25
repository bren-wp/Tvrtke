<?php
declare(strict_types=1);
namespace Tvrtke;

/** A narrow interface so snapshot imports can be regression-tested offline. */
interface SudregGateway
{
    /** @return array{data:array,headers:array} */
    public function get(string $endpoint, array $params = []): array;
}
