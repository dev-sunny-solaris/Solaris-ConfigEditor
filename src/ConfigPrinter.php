<?php

namespace Solaris\ConfigEditor;

use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Array_;

class ConfigPrinter extends Standard
{
    public function __construct(array $options = [])
    {
        parent::__construct(array_merge([
            'shortArraySyntax' => true,
        ], $options));
    }

    /**
     * ALWAYS use double quotes untuk konsistensi
     * Ini menghindari masalah escaping backslash yang tidak konsisten
     */
    protected function pScalar_String(String_ $node): string
    {
        // Escape karakter yang harus di-escape di double quote
        // \\ -> backslash
        // \" -> double quote
        // \$ -> dollar sign (untuk avoid variable interpolation)
        // \n, \r, \t, \f, \v -> special whitespace chars
        $escaped = addcslashes($node->value, "\\\"\$\n\r\t\f\v");
        
        return '"' . $escaped . '"';
    }

    /**
     * Custom array formatting dengan proper indentation
     */
    protected function pExpr_Array(Array_ $node): string
    {
        if (empty($node->items)) {
            return '[]';
        }
        
        $items = [];
        foreach ($node->items as $item) {
            $items[] = $this->p($item);
        }
        
        return '[' . $this->nl
            . $this->indentString(implode(',' . $this->nl, $items))
            . ',' . $this->nl
            . ']';
    }
    
    /**
     * Indent setiap line dengan 4 spasi
     */
    protected function indentString(string $str): string
    {
        return preg_replace('/^/m', '    ', $str);
    }
}