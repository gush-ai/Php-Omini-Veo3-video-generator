<?php
declare(strict_types=1);

namespace GVid;

final class Auth
{
    public static function require(): void
    {
        $expected = (string) Config::get('GV_AUTH_TOKEN', '');
        if ($expected === '') {
            Response::error('Server authentication is not configured.', 500, 'server_misconfigured');
        }

        $actual = Request::bearerToken();
        if (!$actual || !hash_equals($expected, $actual)) {
            Response::error('Unauthorized.', 401, 'unauthorized');
        }
    }
}
