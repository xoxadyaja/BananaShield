# BananaShield

BananaShield is a centralized Laravel Blade and FastAPI decision-support system for a selected banana farm in Davao del Sur. It supports preliminary image screening, advisory information, farm reporting, case follow-ups, owner review, farm profile/settings management, operational analytics, and system administration.

The implementation follows the August 2026 Input–Process–Output conceptual framework:

- Three roles: **Farm Owner**, **Monitoring Personnel**, and **System Administrator**.
- Two guided capture paths: **Leaf Screening** and **Whole-Plant Screening**.
- One shared **EfficientNet-B0** integration for images accepted from either path.
- Four supported output categories: **Healthy Banana**, **Black Sigatoka**, **Fusarium Wilt**, and **Banana Bunchy Top Disease**.
- Low-confidence, poor-quality, mismatched-view, or unsupported results become **inconclusive** and recommend another image or professional assessment.
- Private case images, contextual farm information, follow-up observations/images/actions/statuses, owner review, versioned advisories, model registry, confidence thresholds, and activity logs.
- Farm Owner management of farm details, sections/areas, and stored notification preferences.
- Seven aligned stages: role-based authentication, guided image validation, preprocessing, shared classification, confidence generation, advisory/case recording, and follow-up/analytics.
- Seven outputs: preliminary class, confidence, advisory, farm case/report, follow-ups, monitoring summaries, and system-recorded analytics.
- Analytics describe only records submitted through BananaShield and are never presented as official incidence, prevalence, outbreak, or epidemiological-surveillance statistics.

BananaShield is not a confirmed diagnosis, disease-severity measurement, laboratory test, treatment authorization, or substitute for qualified agricultural assessment.

## Project structure

- `web-application/` - Laravel 12 application, MySQL data, authentication, role authorization, reporting, monitoring, advisory, analytics, and administration.
- `ai-service/` - FastAPI image-validation and shared four-class model contract.
- `documentation/` - supporting project documentation.

See [documentation/CONCEPTUAL_FRAMEWORK.md](documentation/CONCEPTUAL_FRAMEWORK.md) for the complete role inputs, seven process stages, seven outputs, safeguards, and implementation mapping.

## Demonstration and trained-model modes

`AI_MODE=mock` is the safe default. It exercises the complete workflow while clearly labeling outputs as mock. It does not claim learned classification or validated accuracy.

`AI_MODE=model` loads one Keras-compatible model from `MODEL_PATH`. The model must output exactly four probabilities in this order:

1. `healthy_banana`
2. `black_sigatoka`
3. `fusarium_wilt`
4. `banana_bunchy_top_disease`

Do not activate model mode until the file is trained, independently evaluated, documented, and registered in the System Administrator workspace with a validated confidence threshold.

## Quick setup

For a clean installation after deleting the previous database:

1. Start MySQL and create an empty `bananashield` database using the `utf8mb4_unicode_ci` collation.
2. Configure `web-application/.env` with the MySQL connection details.
3. Inside `web-application/`, run `composer install` only if `vendor/autoload.php` is missing.
4. Run `php artisan optimize:clear`, followed by `php artisan migrate --seed`.
5. Keep `AI_MODE=mock` for the built-in demonstration, or start the FastAPI service on port 8001 for service/model mode.
6. Run `php artisan serve --host=0.0.0.0 --port=8000`.

See [HOW_TO_RUN_BANANASHIELD.txt](HOW_TO_RUN_BANANASHIELD.txt) for the complete XAMPP, Composer, database, and startup instructions.

Seeded local accounts use the password configured by `BANANASHIELD_SEED_PASSWORD` (default: `ChangeMe!2026`):

- `owner@bananashield.local`
- `monitor@bananashield.local`
- `admin@bananashield.local`

## Verification

```powershell
cd "D:\Capstone\BananaShield Final\web-application"
php artisan test

cd "D:\Capstone\BananaShield Final\ai-service"
.\.venv\Scripts\python.exe -m pytest -q -p no:cacheprovider
```
