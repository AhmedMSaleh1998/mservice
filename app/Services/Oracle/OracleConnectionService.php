<?php

namespace App\Services\Oracle;

use PDO;
use PDOException;
use RuntimeException;

class OracleConnectionService
{
    public function make()
    {
        $host = (string) config('services.oracle.host');
        $port = (string) config('services.oracle.port', '1521');
        $serviceName = (string) config('services.oracle.service_name');
        $charset = (string) config('services.oracle.charset', 'AL32UTF8');
        $driver = strtolower((string) config('services.oracle.driver', 'oci8'));
        $username = (string) config('services.oracle.username');
        $password = (string) config('services.oracle.password');

        if (blank($host) || blank($serviceName) || blank($username)) {
            throw new RuntimeException('Oracle connection is not configured.');
        }

        $orderedDrivers = match ($driver) {
            'pdo', 'pdo_oci' => ['pdo_oci'],
            'oci8' => ['oci8'],
            'auto' => ['oci8', 'pdo_oci'],
            default => ['oci8', 'pdo_oci'],
        };

        if ($driver === 'oci8' && ! extension_loaded('oci8')) {
            throw new RuntimeException('OCI8 extension is required for Oracle image BLOB export, but it is not enabled.');
        }

        if (in_array($driver, ['pdo', 'pdo_oci'], true) && ! extension_loaded('pdo_oci')) {
            throw new RuntimeException('PDO_OCI extension is required for configured Oracle driver pdo_oci, but it is not enabled.');
        }

        $errors = [];

        foreach ($orderedDrivers as $candidateDriver) {
            if ($candidateDriver === 'oci8' && extension_loaded('oci8')) {
                $connectionString = sprintf('//%s:%s/%s', $host, $port, $serviceName);
                $connection = @oci_connect($username, $password, $connectionString, $charset);

                if ($connection) {
                    return $connection;
                }

                $error = oci_error();
                $errors[] = sprintf(
                    'oci8: %s',
                    is_array($error) ? ($error['message'] ?? 'Unknown Oracle connection error.') : 'Unknown Oracle connection error.'
                );
                continue;
            }

            if ($candidateDriver === 'pdo_oci' && extension_loaded('pdo_oci')) {
                $dsn = sprintf('oci:dbname=//%s:%s/%s;charset=%s', $host, $port, $serviceName, $charset);

                try {
                    $pdo = new PDO($dsn, $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    return $pdo;
                } catch (PDOException $exception) {
                    $errors[] = 'pdo_oci: ' . $exception->getMessage();
                    continue;
                }
            }
        }

        if (! extension_loaded('oci8') && ! extension_loaded('pdo_oci')) {
            throw new RuntimeException('Neither PDO OCI nor OCI8 extensions are installed.');
        }

        throw new RuntimeException('Failed to connect to Oracle database. ' . implode(' | ', $errors));
    }
}
