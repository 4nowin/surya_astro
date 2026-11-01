<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | JME Library Path
    |--------------------------------------------------------------------------
    | Path to the compiled libjme.so / libjme.dylib / jme.dll file.
    */
    'library_path' => env('JME_LIBRARY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Native Engine Selection
    |--------------------------------------------------------------------------
    | This controls only the optional PHP convenience JmeService::calc() method.
    | The low-level FFI wrapper always exposes every native jme_* function.
    |
    | 'AUTO'           -> Let the native C core choose its normal engine policy.
    | 'JPL'            -> Require JPL kernel-backed behavior.
    | 'MOSHIER'        -> Force Moshier analytical behavior.
    | 'VSOP_ELP_MEEUS' -> Force VSOP87/ELP2000/Meeus analytical behavior.
    */
    'engine' => env('JME_ENGINE', 'AUTO'),

    /*
    |--------------------------------------------------------------------------
    | Ephemeris Path (for JPL Kernels)
    |--------------------------------------------------------------------------
    | Set this to a `.bsp` file or a directory containing `.bsp` kernels.
    | Kernel download release:
    | https://github.com/jayeshmepani/jpl-ephemeris/releases/tag/jpl-kernels
    */
    'ephemeris_path' => env('JME_EPHEMERIS_PATH', storage_path('app/ephemeris')),
];
