<?php

return [
    'name' => 'AssetRegister',
    'providers' => [
        Barryvdh\DomPDF\ServiceProvider::class,
        ],
    'aliases' => [
            'PDFOptiont' => Barryvdh\DomPDF\Facade::class,
        ],
];

