# Candidate Registration — Wong Shun Bin

This module is built on the shared tables in `election_db.sql`: `user`, `candidate`, `election`, and `candidacy`.

It demonstrates your assigned requirements:

- **Input Validation / data protection:** allow-listed fields, length checks, prepared SQL queries, escaped HTML output, CSRF tokens, and session regeneration at login.
- **File Management:** rejects unsafe uploads unless their PHP upload status, 5 MiB size limit, file extension, and server-detected MIME type all match. Files receive random names and are stored outside `public`.
- **Proxy pattern:** `RegistrationProxy` implements the same interface as `RealRegistrationService`; it validates requests before delegating database work to the real service.

## Run

Import `election_db.sql` in phpMyAdmin, then start:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8082 -t WongShunBin\public WongShunBin\public\router.php
```

Open `http://127.0.0.1:8082`. Use `CandidateAisha` / `Candidate!2026`. An election must have status `NOMINATION_OPEN` to accept an application.
