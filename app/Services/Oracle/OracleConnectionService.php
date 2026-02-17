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
        $username = (string) config('services.oracle.username');
        $password = (string) config('services.oracle.password');

        if (blank($host) || blank($serviceName) || blank($username)) {
            throw new RuntimeException('Oracle connection is not configured.');
        }

        if (extension_loaded('pdo_oci')) {
            $dsn = sprintf('oci:dbname=//%s:%s/%s', $host, $port, $serviceName);

            try {
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                return $pdo;
            } catch (PDOException $exception) {
                throw new RuntimeException('Failed to connect to Oracle database. ' . $exception->getMessage(), previous: $exception);
            }
        }

        if (extension_loaded('oci8')) {
            $connectionString = sprintf('//%s:%s/%s', $host, $port, $serviceName);
            $connection = @oci_connect($username, $password, $connectionString, 'AL32UTF8');

            if (! $connection) {
                $error = oci_error();
                $message = is_array($error) ? ($error['message'] ?? 'Unknown Oracle connection error.') : 'Unknown Oracle connection error.';
                throw new RuntimeException('Failed to connect to Oracle database. ' . $message);
            }

            return $connection;
        }

        throw new RuntimeException('Neither PDO OCI nor OCI8 extensions are installed.');
    }
}
