<?php

namespace App\Services\Backup;

use RuntimeException;

class PostgresConnection
{
    /** @return array{host: string, port: string, database: string, username: string, password: string, sslmode: string} */
    public function parameters(string $connection): array
    {
        $config = config("database.connections.{$connection}");
        if (! is_array($config)) {
            throw new RuntimeException("PostgreSQL connection [{$connection}] is not configured.");
        }

        $url = filled($config['url'] ?? null) ? parse_url((string) $config['url']) : false;
        $query = [];
        if (is_array($url) && isset($url['query'])) {
            parse_str($url['query'], $query);
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
        return ['PGPASSWORD' => $parameters['password'], 'PGSSLMODE' => $parameters['sslmode']];
    }
}
