<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class IssuedDocumentUnavailable extends HttpException
{
    public function __construct(public readonly string $custodyStatus)
    {
        parent::__construct(409, $custodyStatus === 'integrity_failed'
            ? 'El archivo original no supera la comprobación de integridad. Solicita su revisión; no se generará un sustituto.'
            : 'Documento histórico sin original localizado. Solicita su revisión; no se generará un sustituto.');
    }
}
