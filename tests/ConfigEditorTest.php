<?php

namespace Solaris\ConfigEditor\Tests;

use PHPUnit\Framework\TestCase;
use Solaris\ConfigEditor\ConfigEditor;
use RuntimeException;

class ConfigEditorTest extends TestCase
{
    private string $testConfigPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary test config file
        $this->testConfigPath = sys_get_temp_dir() . '/test-config-' . uniqid() . '.php';
        
        file_put_contents($this->testConfigPath, <<<'PHP'
<?php

return [
    'app' => [
        'name' => 'Test App',
        'debug' => true,
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
    ],
];
PHP
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        if (file_exists($this->testConfigPath)) {
            unlink($this->testConfigPath);
        }
    }

    public function test_can_create_instance(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        $this->assertInstanceOf(ConfigEditor::class, $config);
    }

    public function test_throws_exception_for_missing_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');
        
        new ConfigEditor('/non/existent/path.php');
    }

    public function test_can_add_php_extension(): void
    {
        $path = $this->testConfigPath;
        $pathWithoutExt = substr($path, 0, -4); // Remove .php
        
        $config = new ConfigEditor($pathWithoutExt);
        $this->assertInstanceOf(ConfigEditor::class, $config);
    }

    public function test_can_set_value(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        $config->set('app.name', 'Updated App');
        $config->save();
        
        $config2 = new ConfigEditor($this->testConfigPath);
        $this->assertTrue($config2->has('app.name'));
    }

    public function test_can_check_key_exists(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        
        $this->assertTrue($config->has('app.name'));
        $this->assertFalse($config->has('app.non_existent'));
    }

    public function test_can_add_new_key(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        $config->add('cache.driver', 'redis');
        $config->save();
        
        $config2 = new ConfigEditor($this->testConfigPath);
        $this->assertTrue($config2->has('cache.driver'));
    }

    public function test_add_throws_for_existing_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Key already exists');
        
        $config = new ConfigEditor($this->testConfigPath);
        $config->add('app.name', 'Another Value');
    }

    public function test_can_delete_key(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        $this->assertTrue($config->has('app.name'));
        
        $config->delete('app.name');
        $config->save();
        
        $config2 = new ConfigEditor($this->testConfigPath);
        $this->assertFalse($config2->has('app.name'));
    }

    public function test_can_merge_configs(): void
    {
        // Create a merge config file
        $mergeConfigPath = sys_get_temp_dir() . '/merge-config-' . uniqid() . '.php';
        file_put_contents($mergeConfigPath, <<<'PHP'
<?php

return [
    'app' => [
        'version' => '2.0.0',
    ],
    'new_section' => [
        'key' => 'value',
    ],
];
PHP
        );

        try {
            $config = new ConfigEditor($this->testConfigPath);
            $config->merge($mergeConfigPath);
            $config->save();
            
            $config2 = new ConfigEditor($this->testConfigPath);
            $this->assertTrue($config2->has('new_section.key'));
        } finally {
            if (file_exists($mergeConfigPath)) {
                unlink($mergeConfigPath);
            }
        }
    }

    public function test_fluent_interface(): void
    {
        $config = new ConfigEditor($this->testConfigPath);
        
        $result = $config
            ->set('app.url', 'http://example.com')
            ->set('app.timezone', 'UTC');
        
        $this->assertInstanceOf(ConfigEditor::class, $result);
    }
}
