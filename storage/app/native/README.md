# Astro Setu Native Library Diagnostic Folder

This directory holds test libraries for validating PHP FFI (Foreign Function Interface) native library loading.

## How to compile `libastrotest.so` for Linux (Hostinger)

If Hostinger provides SSH access, run:

```bash
cd storage/app/native
gcc -O2 -fPIC -shared -o libastrotest.so test_lib.c
```

If Hostinger does NOT provide a compiler (`gcc` not installed or disabled):
1. Compile `libastrotest.so` on any x86_64 Linux machine (e.g., Ubuntu/Debian VM, WSL2, or a Docker container):
   ```bash
   gcc -O2 -fPIC -shared -o libastrotest.so test_lib.c
   ```
2. Upload `libastrotest.so` into this directory (`storage/app/native/`) via FTP, Git, or File Manager.
3. Ensure file permissions are readable (`chmod 644 libastrotest.so` or `chmod 755`).

## Exported Functions in `test_lib.c`

- `int astro_test_engine(int val)` — Returns `val * 2`. (Testing input `21` produces `42`).
- `int astro_get_version(void)` — Returns `100`.
