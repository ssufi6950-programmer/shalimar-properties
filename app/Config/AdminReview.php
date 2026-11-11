<?php
namespace Config;

/** Temporary manual approval workflow requested by the administrator. */
class AdminReview extends \CodeIgniter\Config\BaseConfig
{
 public bool $requireOwnershipVerification=false;
}
