<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Security;
use InvalidArgumentException;
final class DocumentValidator {
    private const ALLOWED = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
    /** @param array<string,mixed> $file @return array{original:string,stored:string,mime:string} */
    public function validate(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new InvalidArgumentException('Document upload failed.');
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 5_242_880) throw new InvalidArgumentException('Documents must be at most 5 MiB.');
        $original = basename((string)($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime]) || !in_array($extension, self::ALLOWED[$mime], true)) throw new InvalidArgumentException('Only genuine PDF, JPG, and PNG documents are allowed.');
        return ['original' => $original, 'stored' => bin2hex(random_bytes(24)) . '.' . $extension, 'mime' => $mime];
    }
}
