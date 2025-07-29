<?php

declare(strict_types=1);

namespace Synchrenity\Media;

class SynchrenityMediaManager
{
    protected $auditTrail;
    protected $storagePath  = __DIR__ . '/uploads';
    protected $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    protected $maxSize      = 10 * 1024 * 1024; // 10MB
    protected $hooks        = [];

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    public function setStoragePath($path)
    {
        $this->storagePath = $path;
    }
    public function setAllowedTypes($types)
    {
        $this->allowedTypes = $types;
    }
    public function setMaxSize($bytes)
    {
        $this->maxSize = $bytes;
    }
    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
    }

    // Secure file upload
    public function upload($file)
    {
        // $file: ['name'=>..., 'tmp_name'=>..., 'type'=>..., 'size'=>...]
        if (!is_array($file) || !isset($file['tmp_name'], $file['name'], $file['type'], $file['size'])) {
            $this->audit('upload_failed', null, ['reason' => 'invalid_file_array']);

            return false;
        }

        if ($file['size'] > $this->maxSize) {
            $this->audit('upload_failed', null, ['reason' => 'file_too_large', 'size' => $file['size']]);

            return false;
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            $this->audit('upload_failed', null, ['reason' => 'invalid_type', 'type' => $file['type']]);

            return false;
        }

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        $hash     = sha1_file($file['tmp_name']);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $hash . '.' . $ext;
        $dest     = $this->storagePath . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->audit('upload_failed', null, ['reason' => 'move_failed']);

            return false;
        }
        $meta = [
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'hash' => $hash,
            'path' => $dest,
        ];

        foreach ($this->hooks as $hook) {
            call_user_func($hook, $meta);
        }
        $this->audit('upload_media', null, $meta);

        return $meta;
    }

    // Retrieve file metadata
    public function getMetadata($hash)
    {
        $files = glob($this->storagePath . '/' . $hash . '.*');

        if (!$files) {
            return null;
        }
        $file = $files[0];

        return [
            'name' => basename($file),
            'type' => mime_content_type($file),
            'size' => filesize($file),
            'hash' => $hash,
            'path' => $file,
        ];
    }

    // Download file
    public function download($hash)
    {
        $meta = $this->getMetadata($hash);

        if (!$meta) {
            return null;
        }
        $this->audit('download_media', null, $meta);

        return file_get_contents($meta['path']);
    }

    // Delete file
    public function delete($hash)
    {
        $meta = $this->getMetadata($hash);

        if (!$meta) {
            return false;
        }

        if (unlink($meta['path'])) {
            $this->audit('delete_media', null, $meta);

            return true;
        }

        return false;
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = [])
    {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
