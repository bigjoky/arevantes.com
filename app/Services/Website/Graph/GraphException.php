<?php

namespace App\Services\Website\Graph;

use RuntimeException;

final class GraphException extends RuntimeException
{
    public function __construct(public readonly string $category)
    {
        parent::__construct('Microsoft Graph no ha confirmado la operación: '.$category);
    }
}
