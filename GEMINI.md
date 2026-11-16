# Laravel Project Rules for Gemini CLI
- Always read `C:\Users\rsrav\ai-bridge\pandit-setu\API_CONTRACT.md` and `TASK_QUEUE.md` before writing code.
- Follow strict RESTful Laravel conventions: use FormRequest classes for validation, Eloquent API Resources for JSON formatting, and Service classes for heavy business logic.
- Ensure all JSON keys strictly match `API_CONTRACT.md` (use snake_case consistently).
- After modifying files, run `php artisan test` (or `php artisan route:list` to verify endpoints) and fix any errors.
- Always append a concise summary of created/modified files and test results to `C:\Users\rsrav\ai-bridge\pandit-setu\PROGRESS_LOG.md`.
