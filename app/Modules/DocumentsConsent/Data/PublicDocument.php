<?php

namespace App\Modules\DocumentsConsent\Data;

final readonly class PublicDocument
{
    public function __construct(
        public string $key,
        public string $title,
        public int $version,
        public string $checksum,
        public string $disposition,
    ) {}
}
