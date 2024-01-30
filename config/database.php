<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'banca1' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'loteriabr_BD_DEV',
            'username' => 'loteriabr_DEV_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'banca2' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'loteriabr_DEV_API',
            'username' => 'loteriabr_DEV_API',
            'password' => 'DEVApi2023',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'SuperLotogiro' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'loteriabr_BD_SPL_PRD',
            'username' => 'loteriabr_USER_SPL_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'AguiaDasorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'aguiadasorte_BD_PROD',
            'username' => 'aguiadasorte_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'TalismaDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'talismadasorte_BD_PROD',
            'username' => 'talismadasorte_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'AzaraoDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'oazaraodasorte_BD_PROD',
            'username' => 'oazaraodasorte_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'AgoraEuGanho' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'agoraeuganho_BD_PRDO',
            'username' => 'agoraeuganho_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'AposteNaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'apostena_BD_PROD',
            'username' => 'apostena_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'GiroDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'girodaso_BD_PROD',
            'username' => 'girodaso_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'LotoDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'lotodaso_BD_PROD',
            'username' => 'lotodaso_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'LotoImperial' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'lotoimpe_BD_PROD',
            'username' => 'lotoimpe_USER_PROD',
            'password' => 'LotoImperial2022',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'LotoPix' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'lotopix_BD_PRDO',
            'username' => 'lotopix_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'LotoRolex' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'lotorolex_BD_PRDO',
            'username' => 'lotorolex_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'MegaGiroDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'megagirodasorte_BD_PRDO',
            'username' => 'megagirodasorte_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'MegaJogos' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'megajogos_BD_PRDO',
            'username' => 'megajogos_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'PigDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'pigdasorte_BD_PRDO',
            'username' => 'pigdasorte_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'RodaDaFortuna' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'rodadafortuna_BD_PRDO',
            'username' => 'rodadafortuna_USER_PRD',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
'TriunfoDaSorte' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.144.50',
            'port' => '3306',
            'database' => 'triunfod_BD_PROD',
            'username' => 'triunfod_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'lottobluesg' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'lottobluesg_BD_PROD',
            'username' => 'lottobluesg_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    'lottohub' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => '92.204.136.116',
            'port' => '3306',
            'database' => 'lottohub_BD_PROD',
            'username' => 'lottohub_PROD_USER',
            'password' => 'SmartPayBD22',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
