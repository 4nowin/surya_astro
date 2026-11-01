/*
 * Astro Setu FFI Diagnostic Test Library
 * 
 * Simple C shared library exporting a test function:
 *   astro_test_engine(int val) -> returns val * 2
 * 
 * Linux build command (for Hostinger SSH / Ubuntu / Debian / CloudLinux):
 *   gcc -O2 -fPIC -shared -o libastrotest.so test_lib.c
 * 
 * Windows build command (MinGW):
 *   gcc -O2 -shared -o libastrotest.dll test_lib.c
 */

#include <stdio.h>

#ifdef _WIN32
    #define EXPORT __declspec(dllexport)
#else
    #define EXPORT __attribute__((visibility("default")))
#endif

EXPORT int astro_test_engine(int val) {
    return val * 2;
}

EXPORT int astro_get_version(void) {
    return 100;
}
