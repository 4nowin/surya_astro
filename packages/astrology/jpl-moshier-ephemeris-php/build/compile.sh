#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
if [[ -n "${JME_SOURCE_PATH:-}" ]]; then
    NATIVE_ROOT="$JME_SOURCE_PATH"
elif [[ -d "$ROOT_DIR/../JPL-Moshier-Ephemeris" ]]; then
    NATIVE_ROOT="$ROOT_DIR/../JPL-Moshier-Ephemeris"
else
    NATIVE_ROOT="$ROOT_DIR/../jpl-ephemeris"
fi

if [[ ! -f "$NATIVE_ROOT/CMakeLists.txt" ]]; then
    echo "Native JME source tree not found: $NATIVE_ROOT" >&2
    exit 1
fi

family="$(uname -s)"
arch="$(uname -m)"

case "$family" in
    Linux)
        platform_dir="linux"
        lib_file="libjme.so"
        ;;
    Darwin)
        platform_dir="macos"
        lib_file="libjme.dylib"
        ;;
    MINGW*|MSYS*|CYGWIN*)
        platform_dir="windows"
        lib_file="jme.dll"
        ;;
    *)
        echo "Unsupported OS family: $family" >&2
        exit 1
        ;;
esac

case "$arch" in
    x86_64|amd64)
        arch_dir="x64"
        ;;
    aarch64|arm64)
        arch_dir="arm64"
        ;;
    *)
        arch_dir="$arch"
        ;;
esac

cmake -S "$NATIVE_ROOT" -B "$NATIVE_ROOT/build" -DCMAKE_BUILD_TYPE=Release
cmake --build "$NATIVE_ROOT/build" --config Release

src_lib="$NATIVE_ROOT/build/$lib_file"
dest_dir="$ROOT_DIR/libs/$platform_dir-$arch_dir"
dest_lib="$dest_dir/$lib_file"

if [[ ! -f "$src_lib" ]]; then
    echo "Built library not found: $src_lib" >&2
    exit 1
fi

mkdir -p "$dest_dir"
cp "$src_lib" "$dest_lib"
echo "Installed native library: $dest_lib"
