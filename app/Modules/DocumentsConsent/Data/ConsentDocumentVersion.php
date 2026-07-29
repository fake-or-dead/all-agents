<?php

namespace App\Modules\DocumentsConsent\Data;

final readonly class ConsentDocumentVersion
{
    public function __construct(
        public string $id,
        public string $title,
        public string $versionLabel,
        public string $locale,
        public string $content,
        public string $checksum,
    ) {}
}
