<?php

declare(strict_types=1);

namespace App\Ingestion\Upload;

use App\Ingestion\IngestionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploadValidatorInterface
{
    /**
     * @throws IngestionException
     */
    public function validate(UploadedFile $upload): ValidatedUpload;
}
