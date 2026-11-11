<?php
namespace App\Commands;
class ResalePrunePhotos extends \CodeIgniter\CLI\BaseCommand
{
 protected $group='Customers';protected $name='resale:prune-photos';protected $description='Remove a bounded batch of expired, uncommitted private resale photos.';
 public function run(array $params){$n=(new \App\Services\ResalePhotos(db_connect()))->prune();\CodeIgniter\CLI\CLI::write($n.' expired private photos removed; committed listing photos preserved.');}
}
