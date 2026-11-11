<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Exceptions\PageNotFoundException;

final class CustomerDemoOnly implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!config('CustomerPortal')->enabled) throw PageNotFoundException::forPageNotFound();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store');
    }
}
