<?php

use Solaris\ConfigEditor\ConfigEditor;

beforeEach(function () {
    // Setup test directory
    $this->testDir = __DIR__ . '/fixtures';
    if (!is_dir($this->testDir)) {
        mkdir($this->testDir, 0755, true);
    }
});

afterEach(function () {
    // Cleanup test files
    if (is_dir($this->testDir)) {
        array_map('unlink', glob("{$this->testDir}/*.php"));
        rmdir($this->testDir);
    }
});

describe('ConfigEditor Merge - Scalar Values', function () {
    
    it('merges scalar values with last-wins strategy', function () {
        $base = <<<'PHP'
<?php
return [
    'name' => 'Base App',
    'version' => '1.0',
    'debug' => true,
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'name' => 'Override App',
    'environment' => 'production',
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['name'])->toBe('Override App'); // overridden
        expect($result['version'])->toBe('1.0'); // preserved from base
        expect($result['debug'])->toBe(true); // preserved from base
        expect($result['environment'])->toBe('production'); // added from override
    });

    it('preserves different data types correctly', function () {
        $base = <<<'PHP'
<?php
return [
    'string' => 'value',
    'int' => 42,
    'float' => 3.14,
    'bool_true' => true,
    'bool_false' => false,
    'null' => null,
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'int' => 100,
    'bool_true' => false,
    'new_string' => 'added',
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['string'])->toBe('value');
        expect($result['int'])->toBe(100);
        expect($result['float'])->toBe(3.14);
        expect($result['bool_true'])->toBe(false);
        expect($result['bool_false'])->toBe(false);
        expect($result['null'])->toBe(null);
        expect($result['new_string'])->toBe('added');
    });
});

describe('ConfigEditor Merge - Indexed Arrays', function () {
    
    it('merges indexed arrays by concatenation', function () {
        $base = <<<'PHP'
<?php
return [
    'providers' => [
        'App\Providers\AuthProvider',
        'App\Providers\DatabaseProvider',
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'providers' => [
        'App\Providers\CacheProvider',
        'App\Providers\QueueProvider',
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['providers'])->toBe([
            'App\Providers\AuthProvider',
            'App\Providers\DatabaseProvider',
            'App\Providers\CacheProvider',
            'App\Providers\QueueProvider',
        ]);
    });

    it('deduplicates indexed arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'tags' => ['php', 'laravel'],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'tags' => ['laravel', 'vue', 'tailwind'],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['tags'])->toBe([
            'php',
            'laravel',
            'vue',
            'tailwind',
        ]);
    });

    it('handles empty indexed arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'items' => [],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'items' => ['foo', 'bar'],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['items'])->toBe(['foo', 'bar']);
    });

    it('merges indexed arrays with different types', function () {
        $base = <<<'PHP'
<?php
return [
    'mixed' => [1, 'string', true],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'mixed' => [2, 'string', false, null],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['mixed'])->toBe([1, 'string', true, 2, false, null]);
    });
});

describe('ConfigEditor Merge - Associative Arrays', function () {
    
    it('merges associative arrays recursively', function () {
        $base = <<<'PHP'
<?php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'charset' => 'utf8mb4',
            ],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'database' => [
        'host' => '10.0.0.5',
        'username' => 'root',
        'connections' => [
            'mysql' => [
                'charset' => 'utf8mb4_unicode_ci',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
            ],
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['database']['host'])->toBe('10.0.0.5');
        expect($result['database']['port'])->toBe(3306);
        expect($result['database']['username'])->toBe('root');
        expect($result['database']['connections']['mysql']['driver'])->toBe('mysql');
        expect($result['database']['connections']['mysql']['charset'])->toBe('utf8mb4_unicode_ci');
        expect($result['database']['connections']['mysql']['collation'])->toBe('utf8mb4_unicode_ci');
        expect($result['database']['connections']['pgsql']['driver'])->toBe('pgsql');
    });

    it('adds new keys to associative arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'models' => [
        'user' => 'App\Models\User',
        'city' => 'Solaris\Core\Models\City',
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'models' => [
        'account' => 'Solaris\MasterData\Models\Account',
        'contact' => 'Solaris\MasterData\Models\Contact',
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['models'])->toBe([
            'user' => 'App\Models\User',
            'city' => 'Solaris\Core\Models\City',
            'account' => 'Solaris\MasterData\Models\Account',
            'contact' => 'Solaris\MasterData\Models\Contact',
        ]);
    });

    it('handles deeply nested associative arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'level1' => [
        'level2' => [
            'level3' => [
                'key1' => 'value1',
            ],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'level1' => [
        'level2' => [
            'level3' => [
                'key2' => 'value2',
            ],
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['level1']['level2']['level3'])->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });
});

describe('ConfigEditor Merge - Mixed Structures', function () {
    
    it('merges nested associative arrays with indexed arrays inside', function () {
        $base = <<<'PHP'
<?php
return [
    'cache' => [
        'default' => 'redis',
        'stores' => [
            'redis' => [
                'driver' => 'redis',
                'hosts' => ['127.0.0.1:6379'],
            ],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'cache' => [
        'default' => 'memcached',
        'stores' => [
            'redis' => [
                'hosts' => ['10.0.0.10:6379'],
            ],
            'memcached' => [
                'driver' => 'memcached',
            ],
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['cache']['default'])->toBe('memcached');
        expect($result['cache']['stores']['redis']['driver'])->toBe('redis');
        expect($result['cache']['stores']['redis']['hosts'])->toBe([
            '127.0.0.1:6379',
            '10.0.0.10:6379',
        ]);
        expect($result['cache']['stores']['memcached']['driver'])->toBe('memcached');
    });

    it('merges complex nested structures with multiple levels', function () {
        $base = <<<'PHP'
<?php
return [
    'services' => [
        'mail' => [
            'driver' => 'smtp',
            'from' => [
                'address' => 'old@example.com',
                'name' => 'Old App',
            ],
            'providers' => ['sendgrid', 'mailgun'],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'services' => [
        'mail' => [
            'from' => [
                'address' => 'new@example.com',
            ],
            'providers' => ['ses'],
        ],
        'sms' => [
            'driver' => 'twilio',
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['services']['mail']['driver'])->toBe('smtp');
        expect($result['services']['mail']['from']['address'])->toBe('new@example.com');
        expect($result['services']['mail']['from']['name'])->toBe('Old App');
        expect($result['services']['mail']['providers'])->toBe([
            'sendgrid',
            'mailgun',
            'ses',
        ]);
        expect($result['services']['sms']['driver'])->toBe('twilio');
    });

    it('handles associative arrays containing indexed arrays of associative arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'servers' => [
        'web' => [
            [
                'host' => '192.168.1.1',
                'port' => 80,
            ],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'servers' => [
        'web' => [
            [
                'host' => '192.168.1.2',
                'port' => 443,
            ],
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['servers']['web'])->toHaveCount(2);
        expect($result['servers']['web'][0])->toBe(['host' => '192.168.1.1', 'port' => 80]);
        expect($result['servers']['web'][1])->toBe(['host' => '192.168.1.2', 'port' => 443]);
    });

    it('merges deeply nested mixed structures', function () {
        $base = <<<'PHP'
<?php
return [
    'level1' => [
        'level2' => [
            'level3' => [
                'indexed' => ['a', 'b'],
                'assoc' => ['key' => 'value1'],
            ],
        ],
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'level1' => [
        'level2' => [
            'level3' => [
                'indexed' => ['c'],
                'assoc' => ['key' => 'value2', 'new' => 'data'],
            ],
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['level1']['level2']['level3']['indexed'])->toBe(['a', 'b', 'c']);
        expect($result['level1']['level2']['level3']['assoc'])->toBe([
            'key' => 'value2',
            'new' => 'data',
        ]);
    });
});

describe('ConfigEditor Merge - Real-World Scenarios', function () {
    
    it('merges Solaris Core and MasterData configs', function () {
        $core = <<<'PHP'
<?php
return [
    'title' => 'Solaris Core',
    'menu_access' => false,
    'license' => false,
    'register' => true,
    
    'resources_path' => [
        'core' => 'vendor/solaris/solaris-laravel-core/resources',
    ],
    
    'model_namespaces' => [
        'Solaris\Core\Models' => 'vendor/solaris/solaris-laravel-core/src/Models',
    ],
    
    'models' => [
        'user' => 'App\Models\User',
        'city' => 'Solaris\Core\Models\City',
        'country' => 'Solaris\Core\Models\Country',
    ],
    
    'repositories' => [
        'user' => 'Solaris\Core\Repositories\UserRepository',
        'role' => 'Solaris\Core\Repositories\BaseRepository',
    ],
    
    'controller_namespaces' => [
        'Solaris\Core\Http\Controllers',
        'Solaris\Core\Http\Controllers\Auth',
    ],
    
    'controllers' => [
        'login' => 'Solaris\Core\Http\Controllers\Auth\AuthController',
        'user' => 'Solaris\Core\Http\Controllers\UserController',
        'role' => 'Solaris\Core\Http\Controllers\RoleController',
    ],
    
    'form_requests' => [
        'user' => 'Solaris\Core\Http\Requests\UserRequest',
        'role' => 'Solaris\Core\Http\Requests\RoleRequest',
    ],
    
    'views' => [
        'login' => 'solar::pages.auth.login',
        'user' => [
            'list' => 'solar::pages.user.list',
            'page' => 'solar::pages.user.page',
        ],
        'role' => [
            'list' => 'solar::pages.role.list',
            'page' => 'solar::pages.role.page',
        ],
    ],
    
    'ttl' => [
        'lookup' => 7200,
        'select' => 7200,
    ],
];
PHP;

        $masterData = <<<'PHP'
<?php
return [
    'resources_path' => [
        'master-data' => 'vendor/solaris/solaris-laravel-masterdata/resources',
    ],
    
    'model_namespaces' => [
        'Solaris\MasterData\Models' => 'vendor/solaris/solaris-laravel-masterdata/src/Models',
    ],
    
    'models' => [
        'account' => 'Solaris\MasterData\Models\Account',
        'contact' => 'Solaris\MasterData\Models\Contact',
        'product' => 'Solaris\MasterData\Models\Product',
    ],
    
    'repositories' => [
        'account' => 'Solaris\MasterData\Repositories\AccountRepository',
        'contact' => 'Solaris\MasterData\Repositories\ContactRepository',
    ],
    
    'controller_namespaces' => [
        'Solaris\MasterData\Http\Controllers',
    ],
    
    'controllers' => [
        'account' => 'Solaris\MasterData\Http\Controllers\AccountController',
        'contact' => 'Solaris\MasterData\Http\Controllers\ContactController',
    ],
    
    'form_requests' => [
        'account' => 'Solaris\MasterData\Http\Requests\AccountRequest',
        'contact' => 'Solaris\MasterData\Http\Requests\ContactRequest',
    ],
    
    'views' => [
        'account' => [
            'list' => 'solar-md::pages.account.list',
            'page' => 'solar-md::pages.account.page',
        ],
        'contact' => [
            'list' => 'solar-md::pages.contact.list',
            'page' => 'solar-md::pages.contact.page',
        ],
    ],
];
PHP;

        file_put_contents("{$this->testDir}/core.php", $core);
        file_put_contents("{$this->testDir}/masterdata.php", $masterData);

        $editor = new ConfigEditor("{$this->testDir}/core.php");
        $editor->merge("{$this->testDir}/masterdata.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        // Scalars preserved from base
        expect($result['title'])->toBe('Solaris Core');
        expect($result['menu_access'])->toBe(false);
        expect($result['license'])->toBe(false);
        expect($result['register'])->toBe(true);

        // Associative arrays merged
        expect($result['resources_path'])->toBe([
            'core' => 'vendor/solaris/solaris-laravel-core/resources',
            'master-data' => 'vendor/solaris/solaris-laravel-masterdata/resources',
        ]);

        expect($result['model_namespaces'])->toBe([
            'Solaris\Core\Models' => 'vendor/solaris/solaris-laravel-core/src/Models',
            'Solaris\MasterData\Models' => 'vendor/solaris/solaris-laravel-masterdata/src/Models',
        ]);

        expect($result['models'])->toBe([
            'user' => 'App\Models\User',
            'city' => 'Solaris\Core\Models\City',
            'country' => 'Solaris\Core\Models\Country',
            'account' => 'Solaris\MasterData\Models\Account',
            'contact' => 'Solaris\MasterData\Models\Contact',
            'product' => 'Solaris\MasterData\Models\Product',
        ]);

        expect($result['repositories'])->toBe([
            'user' => 'Solaris\Core\Repositories\UserRepository',
            'role' => 'Solaris\Core\Repositories\BaseRepository',
            'account' => 'Solaris\MasterData\Repositories\AccountRepository',
            'contact' => 'Solaris\MasterData\Repositories\ContactRepository',
        ]);

        // Indexed arrays concatenated
        expect($result['controller_namespaces'])->toBe([
            'Solaris\Core\Http\Controllers',
            'Solaris\Core\Http\Controllers\Auth',
            'Solaris\MasterData\Http\Controllers',
        ]);

        expect($result['controllers'])->toBe([
            'login' => 'Solaris\Core\Http\Controllers\Auth\AuthController',
            'user' => 'Solaris\Core\Http\Controllers\UserController',
            'role' => 'Solaris\Core\Http\Controllers\RoleController',
            'account' => 'Solaris\MasterData\Http\Controllers\AccountController',
            'contact' => 'Solaris\MasterData\Http\Controllers\ContactController',
        ]);

        expect($result['form_requests'])->toBe([
            'user' => 'Solaris\Core\Http\Requests\UserRequest',
            'role' => 'Solaris\Core\Http\Requests\RoleRequest',
            'account' => 'Solaris\MasterData\Http\Requests\AccountRequest',
            'contact' => 'Solaris\MasterData\Http\Requests\ContactRequest',
        ]);

        // Nested associative arrays merged
        expect($result['views']['login'])->toBe('solar::pages.auth.login');
        expect($result['views']['user'])->toBe([
            'list' => 'solar::pages.user.list',
            'page' => 'solar::pages.user.page',
        ]);
        expect($result['views']['role'])->toBe([
            'list' => 'solar::pages.role.list',
            'page' => 'solar::pages.role.page',
        ]);
        expect($result['views']['account'])->toBe([
            'list' => 'solar-md::pages.account.list',
            'page' => 'solar-md::pages.account.page',
        ]);
        expect($result['views']['contact'])->toBe([
            'list' => 'solar-md::pages.contact.list',
            'page' => 'solar-md::pages.contact.page',
        ]);

        // TTL preserved
        expect($result['ttl'])->toBe([
            'lookup' => 7200,
            'select' => 7200,
        ]);
    });
});

describe('ConfigEditor Merge - Edge Cases', function () {
    
    it('handles empty base array', function () {
        $base = <<<'PHP'
<?php
return [];
PHP;

        $override = <<<'PHP'
<?php
return [
    'name' => 'App',
    'items' => ['foo'],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result)->toBe([
            'name' => 'App',
            'items' => ['foo'],
        ]);
    });

    it('handles empty override array', function () {
        $base = <<<'PHP'
<?php
return [
    'name' => 'App',
    'items' => ['foo'],
];
PHP;

        $override = <<<'PHP'
<?php
return [];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result)->toBe([
            'name' => 'App',
            'items' => ['foo'],
        ]);
    });

    it('handles scalar to array conversion', function () {
        $base = <<<'PHP'
<?php
return [
    'value' => 'string',
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'value' => ['array', 'values'],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['value'])->toBe(['array', 'values']);
    });

    it('handles array to scalar conversion', function () {
        $base = <<<'PHP'
<?php
return [
    'value' => ['array', 'values'],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'value' => 'string',
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['value'])->toBe('string');
    });

    it('handles numeric string keys in associative arrays', function () {
        $base = <<<'PHP'
<?php
return [
    'mixed' => [
        '0' => 'zero',
        'key' => 'value',
    ],
];
PHP;

        $override = <<<'PHP'
<?php
return [
    'mixed' => [
        '1' => 'one',
        'key' => 'overridden',
    ],
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override.php", $override);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['mixed']['0'])->toBe('zero');
        expect($result['mixed']['1'])->toBe('one');
        expect($result['mixed']['key'])->toBe('overridden');
    });

    it('throws exception when merging non-existent file', function () {
        $base = <<<'PHP'
<?php
return [];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        
        expect(fn() => $editor->merge("{$this->testDir}/nonexistent.php"))
            ->toThrow(\RuntimeException::class, 'Config file not found');
    });

    it('throws exception when merging invalid config file', function () {
        $base = <<<'PHP'
<?php
return [];
PHP;

        $invalid = <<<'PHP'
<?php
echo "not a config";
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/invalid.php", $invalid);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        
        expect(fn() => $editor->merge("{$this->testDir}/invalid.php"))
            ->toThrow(\RuntimeException::class, 'Invalid config file');
    });

    it('handles multiple consecutive merges', function () {
        $base = <<<'PHP'
<?php
return [
    'name' => 'Base',
    'items' => ['a'],
];
PHP;

        $override1 = <<<'PHP'
<?php
return [
    'name' => 'Override1',
    'items' => ['b'],
    'extra' => 'data1',
];
PHP;

        $override2 = <<<'PHP'
<?php
return [
    'items' => ['c'],
    'extra' => 'data2',
    'more' => 'info',
];
PHP;

        file_put_contents("{$this->testDir}/base.php", $base);
        file_put_contents("{$this->testDir}/override1.php", $override1);
        file_put_contents("{$this->testDir}/override2.php", $override2);

        $editor = new ConfigEditor("{$this->testDir}/base.php");
        $editor->merge("{$this->testDir}/override1.php");
        $editor->merge("{$this->testDir}/override2.php");
        $editor->save("{$this->testDir}/result.php");

        $result = require "{$this->testDir}/result.php";

        expect($result['name'])->toBe('Override1');
        expect($result['items'])->toBe(['a', 'b', 'c']);
        expect($result['extra'])->toBe('data2');
        expect($result['more'])->toBe('info');
    });
});