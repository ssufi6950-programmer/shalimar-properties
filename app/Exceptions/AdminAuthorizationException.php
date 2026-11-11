<?php
namespace App\Exceptions;

/** An authenticated identity attempted an operation outside its permitted scope. */
class AdminAuthorizationException extends \DomainException {}
