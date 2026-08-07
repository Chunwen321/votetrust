<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Security;
use InvalidArgumentException;
final class ProfilePhotoValidator {
    private const ALLOWED = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
    /** @param array<string,mixed> $file @return array{name:string,mime:string} */
    public function validate(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new InvalidArgumentException('A candidate photo is required.');
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 2_097_152) throw new InvalidArgumentException('Candidate photo must be at most 2 MiB.');
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime]) || !in_array($extension, self::ALLOWED[$mime], true)) throw new InvalidArgumentException('Candidate photo must be a genuine JPG or PNG image.');
        return ['name' => bin2hex(random_bytes(24)) . '.' . $extension, 'mime' => $mime];
    }
}
