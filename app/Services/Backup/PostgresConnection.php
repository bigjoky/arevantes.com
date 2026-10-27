<?php

namespace App\Services\Backup;

use RuntimeException;

class PostgresConnection
{
    /** @return array{host: string, port: string, database: string, username: string, password: string, sslmode: string} */
    public function parameters(string $connection): array
    {
        $config = config("database.connections.{$connection}");
        if (! is_array($config) || ($config['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException("PostgreSQL connection [{$connection}] is not configured.");
        }

        $url = filled($config['url'] ?? null) ? parse_url((string) $config['url']) : false;
        if (filled($config['url'] ?? null) && (! is_array($url) || ! in_array(strtolower($url['scheme'] ?? ''), ['postgres', 'postgresql'], true))) {
            throw new RuntimeException('Invalid PostgreSQL connection URL.');
        }
        $query = [];
        if (is_array($url) && isset($url['query'])) {
            parse_str($url['query'], $query);
        }

        if (array_diff(array_keys($query), ['sslmode']) !== []) {
            throw new RuntimeException('Unsupported PostgreSQL URL options; configure explicit connection parameters.');
        }

        $parameters = [
            'host' => is_array($url) ? urldecode((string) ($url['host'] ?? '')) : (string) ($config['host'] ?? ''),
            'port' => is_array($url) ? (string) ($url['port'] ?? 5432) : (string) ($config['port'] ?? 5432),
            'database' => is_array($url) ? ltrim(urldecode((string) ($url['path'] ?? '')), '/') : (string) ($config['database'] ?? ''),
            'username' => is_array($url) ? urldecode((string) ($url['user'] ?? '')) : (string) ($config['username'] ?? ''),
            'password' => is_array($url) ? urldecode((string) ($url['pass'] ?? '')) : (string) ($config['password'] ?? ''),
            'sslmode' => (string) ($query['sslmode'] ?? $config['sslmode'] ?? 'require'),
        ];

        foreach (['host', 'database', 'username'] as $required) {
            if ($parameters[$required] === '') {
                throw new RuntimeException("PostgreSQL connection [{$connection}] is missing {$required}.");
            }
        }

        foreach ($parameters as $key => $value) {
            if ($key !== 'password' && preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('Invalid PostgreSQL connection parameter.');
            }
        }
        if (! ctype_digit($parameters['port']) || (int) $parameters['port'] < 1 || (int) $parameters['port'] > 65535
            || str_contains($parameters['database'], '=') || str_contains($parameters['database'], '://')
            || ! preg_match('/^[a-zA-Z0-9_.:-]+$/', $parameters['host'])
            || ! in_array($parameters['sslmode'], ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
            throw new RuntimeException('Invalid PostgreSQL connection parameters.');
        }

        return $parameters;
    }

    /** @param array{host: string, port: string, database: string, username: string, password: string, sslmode: string} $parameters */
    public function processArguments(array $parameters): array
    {
        return [
            '--host='.$parameters['host'],
            '--port='.$parameters['port'],
            '--username='.$parameters['username'],
            '--dbname='.$parameters['database'],
            '--no-password',
        ];
    }

    /** @param array{password: string, sslmode: string} $parameters */
    public function processEnvironment(array $parameters): array
    {
        return ['PGPASSWORD' => $parameters['password'], 'PGSSLMODE' => $parameters['sslmode'], 'PGHOSTADDR' => false, 'PGSERVICE' => false, 'PGSERVICEFILE' => false, 'PGOPTIONS' => false, 'PGDATABASE' => false, 'PGHOST' => false, 'PGPORT' => false, 'PGUSER' => false];
    }
}
