<?php
namespace App\Controllers;
final class ResaleMedia extends BaseController
{
 public function show(string $id)
 {
  $this->response->setHeader('Cache-Control','no-store')->setHeader('X-Content-Type-Options','nosniff');
  try{$file=(new \App\Services\ResalePhotos(db_connect()))->publicFile($id);return $this->response->setHeader('Cache-Control','no-store')->setHeader('X-Content-Type-Options','nosniff')->setContentType($file['mime'])->setBody(file_get_contents($file['path']));}catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode(404)->setBody('Image unavailable.');}
 }
}
