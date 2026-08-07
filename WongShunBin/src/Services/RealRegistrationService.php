<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Services;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use VoteTrust\Candidate\Contracts\RegistrationService;
use VoteTrust\Candidate\Security\DocumentValidator;
use VoteTrust\Candidate\Security\ProfilePhotoValidator;
final class RealRegistrationService implements RegistrationService {
    public function __construct(private PDO $pdo, private DocumentValidator $documents, private ProfilePhotoValidator $photos, private string $directory) {}
    public function apply(int $userId, int $electionId, array $input, array $files, array $photo): int {
        $election = $this->pdo->prepare('SELECT organizer_id, status, nomination_start, nomination_end FROM election WHERE id = ?'); $election->execute([$electionId]); $row = $election->fetch(PDO::FETCH_ASSOC); $now = new DateTimeImmutable();
        if (!is_array($row) || $row['status'] !== 'NOMINATION_OPEN' || ($row['nomination_start'] && $now < new DateTimeImmutable($row['nomination_start'])) || ($row['nomination_end'] && $now > new DateTimeImmutable($row['nomination_end']))) throw new InvalidArgumentException('This election is not accepting nominations.');
        $this->pdo->prepare('INSERT IGNORE INTO candidate (id) VALUES (?)')->execute([$userId]);
        $exists = $this->pdo->prepare('SELECT id, status FROM candidacy WHERE candidate_id = ? AND election_id = ?');
        $exists->execute([$userId, $electionId]); $previous = $exists->fetch(PDO::FETCH_ASSOC);
        if (is_array($previous) && $previous['status'] !== 'Cancelled') throw new InvalidArgumentException('You already applied for this election.');
        $profilePhoto = $this->photos->validate($photo); $stored = []; $this->pdo->beginTransaction();
        try {
            $photoDirectory = $this->directory . '/photos';
            if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0700, true)) throw new RuntimeException('Cannot create private image storage.');
            $photoPath = $photoDirectory . '/' . $profilePhoto['name']; if (!move_uploaded_file((string)$photo['tmp_name'], $photoPath)) throw new RuntimeException('Cannot save candidate photo.'); $stored[] = $photoPath;
            $payment = $this->pdo->prepare('INSERT INTO payment (amount, status, organizer_id, candidate_id) VALUES (10.00, "Pending", ?, ?)');
            $payment->execute([(int) $row['organizer_id'], $userId]);
            $paymentId = (int) $this->pdo->lastInsertId();
            if (is_array($previous)) {
                $id = (int) $previous['id'];
                $this->pdo->prepare('DELETE FROM candidate_document WHERE candidacy_id = ?')->execute([$id]);
                $s = $this->pdo->prepare('UPDATE candidacy SET payment_id=?, status="Pending", registered_at=NOW(), position_sought=?, manifesto=?, supporting_details=?, profile_photo=?, profile_photo_mime=? WHERE id=?');
                $s->execute([$paymentId, $input['position'], $input['manifesto'], $input['details'], $profilePhoto['name'], $profilePhoto['mime'], $id]);
            } else {
                $s = $this->pdo->prepare('INSERT INTO candidacy (candidate_id,election_id,payment_id,status,registered_at,position_sought,manifesto,supporting_details,profile_photo,profile_photo_mime) VALUES (?, ?, ?, "Pending", NOW(), ?, ?, ?, ?, ?)');
                $s->execute([$userId, $electionId, $paymentId, $input['position'], $input['manifesto'], $input['details'], $profilePhoto['name'], $profilePhoto['mime']]); $id = (int)$this->pdo->lastInsertId();
            }
            $documentDirectory = $this->directory . '/documents';
            if (!is_dir($documentDirectory) && !mkdir($documentDirectory, 0700, true)) throw new RuntimeException('Cannot create private document storage.');
            foreach ($files as $file) { if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue; $document = $this->documents->validate($file); $path = $documentDirectory . '/' . $document['stored']; if (!move_uploaded_file((string)$file['tmp_name'], $path)) throw new RuntimeException('Cannot save document.'); $stored[] = $path; $d = $this->pdo->prepare('INSERT INTO candidate_document (candidacy_id,original_name,stored_name,mime_type,uploaded_at) VALUES (?, ?, ?, ?, NOW())'); $d->execute([$id, $document['original'], $document['stored'], $document['mime']]); }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); foreach ($stored as $path) if (is_file($path)) unlink($path); throw $e; }
    }
}
