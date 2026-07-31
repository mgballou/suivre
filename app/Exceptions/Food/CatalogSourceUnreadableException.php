<?php

declare(strict_types=1);

namespace App\Exceptions\Food;

use RuntimeException;

/**
 * Thrown when the catalog import cannot open the export it was pointed at.
 *
 * The dumps this command reads are large, downloaded by hand, and often still
 * being written when someone reaches for them. Failing loudly on the path beats
 * a run that reports zero products and looks like an empty dataset.
 */
class CatalogSourceUnreadableException extends RuntimeException
{
    public static function make(string $path): self
    {
        return new self("The food catalog export at [{$path}] could not be opened for reading.");
    }
}
