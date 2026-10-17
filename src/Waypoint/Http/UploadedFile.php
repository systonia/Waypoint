<?php

namespace Waypoint\Http;

use Closure;
use RuntimeException;

/**
 * A file from a multipart/form-data request, the same under FPM, Tenma and any PSR-7 server:
 * `$req->file('picture')?->moveTo($dir . '/' . bin2hex(random_bytes(8)))`.
 *
 * Name, type and size come from the client: never use the name unchecked as a path, and check the content
 * itself (finfo, getimagesizefromstring()) before trusting the type.
 */
final class UploadedFile
{
    private bool $moved = false;

    /**
     * For a runtime that hands files over itself; fromString() covers a file held in memory. Request builds PHP's
     * own uploads ($_FILES) itself.
     *
     * @param Closure(): string $contents
     * @param Closure(string): void $moveTo
     * @param int $error One of PHP's UPLOAD_ERR_* constants.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly int $size,
        private readonly Closure $contents,
        private readonly Closure $moveTo,
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
    }

    /** A file held in memory, e.g. from a test or a WebSocket message. */
    public static function fromString(string $name, string $type, string $contents): self
    {
        return new self(
            $name,
            $type,
            strlen($contents),
            static fn (): string => $contents,
            static function (string $path) use ($contents): void {
                if (file_put_contents($path, $contents) === false) {
                    throw new RuntimeException("Could not write the upload to '$path'");
                }
            },
        );
    }

    /** False when the upload failed (too big, partial, ...); see $error. */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    public function contents(): string
    {
        $this->assertUsable();
        return ($this->contents)();
    }

    /** Writes the file to $path; only once. */
    public function moveTo(string $path): void
    {
        $this->assertUsable();
        ($this->moveTo)($path);
        $this->moved = true;
    }

    private function assertUsable(): void
    {
        if (!$this->isValid()) {
            throw new RuntimeException("The upload of '{$this->name}' failed (UPLOAD_ERR {$this->error})");
        }
        if ($this->moved) {
            throw new RuntimeException("The upload of '{$this->name}' was already moved");
        }
    }
}
