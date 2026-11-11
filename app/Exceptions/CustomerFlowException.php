<?php
namespace App\Exceptions;
final class CustomerFlowException extends \RuntimeException
{
 public function __construct(public readonly int $httpStatus,string $message,public readonly string $reason='verification_failed'){parent::__construct($message);}
}
