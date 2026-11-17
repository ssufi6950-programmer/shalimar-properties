<?php
declare(strict_types=1);
namespace App\Exceptions;

/** Safe, deliberately public operational message; no provider body/credentials. */
final class ExplorerMetadataException extends \RuntimeException {}
