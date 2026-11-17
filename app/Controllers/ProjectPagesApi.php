<?php

namespace App\Controllers;

use App\Services\PublicProjectPages;

final class ProjectPagesApi extends BaseController
{
    private function headers()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Accept, Content-Type')
            ->setHeader('Access-Control-Max-Age', '600')
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function options()
    {
        return $this->headers()->setStatusCode(204)->setBody('');
    }

    public function index(?string $slug = null)
    {
        $response = $this->headers();

        try {
            $pages = new PublicProjectPages(db_connect());
            $documentOnly = $this->request->getGet('format') === 'document';
            $data = $slug === null ? $pages->all($documentOnly) : $pages->find($slug, $documentOnly);

            if ($data === null) {
                return $response->setStatusCode(404)->setJSON(['message' => 'Saved public project page not found.']);
            }

            return $response->setJSON(['data' => $data]);
        } catch (\InvalidArgumentException $error) {
            return $response->setStatusCode(404)->setJSON(['message' => 'Saved public project page not found.']);
        } catch (\Throwable $error) {
            log_message('error', 'Public project API unavailable: exception_type={exception_type}.', ['exception_type' => get_class($error)]);
            return $response->setStatusCode(503)->setJSON(['message' => 'Project pages are temporarily unavailable.']);
        }
    }

    public function image(string $id)
    {
        $response = $this->headers();

        try {
            $file = WRITEPATH . 'uploads/project-editor/' . $id . '.webp';
            if (!preg_match('/\A[a-f0-9]{64}\z/D', $id) || !is_file($file) || !(new PublicProjectPages(db_connect()))->hasImage($id)) {
                return $response->setStatusCode(404)->setJSON(['message' => 'Project image not found.']);
            }

            return $response->setContentType('image/webp')->setBody(file_get_contents($file));
        } catch (\Throwable $error) {
            log_message('error', 'Public project image unavailable: exception_type={exception_type}.', ['exception_type' => get_class($error)]);
            return $response->setStatusCode(503)->setJSON(['message' => 'Project images are temporarily unavailable.']);
        }
    }
}
