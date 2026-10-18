<?php

require __DIR__ . '/../vendor/autoload.php';

// Tenma declares Tenma\Lsp\Provider at runtime; the tests run under plain PHP
if (!interface_exists(\Tenma\Lsp\Provider::class)) {
    require __DIR__ . '/Stubs/Tenma/Lsp/Provider.php';
    require __DIR__ . '/Stubs/Tenma/Lsp/Tokens.php';
    require __DIR__ . '/Stubs/Tenma/Lsp/Imports.php';
    require __DIR__ . '/Stubs/Tenma/Lsp/Variables.php';
    require __DIR__ . '/Stubs/Tenma/Lsp/Elements.php';
}
