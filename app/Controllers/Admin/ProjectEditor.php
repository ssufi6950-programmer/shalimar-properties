<?php

namespace App\Controllers\Admin;

use App\Services\ProjectPageEditor;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

final class ProjectEditor extends \App\Controllers\BaseController
{
    protected $helpers = ['url', 'form', 'admin'];

    public function index()
    {
        if (!can('projects.view')) {
            return admin_denied();
        }
        return view('admin/projects/index', [
            'pageTitle' => 'Projects',
            'tab' => 'projects',
            'rows' => (new ProjectPageEditor(db_connect()))->rows(),
        ]);
    }

    public function edit()
    {
        $id = $this->request->getGet('id');
        if ($id !== null && (!is_string($id) || $id === '')) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (!can('projects.view') || !can($id === null ? 'projects.create' : 'projects.edit')) {
            return admin_denied();
        }
        $editor = new ProjectPageEditor(db_connect());
        try {
            $record = $id === null ? null : $editor->get($id);
        } catch (InvalidArgumentException $error) {
            throw PageNotFoundException::forPageNotFound();
        }
        return view('admin/projects/editor', [
            'pageTitle' => $record ? 'Edit Project' : 'Add Project',
            'tab' => 'projects',
            'record' => $record,
            'templates' => $editor->templates(),
        ]);
    }

    private function json(array $body, int $status = 200)
    {
        $body['csrf'] = ['name' => csrf_token(), 'hash' => csrf_hash()];
        return $this->response->setHeader('Cache-Control', 'no-store, private')->setStatusCode($status)->setJSON($body);
    }

    public static function imageUrl(string $reference): string
    {
        if (str_starts_with($reference, 'https://')) {
            return $reference;
        }
        return str_starts_with($reference, 'project-image:')
            ? site_url('admin/project-editor/images/' . substr($reference, 14))
            : ($reference === '' ? '' : admin_legacy_url($reference));
    }

    public function save()
    {
        if (!can('projects.view')) {
            return admin_denied();
        }
        try {
            if (strlen($this->request->getBody()) > 262144) {
                throw new InvalidArgumentException('This project is too large to save.');
            }
            $input = $this->request->getJSON(true);
            if (!is_array($input) || array_is_list($input)) {
                throw new InvalidArgumentException('Send a project document.');
            }
            $id = $input['id'] ?? null;
            if ($id !== null && !is_string($id)) {
                throw new InvalidArgumentException('Invalid project.');
            }
            if (!can($id === null ? 'projects.create' : 'projects.edit')) {
                return admin_denied();
            }
            $result = (new ProjectPageEditor(db_connect()))->save(
                $id,
                $input,
                (int) admin_user()['id'],
                static fn (array $document, string $template) => view('admin/projects/preview', [
                    'document' => $document,
                    'template' => $template,
                    'editing' => false,
                    'imageUrl' => [\App\Services\ProjectPageAssets::class, 'publicImageUrl'],
                    'linkUrl' => [\App\Services\ProjectPageAssets::class, 'publicLink'],
                ])
            );
            return $this->json($result + ['message' => 'Project saved.', 'editUrl' => site_url('admin/workspace/project-form') . '?id=' . rawurlencode($result['slug'])]);
        } catch (\App\Exceptions\CustomerFlowException $error) {
            return $this->json(['message' => $error->getMessage()], 409);
        } catch (InvalidArgumentException | \JsonException $error) {
            return $this->json(['message' => $error instanceof \JsonException ? 'Invalid project JSON.' : $error->getMessage()], 422);
        } catch (\Throwable $error) {
            log_message('error', 'Project editor save failed: exception_type={exception_type}.', ['exception_type' => get_class($error)]);
            return $this->json(['message' => 'The project could not be saved. Please try again.'], 503);
        }
    }

    public function upload()
    {
        if (!can('projects.view') || (!can('projects.create') && !can('projects.edit'))) {
            return admin_denied();
        }
        try {
            $file = $this->request->getFile('image');
            if (!$file || !$file->isValid() || $file->getSize() > 5 * 1024 * 1024 || !in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new InvalidArgumentException('Choose a JPEG, PNG or WebP image up to 5 MB.');
            }
            $info = getimagesize($file->getTempName());
            if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 20000000) {
                throw new InvalidArgumentException('The image must contain no more than 20 million pixels.');
            }
            $image = imagecreatefromstring(file_get_contents($file->getTempName()));
            if (!$image) {
                throw new InvalidArgumentException('This image could not be read.');
            }
            $directory = WRITEPATH . 'uploads/project-editor/';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new \RuntimeException('Image storage is unavailable.');
            }
            $id = bin2hex(random_bytes(32));
            try {
                if (!imagewebp($image, $directory . $id . '.webp', 88)) {
                    throw new \RuntimeException('Image could not be saved.');
                }
            } finally {
                imagedestroy($image);
            }
            return $this->json(['reference' => 'project-image:' . $id, 'url' => site_url('admin/project-editor/images/' . $id)], 201);
        } catch (InvalidArgumentException $error) {
            return $this->json(['message' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            log_message('error', 'Project image upload failed: exception_type={exception_type}.', ['exception_type' => get_class($error)]);
            return $this->json(['message' => 'Image upload could not finish. Check the image size and server upload settings.'], 503);
        }
    }

    public function image(string $id)
    {
        if (!can('projects.view')) {
            return admin_denied();
        }
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $id) || !is_file(WRITEPATH . 'uploads/project-editor/' . $id . '.webp')) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setContentType('image/webp')
            ->setBody(file_get_contents(WRITEPATH . 'uploads/project-editor/' . $id . '.webp'));
    }
}
