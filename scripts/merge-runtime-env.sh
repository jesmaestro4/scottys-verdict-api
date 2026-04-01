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

while IFS= read -r line || [ -n "$line" ]; do
  case "$line" in
    ''|'#'*)
      continue
      ;;
  esac

  if ! printf '%s' "$line" | grep -Eq '^[A-Za-z_][A-Za-z0-9_]*='; then
    continue
  fi

  key="${line%%=*}"
  value="${line#*=}"

  # Never overwrite the server-side application key.
  if [ "$key" = "APP_KEY" ]; then
    continue
  fi

  if grep -Eq "^${key}=" "$ENV_FILE"; then
    KEY="$key" VALUE="$value" perl -i -pe '
      BEGIN {
        $k = $ENV{KEY};
        $v = $ENV{VALUE};
        $done = 0;
      }
      if (!$done && /^\Q$k\E=/) {
        $_ = "$k=$v\n";
        $done = 1;
      }
    ' "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
done < "$VARS_FILE"

rm -f "$VARS_FILE"
