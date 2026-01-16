<?php

namespace App\Service;

use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{
    private BarcodeGeneratorPNG $generator;

    public function __construct(?BarcodeGeneratorPNG $generator = null)
    {
        $this->generator = $generator ?? new BarcodeGeneratorPNG();
    }

    public function renderPng(string $code, string $type = 'code128'): string
    {
        $typeConst = $this->mapType($type);
        return $this->generator->getBarcode($code, $typeConst);
    }

    private function mapType(string $type): string
    {
        $normalized = strtolower($type);

        switch ($normalized) {
            case 'ean13':
            case 'ean-13':
                return $this->generator::TYPE_EAN_13;
            case 'ean8':
            case 'ean-8':
                return $this->generator::TYPE_EAN_8;
            case 'upc':
            case 'upc-a':
                return $this->generator::TYPE_UPC_A;
            case 'code39':
            case 'code-39':
                return $this->generator::TYPE_CODE_39;
            case 'itf':
            case 'itf-14':
                return $this->generator::TYPE_ITF_14;
            case 'codabar':
                return $this->generator::TYPE_CODABAR;
            case 'code128':
            default:
                return $this->generator::TYPE_CODE_128;
        }
    }
}
