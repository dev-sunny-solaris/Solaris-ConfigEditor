<?php

namespace Solaris\ConfigEditor;

use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

class ConfigEditor
{
    protected string $path;
    protected array $ast;
    protected Array_ $root;

    public function __construct(string $path)
    {
        // Jika path tidak memiliki extension .php, tambahkan
        $this->path = str_ends_with($path, '.php') ? $path : "$path.php";

        if (! file_exists($this->path)) {
            throw new \RuntimeException("Config file not found: {$this->path}");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->ast = $parser->parse(file_get_contents($this->path));

        foreach ($this->ast as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                $this->root = $stmt->expr;
                return;
            }
        }

        throw new \RuntimeException('Invalid config file (no return array)');
    }

    public function set(string $key, mixed $value): self
    {
        [$array, $leaf] = $this->resolvePath($key, create: true);

        $item = $this->findItem($array, $leaf);

        if ($item) {
            $item->value = $this->toNode($value);
        } else {
            $array->items[] = new ArrayItem(
                $this->toNode($value),
                new String_($leaf)
            );
        }

        return $this;
    }

    public function add(string $key, mixed $value): self
    {
        [$array, $leaf] = $this->resolvePath($key, create: true);

        if ($this->findItem($array, $leaf)) {
            throw new \RuntimeException("Key already exists: $key");
        }

        $array->items[] = new ArrayItem(
            $this->toNode($value),
            new String_($leaf)
        );

        return $this;
    }

    public function setRaw(string $key, string $code): self
    {
        [$array, $leaf] = $this->resolvePath($key, create: true);

        $node = $this->parseCode($code);
        $item = $this->findItem($array, $leaf);

        if ($item) {
            $item->value = $node;
        } else {
            $array->items[] = new ArrayItem(
                $node,
                new String_($leaf)
            );
        }

        return $this;
    }

    public function addRaw(string $key, string $code): self
    {
        [$array, $leaf] = $this->resolvePath($key, create: true);

        if ($this->findItem($array, $leaf)) {
            throw new \RuntimeException("Key already exists: $key");
        }

        $node = $this->parseCode($code);
        $array->items[] = new ArrayItem(
            $node,
            new String_($leaf)
        );

        return $this;
    }

    public function push(string $key, mixed $value): self
    {
        [$array, $leaf] = $this->resolvePath($key);

        $item = $this->findItem($array, $leaf);

        if (! $item) {
            throw new \RuntimeException("Key not found: $key");
        }

        if (! $item->value instanceof Array_) {
            throw new \RuntimeException("Key is not array: $key");
        }

        $item->value->items[] = new ArrayItem(
            $this->toNode($value)
        );

        return $this;
    }

    public function pushRaw(string $key, string $code): self
    {
        [$array, $leaf] = $this->resolvePath($key);

        $item = $this->findItem($array, $leaf);

        if (! $item) {
            throw new \RuntimeException("Key not found: $key");
        }

        if (! $item->value instanceof Array_) {
            throw new \RuntimeException("Key is not array: $key");
        }

        $node = $this->parseCode($code);
        $item->value->items[] = new ArrayItem($node);

        return $this;
    }

    public function delete(string $key): void
    {
        [$array, $leaf] = $this->resolvePath($key, silent: true);

        if (!$array) {
            return;
        }

        $newItems = [];
        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $leaf) {
                continue;
            }
            $newItems[] = $item;
        }
        
        $array->items = $newItems;
    }

    public function has(string $key): bool
    {
        [$array, $leaf] = $this->resolvePath($key, silent: true);
        
        if (!$array) {
            return false;
        }
        
        return $this->findItem($array, $leaf) !== null;
    }

    public function merge(string $path): self
    {
        // Jika path tidak memiliki extension .php, tambahkan
        $fullPath = str_ends_with($path, '.php') ? $path : "$path.php";

        if (! file_exists($fullPath)) {
            throw new \RuntimeException("Config file not found: {$fullPath}");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse(file_get_contents($fullPath));

        foreach ($ast as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                $this->mergeArrays($this->root, $stmt->expr);
                return $this;
            }
        }

        throw new \RuntimeException('Invalid config file (no return array)');
    }

    protected function mergeArrays(Array_ $target, Array_ $source): void
    {
        foreach ($source->items as $sourceItem) {
            // Skip items without string keys (shouldn't happen at top level, but safe guard)
            if (!$sourceItem->key instanceof String_) {
                continue;
            }

            $key = $sourceItem->key->value;
            $targetItem = $this->findItem($target, $key);

            if (!$targetItem) {
                // Key tidak ada di target, tambahkan apa adanya
                $target->items[] = new ArrayItem(
                    $this->cloneNode($sourceItem->value),
                    new String_($key)
                );
            } elseif ($sourceItem->value instanceof Array_ && $targetItem->value instanceof Array_) {
                // Kedua value adalah array, merge dengan smart logic
                $this->mergeArrayItems($targetItem->value, $sourceItem->value);
            } else {
                // Scalar: override wins
                $targetItem->value = $this->cloneNode($sourceItem->value);
            }
        }
    }

    protected function mergeArrayItems(Array_ $target, Array_ $source): void
    {
        // Deteksi apakah ini indexed atau associative array
        $isIndexed = $this->isIndexedArray($source) && $this->isIndexedArray($target);
        
        if ($isIndexed) {
            // Indexed array: deduplicate & append
            foreach ($source->items as $sourceItem) {
                $found = false;
                
                foreach ($target->items as $targetItem) {
                    if ($this->nodesEqual($targetItem->value, $sourceItem->value)) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $target->items[] = new ArrayItem(
                        $this->cloneNode($sourceItem->value)
                    );
                }
            }
        } else {
            // Associative array: recursive merge per key
            foreach ($source->items as $sourceItem) {
                // Jika item tidak punya key (indexed item di assoc array), skip
                if (!$sourceItem->key instanceof String_) {
                    // Tambahkan sebagai indexed item
                    $target->items[] = new ArrayItem(
                        $this->cloneNode($sourceItem->value),
                        null
                    );
                    continue;
                }
                
                $key = $sourceItem->key->value;
                $targetItem = $this->findItem($target, $key);
                
                if (!$targetItem) {
                    // Key belum ada, tambahkan
                    $target->items[] = new ArrayItem(
                        $this->cloneNode($sourceItem->value),
                        new String_($key)
                    );
                } elseif ($sourceItem->value instanceof Array_ && $targetItem->value instanceof Array_) {
                    // Kedua array, recursive merge
                    $this->mergeArrayItems($targetItem->value, $sourceItem->value);
                } else {
                    // Scalar override
                    $targetItem->value = $this->cloneNode($sourceItem->value);
                }
            }
        }
    }

    protected function isIndexedArray(Array_ $array): bool
    {
        if (empty($array->items)) {
            return true;
        }
        
        // Indexed array = semua items tidak punya key atau key numerik sequential
        foreach ($array->items as $item) {
            // Jika ada string key, bukan indexed
            if ($item->key instanceof String_) {
                return false;
            }
            
            // Jika key adalah expression lain selain null atau int literal, anggap assoc
            if ($item->key !== null && !$item->key instanceof Node\Scalar\Int_) {
                return false;
            }
        }
        
        return true;
    }

    protected function nodesEqual(Node $node1, Node $node2): bool
    {
        $printer = new ConfigPrinter();
        return $printer->prettyPrint([$node1]) === $printer->prettyPrint([$node2]);
    }

    protected function cloneNode(Node $node): Node
    {
        // Deep clone untuk avoid reference issues
        return clone $node;
    }

    public function save(?string $filename = null): self
    {
        $printer = new ConfigPrinter();
        
        file_put_contents(
            $filename ?? $this->path,
            $printer->prettyPrintFile($this->ast)
        );

        return $this;
    }

    protected function resolvePath(string $key, bool $create = false, bool $silent = false): array 
    {
        $segments = explode('.', $key);
        $current = $this->root;

        foreach (array_slice($segments, 0, -1) as $segment) {
            $item = $this->findItem($current, $segment);

            if (! $item) {
                if (! $create) {
                    if ($silent) {
                        return [null, null];
                    }
                    throw new \RuntimeException("Path not found: $key");
                }

                $child = new Array_([]);
                $current->items[] = new ArrayItem(
                    $child,
                    new String_($segment)
                );
                $current = $child;
                continue;
            }

            if (! $item->value instanceof Array_) {
                throw new \RuntimeException("Path is not array: $segment");
            }

            $current = $item->value;
        }

        return [$current, end($segments)];
    }

    protected function findItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }

    protected function toNode(mixed $value): Node\Expr
    {
        if (is_string($value)) {
            return new String_($value);
        }

        if (is_int($value)) {
            return new Node\Scalar\Int_($value);
        }

        if (is_float($value)) {
            return new Node\Scalar\Float_($value);
        }

        if (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        }

        if ($value === null) {
            return new ConstFetch(new Name('null'));
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $k => $v) {
                $items[] = new ArrayItem(
                    $this->toNode($v),
                    is_string($k) ? new String_($k) : null
                );
            }

            return new Array_($items);
        }

        throw new \RuntimeException('Unsupported value type');
    }

    protected function parseCode(string $code): Node\Expr
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        
        // Wrap code sebagai expression statement untuk di-parse
        $ast = $parser->parse("<?php $code;");

        if (empty($ast)) {
            throw new \RuntimeException('Invalid code expression');
        }

        $stmt = $ast[0];

        if ($stmt instanceof Node\Stmt\Expression) {
            return $stmt->expr;
        }

        throw new \RuntimeException('Code must be a valid expression');
    }
}