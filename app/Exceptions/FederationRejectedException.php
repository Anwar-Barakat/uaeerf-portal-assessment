<?php

namespace App\Exceptions;

/**
 * The federation understood the submission and refused it.
 *
 * Distinct from a transport failure: retrying will produce the same answer, so
 * the fulfilment job must stop rather than burn its attempts.
 */
class FederationRejectedException extends \RuntimeException
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(public readonly array $messages)
    {
        parent::__construct('SOAP registration rejected: ' . implode('; ', $messages));
    }
}
