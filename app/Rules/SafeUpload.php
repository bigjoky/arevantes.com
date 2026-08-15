<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Throwable;
use ZipArchive;

class SafeUpload implements ValidationRule
{
    /** @param array<int, string> $extensions */
    public function __construct(private readonly array $extensions, private readonly ?int $maxKilobytes = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('The :attribute must be a valid file.'));

            return;
        }
        $extension = strtolower($value->getClientOriginalExtension());
        if (! in_array($extension, $this->extensions, true)) {
            $fail(__('The :attribute file type is not allowed.'));

            return;
        }
        $mimeTypes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
            'md' => ['text/plain'],
            'doc' => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        $allowedMimes = collect($this->extensions)->flatMap(fn (string $type) => $mimeTypes[$type] ?? [])->unique();
        if ($allowedMimes->isNotEmpty() && ! $allowedMimes->contains($value->getMimeType())) {
            $fail(__('The :attribute contents do not match its allowed file type.'));

            return;
        }
        if ($extension === 'doc' && ! $this->hasOleHeader($value)) {
            $fail(__('The :attribute contents are not a valid Word document.'));

            return;
        }
        if ($extension === 'docx' && ! $this->isValidDocx($value)) {
            $fail(__('The :attribute contents are not a valid Word document.'));

            return;
        }
        $limit = $this->maxKilobytes ?? (int) config('security.uploads.max_kilobytes', 15360);
        if ($value->getSize() > $limit * 1024) {
            $fail(__('The :attribute may not be greater than :max kilobytes.', ['max' => $limit]));

            return;
        }
        $socket = config('security.uploads.clamav_socket');
        if ($socket && ! $this->isClean($value, $socket)) {
            $fail(__('The :attribute failed the antivirus scan.'));
        }
    }

    private function isClean(UploadedFile $file, string $socket): bool
    {
        try {
            $connection = stream_socket_client(str_contains($socket, '://') ? $socket : 'unix://'.$socket, $error, $message, 5);
            if (! $connection) {
                return false;
            }
            fwrite($connection, "zINSTREAM\0");
            $handle = fopen($file->getRealPath(), 'rb');
            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                fwrite($connection, pack('N', strlen($chunk)).$chunk);
            }
            fwrite($connection, pack('N', 0));
            $response = stream_get_contents($connection);
            fclose($handle);
            fclose($connection);

            return str_contains($response, 'OK') && ! str_contains($response, 'FOUND');
        } catch (Throwable) {
            return false;
        }
    }

    private function hasOleHeader(UploadedFile $file): bool
    {
        $header = file_get_contents($file->getRealPath(), false, null, 0, 8);

        return $header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    }

    private function isValidDocx(UploadedFile $file): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $archive = new ZipArchive;
        if ($archive->open($file->getRealPath()) !== true) {
            return false;
        }

        try {
            return $archive->locateName('[Content_Types].xml') !== false
                && $archive->locateName('word/document.xml') !== false;
        } finally {
            $archive->close();
        }
    }
}
