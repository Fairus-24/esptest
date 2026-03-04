Import("env")
import os

# Some production service environments (PM2/PHP-FPM) provide a minimal PATH.
# Ensure basic shell/system bins are always available to PlatformIO/SCons tasks.
runtime_env = env["ENV"]
path_sep = os.pathsep
raw_path = runtime_env.get("PATH", "") or ""
parts = [p.strip() for p in raw_path.split(path_sep) if p.strip()]

if os.name != "nt":
    required_unix_paths = [
        "/usr/local/sbin",
        "/usr/local/bin",
        "/usr/sbin",
        "/usr/bin",
        "/sbin",
        "/bin",
    ]
    for item in required_unix_paths:
        if item not in parts:
            parts.append(item)

    runtime_env["SHELL"] = "/bin/sh"

runtime_env["PATH"] = path_sep.join(parts)
