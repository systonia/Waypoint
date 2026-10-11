<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Post};
use Waypoint\Http\Request;

/** Echoes what arrived of a multipart form: the fields, the files, and the content of one moved to disk. */
#[Controller('/upload')]
class UploadController
{
    /** @return array<string, mixed> */
    #[Post('')]
    public function upload(Request $req): array
    {
        $doc = $req->file('doc');
        $moved = null;
        if ($doc !== null) {
            $target = sys_get_temp_dir() . '/waypoint-upload-' . bin2hex(random_bytes(6));
            $doc->moveTo($target);
            $moved = file_get_contents($target);
            unlink($target);
        }
        $photos = $req->files['photos'] ?? [];
        return [
            'title' => $req->body('title'),
            'doc' => $doc !== null ? [$doc->name, $doc->type, $doc->size] : null,
            'moved' => $moved,
            'photos' => is_array($photos) ? count($photos) : 0,
        ];
    }
}
