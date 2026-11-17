# Deployment handoff standard update

## Changed local files

| Exact local path | Live destination | FTP action |
| --- | --- | --- |
| `D:\xampp\htdocs\shalimar-properties\README.md` | No live destination | Documentation only; retain in Git. |
| `D:\xampp\htdocs\shalimar-properties\deploy\README.md` | No live destination | Deployment guidance only; retain in Git. |

No application files changed. No live upload, database SQL, migration, or `.env` change is required for this documentation update.

For future code changes, create a task folder under `deploy/`, copy only the changed deployable files while preserving their relative directories, then list each package source path and exact live destination in that task's README. Back up existing live files before replacement. If a file is documentation-only or there is no verified live destination, state that clearly instead of inventing an upload path.

Status: prepared locally; not a live deployment.
