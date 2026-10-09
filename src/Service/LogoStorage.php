<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\ByteString;

/**
 * Logos get a random file name, so a client's name never shows up in a URL.
 * Replaced logos are kept: signatures pasted earlier still point to them.
 */
final class LogoStorage
{
    public const PUBLIC_DIR = 'uploads/logos';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/'.self::PUBLIC_DIR)] private readonly string $directory,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $filename = ByteString::fromRandom(16, 'abcdefghijklmnopqrstuvwxyz0123456789')->toString()
            .'.'.($file->guessExtension() ?? 'png');
        $file->move($this->directory, $filename);

        return $filename;
    }
}
