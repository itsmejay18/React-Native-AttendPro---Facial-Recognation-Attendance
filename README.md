# AttendPro

AttendPro is the institution-wide attendance system for students, faculty, and staff of Holy Child College of Davao. Laravel provides the web camera and administration UI, while an in-repository Python/FastAPI service performs the authoritative OpenCV facial detection, embedding, and matching.

## Implemented backend

- Laravel Breeze authentication with login, pending registration, logout, login throttling, and password reset
- Spatie Permission authorization with `super_admin`, `attendance_admin`, and read-only `reviewer` roles
- Student, faculty, and staff directories with department, institutional, contact, and enrollment state data
- Encrypted-at-rest face embeddings with model version, checksum, consent, and retention metadata
- Locations, recognition terminals, schedules, attendance windows, grace periods, and direct schedule assignments
- Idempotent recognition events that produce time-in/time-out and present/late records
- Unknown and failed recognition review queue
- Manual attendance entry and corrections with revision tracking
- Daily/role/department analytics and filtered CSV export
- Terminal heartbeat/online status and revocable, one-time-displayed API credentials
- Before/after administrative audit history without passwords, tokens, or facial embeddings
- Automated absent-record command and daily scheduler entry

## Requirements

- PHP 8.3 or newer with the MySQL PDO extension
- Composer
- MySQL 8+ or MariaDB 10.6+
- Node.js and npm
- Python 3.10 or newer

The code uses PHP/Laravel paths and environment variables, not OS-specific filesystem paths, so the same repository runs on Windows and Linux.

## Installation

1. Copy `.env.example` to `.env` (`copy .env.example .env` on Windows Command Prompt, or `cp .env.example .env` on Linux/PowerShell).
2. Create a MySQL database named `attendpro` and enter its credentials in `.env`.
3. Set `ATTENDPRO_ADMIN_PASSWORD` to a unique password of at least 12 characters.
4. Run:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### Secure Gmail SMTP and Google sign-in

Do not commit a local `.env`. It is ignored by Git; `.env.example` contains only safe placeholders. The supplied setup scripts create `.env` only when it is missing, preserve existing values, and ask for missing Gmail and Google OAuth values without echoing secrets.

On Windows PowerShell:

```powershell
./setup.ps1
```

On Linux or macOS:

```bash
chmod +x setup.sh
./setup.sh
```

For non-interactive setup, provide values through these OS environment variables before running the script: `ATTENDPRO_MAIL_USERNAME`, `ATTENDPRO_MAIL_PASSWORD`, `ATTENDPRO_GOOGLE_CLIENT_ID`, `ATTENDPRO_GOOGLE_CLIENT_SECRET`, and `ATTENDPRO_GOOGLE_REDIRECT_URI`. Existing non-empty `.env` values always take priority.

Use a Gmail **App Password** (not a normal Gmail password) with `smtp.gmail.com`, port `587`, and `MAIL_REQUIRE_TLS=true`. Super administrators and attendance administrators can change sender details under **Settings → Email Notifications**; the saved SMTP password is never displayed. After a new recognized time-in or time-out is recorded, AttendPro sends the person an email containing the attendance date, time, status, class when available, and location. Duplicate scans do not send another email. With `QUEUE_CONNECTION` set to a non-`sync` driver, start a worker so queued emails are delivered:

```bash
php artisan queue:work
```

Use the safe configuration and delivery checks below. They never print passwords, app passwords, client secrets, or tokens:

```bash
php artisan attendpro:check-config
php artisan attendpro:test-mail person@example.com
```

Google sign-in is available at the login page only when all three Google values are configured. In Google Cloud, create an OAuth **Web application** and add the exact callback URL from `GOOGLE_REDIRECT_URI` (for local development: `http://127.0.0.1:8000/auth/google/callback`). Google sign-in authenticates only an existing active AttendPro user with the same verified email address; it never creates accounts automatically.

On Windows, double-click `start-attendpro.bat` to configure the local recognition terminal, start Laravel and Python on loopback addresses, and open the Recognition Center. The first launch installs the Python dependencies and downloads the recognition models.

### Fresh Windows clone

After cloning the repository, create an empty MySQL database named `attendpro`, then either set its credentials in `.env` or let the launcher create `.env` from `.env.example` on first start. Double-click `start-attendpro.bat`. On a fresh clone it installs the missing PHP, Node, and Python dependencies; generates the Laravel key; migrates and seeds the database; builds browser assets; configures the local Laravel-to-Python connection; and starts both services. PHP, Composer, Node/npm, Python 3.10+, and MySQL must already be installed and available on `PATH`.

For manual or non-Windows startup, start Laravel on the loopback address used by the Python client:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/login` and sign in using `ATTENDPRO_ADMIN_EMAIL` and `ATTENDPRO_ADMIN_PASSWORD`. Keep `php artisan schedule:work` running in a second terminal in production-like deployments, or configure the platform scheduler to run `php artisan schedule:run` every minute.

New users can request access at `http://127.0.0.1:8000/register`. Registrations are assigned the read-only `reviewer` role and remain inactive until a super administrator or attendance administrator approves them from **User Management**. Configure Gmail SMTP in the ignored `.env` before deployment so password-reset and attendance-confirmation emails are delivered.

## Configure the Python recognition backend

For a one-device localhost installation, run this idempotent command (the Windows launcher runs it automatically):

```bash
php artisan attendpro:recognition-setup
```

It writes the matching Laravel/Python shared connection settings to the ignored repository `.env` without displaying the secret. Python identifies faces only; Laravel selects the active program schedule, room, and time window before saving attendance.

For manual Python connection configuration:

1. Sign in as a super administrator or attendance staff operator.
2. Create the required student schedules with their program, room, and time windows.
3. Generate a long random service key. Put the same value in `ATTENDPRO_PYTHON_SERVICE_KEY` for Laravel and Python.
4. Add these settings to the repository root `.env`:

```dotenv
ATTENDPRO_PYTHON_URL=http://127.0.0.1:5001
ATTENDPRO_PYTHON_SERVICE_KEY=<long-random-shared-secret>
ATTENDPRO_LARAVEL_API_URL=http://127.0.0.1:8000/api/v1
```

Install the Python service on Linux/macOS:

```bash
cd python_service
./setup.sh
./start.sh
```

On Windows Command Prompt:

```bat
cd python_service
setup.bat
start.bat
```

The first setup downloads OpenCV YuNet and SFace ONNX models into the ignored `python_service/models` directory. Keep Laravel running on port 8000 and Python on port 5001. The Recognition Center displays **Python ready** when both services and the terminal credential are correct.

### Register a person and their face

1. Sign in as a super administrator or attendance staff operator.
2. Open **Students**, **Faculty**, or **Staff**, then select **Register person**.
3. Save the Laravel profile with **Continue to camera-based face enrollment** selected.
4. In the Recognition Center, start the camera, verify documented consent, and select **Collect & save enrollment samples**. The camera collects 20 short-spaced frames and Python must accept at least 15 distinct, high-quality samples before enrollment succeeds.
5. Use **Recognize & Record** for later scans. Laravel records time-in/time-out and attendance status after Python matches the enrolled face.

After the one-time setup, super administrators and attendance staff can start Python without a terminal: open **Recognition Center** and select **Start recognition server**. Laravel launches the fixed local service as a hidden background process; this control is limited to authenticated admin/staff accounts accessing Laravel from the same device.

### Recognition flow

1. The authorized operator starts the camera in the Laravel Recognition Center.
2. Browser-side MediaPipe provides a preliminary one-face framing guide.
3. The operator captures a frame and selects **Recognize & Record**.
4. Laravel validates the session/CSRF token and forwards the frame to the loopback Python service using the shared service key.
5. Python/OpenCV detects and aligns the face, creates an SFace embedding, and compares it with its in-memory enrolled-profile index.
6. Python returns only the match result to the already active Laravel request; it does not call Laravel again while the scan is in progress.
7. Laravel applies schedule, grace-period, duplicate-scan, time-in/time-out, audit, and exception rules, then returns the result to the camera UI.

Raw camera frames are not persisted. Laravel stores encrypted facial embeddings, attendance records, recognition metadata, and audit history.

Enrollment validates one face, detector confidence, face size, blur, brightness, and landmark-based head-pose proxies. It rejects low-quality frames with an actionable reason. Re-enrolling a person safely deactivates their prior active sample set and creates a new versioned enrollment session; attendance history is untouched.

### API endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/health` | Unauthenticated connectivity check |
| `POST` | `/api/v1/terminals/heartbeat` | Report client version/capabilities and keep terminal online |
| `GET` | `/api/v1/terminals/configuration` | Get terminal, location, threshold, timezone, and schedules |
| `GET` | `/api/v1/faces` | Paginated sync of active enrolled embeddings |
| `POST` | `/api/v1/faces/enroll` | Enroll a consented embedding for an existing person |
| `POST` | `/api/v1/recognition/events` | Submit a matched, unknown, or failed scan |

The Python service refreshes active SFace profiles from the paginated face-sync endpoint during startup and immediately indexes a newly enrolled profile. Its internal `/v1/health`, `/v1/sync`, `/v1/extract`, `/v1/index-profile`, `/v1/recognize`, and legacy `/v1/enroll` endpoints accept only the Laravel service key and bind to `127.0.0.1` by default.

Example matched scan:

```json
{
  "event_id": "camera-generated-uuid",
  "result": "matched",
  "institution_id": "2026-00001",
  "confidence": 0.93,
  "direction": "auto",
  "captured_at": "2026-08-26T08:02:13+08:00",
  "metadata": {
    "model": "example-model-version"
  }
}
```

`event_id` is idempotent per terminal: retrying the same event does not create a second attendance record. `direction: auto` creates time-in on the first scan and time-out on the second; later scans return `already_complete`. Matches below `ATTENDPRO_MIN_CONFIDENCE` become failed exceptions for review.

Unknown scan payloads use `result: unknown` and omit the person identity. The public browser never receives the terminal token or direct access to Laravel's facial embedding synchronization API.

### Accuracy and threshold

`ATTENDPRO_MIN_CONFIDENCE=0.65` is the current acceptance threshold: a match must score at least 65% on the system's normalized similarity scale. It is a decision threshold, not a guaranteed 65% accuracy rate. Real accuracy depends on the camera, lighting, pose, image quality, and the enrolled population. Before production use, test a representative set of genuine and impostor scans, then adjust the threshold to meet the institution's false-accept and false-reject requirements.

Each scan now submits a seven-frame window. A person is accepted only when at least five quality-approved frames agree, the best match reaches the configured threshold, and its confidence exceeds the second-best identity by `ATTENDPRO_MIN_SECOND_BEST_MARGIN`. Otherwise the result is `unknown` and no attendance record is created.

### Real evaluation and threshold calibration

Do not use the enrollment frames to measure accuracy. Capture a separate test session (ideally another day) and prepare two CSV files:

```csv
# evaluation/references.csv
person_id,path
2026-00001,enrollment/jay_front.jpg
2026-00001,enrollment/jay_left.jpg

# evaluation/probes.csv
person_id,path,condition
2026-00001,probes/jay_day2_front.jpg,normal_lighting
unknown,probes/visitor.jpg,unknown_person
```

Run the evaluator from `python_service` after starting its virtual environment:

```bash
.venv\Scripts\python.exe -m attendpro_recognition.evaluation --references evaluation/references.csv --probes evaluation/probes.csv --thresholds 0.55,0.60,0.65,0.70,0.75 --output evaluation/report.json
```

It reports identification accuracy for registered probes, false-accept rate for unknown probes, false-reject rate for registered probes, unknown-rejection rate, precision, recall, F1, latency, per-condition counts, and any identity confusions. Choose the lowest false-accept rate compatible with the required recognition rate; update `ATTENDPRO_MIN_CONFIDENCE` and `ATTENDPRO_MIN_SECOND_BEST_MARGIN` in `.env`, then restart Python. Never report an accuracy result until this held-out evaluation has been run.

## Attendance automation

To create missing absent records for the previous day:

```bash
php artisan attendance:mark-absent
```

Or provide a date:

```bash
php artisan attendance:mark-absent 2026-08-26
```

## Security and deployment notes

- Rotate `APP_KEY` only with a planned encrypted-data migration; facial embeddings use this key.
- Serve the portal and API over HTTPS outside loopback development.
- Configure `ATTENDPRO_TERMINAL_ALLOWED_IPS` for known station addresses. Leave it empty only when IP restriction is not possible.
- Define institutional consent, retention, deletion, incident response, and backup policies before enrolling real people.
- Avoid facial images in logs or recognition metadata. Tokens and embeddings are excluded from audit snapshots.
- Back up both MySQL and `APP_KEY`; encrypted embeddings cannot be recovered without the key.
- Keep the Python API bound to loopback unless a secured, firewalled service network and HTTPS are deliberately configured.

## Verification

The test suite uses an isolated MySQL database so it never refreshes development attendance data. Create `attendpro_testing` and grant the configured MySQL user access before running it.

```bash
php artisan route:list
php artisan test
npm run build
cd python_service && .venv/bin/python -m pytest
```
