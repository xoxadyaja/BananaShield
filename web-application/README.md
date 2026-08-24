# BananaShield Laravel application

This Laravel 12 application implements the revised BananaShield roles and six integrated capability areas:

- Preliminary Disease Screening
- Advisory Support
- Disease Monitoring
- Analytics Dashboard
- Farm Reporting
- Farm Profile and Settings

For a clean installation, first create an empty MySQL database named `bananashield`, configure `.env`, and then run `php artisan migrate --seed`. This creates the schema, the four supported disease categories, versioned advisory content, one shared EfficientNet-B0 integration registry entry, the selected-farm profile/settings record, and the three local demonstration accounts described in the repository root README.

If `vendor/autoload.php` is already present, Composer installation is not required merely to start this project copy. See the repository's `HOW_TO_RUN_BANANASHIELD.txt` for the complete clean-installation procedure.

Run the test suite with:

```powershell
php artisan test
```

Uploaded case and follow-up images use the private `local` filesystem disk and are served only through authenticated, case-authorized routes.
