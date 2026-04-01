#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.env}"
VARS_FILE="${2:-runtime.vars.env}"

if [ ! -f "$VARS_FILE" ]; then
  exit 0
fi

if [ ! -f "$ENV_FILE" ]; then
  cp .env.example "$ENV_FILE"
fi

awk -F= -v envf="$ENV_FILE" '
  function is_env_kv(line) {
    return line ~ /^[A-Za-z_][A-Za-z0-9_]*=/
  }

  BEGIN {
    while ((getline line < envf) > 0) {
      if (is_env_kv(line)) {
        split(line, kv, "=")
        existing[kv[1]] = line
      }
    }
    close(envf)
  }

  {
    if (is_env_kv($0)) {
      key = $1
      if (key != "APP_KEY") {
        existing[key] = $0
      }
    }
  }

  END {
    for (k in existing) {
      print existing[k]
    }
  }
' "$VARS_FILE" > "$ENV_FILE.tmp"

mv "$ENV_FILE.tmp" "$ENV_FILE"
rm -f "$VARS_FILE"
