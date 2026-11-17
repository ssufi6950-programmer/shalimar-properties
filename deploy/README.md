# Deployment handoff standard

Create one folder per completed implementation:

```text
deploy/<task-name>/README.md
deploy/<task-name>/<mirrored application paths for changed deployable files>
```

Each task README must include:

1. Exact absolute local source path for each file, for example `D:\xampp\htdocs\shalimar-properties\deploy\<task-name>\app\Services\Example.php`.
2. Exact absolute live destination path, normally `/home/propertys_user11/www/app/Services/Example.php` for a portal service, or the verified `html`/`public` path for website assets.
3. FTP action for each file: merge the package folder and replace only the named files.
4. Backup instructions for existing live files and any required database/configuration steps. Never include secret values in the README.
5. Explicit status: prepared locally, uploaded by user, or deployed by Codex. Do not claim deployment without confirmation/evidence.

For documentation-only or local-only work, state **No live upload required** and explain briefly. Include only files changed for that task. Keep `.env`, credentials, runtime data and unrelated files out of deployment packages.

The Git-ignored `deploy/` directory is excluded by default. If the task README/package is part of a requested Git push, stage only that task's handoff files deliberately; never force-add the whole directory.
