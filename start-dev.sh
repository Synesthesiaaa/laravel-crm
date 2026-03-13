#!/usr/bin/env bash
#
# Laravel CRM - Start all required components (Linux / macOS)
# Usage: ./start-dev.sh   or   bash start-dev.sh
#

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

# Optional overrides (defaults match common setup)
HOST="${HOST:-0.0.0.0}"
PORT="${PORT:-8000}"
REVERB_PORT="${REVERB_PORT:-6001}"
PID_FILE="${PID_FILE:-$PROJECT_DIR/storage/app/run-dev.pids}"

# Ensure storage dir exists for PID file
mkdir -p "$(dirname "$PID_FILE")"
: > "$PID_FILE"

cleanup() {
  echo ""
  echo "Stopping Laravel CRM services..."
  if [[ -f "$PID_FILE" ]]; then
    while read -r pid; do
      if [[ -n "$pid" ]]; then
        kill "$pid" 2>/dev/null || true
      fi
    done < "$PID_FILE"
    rm -f "$PID_FILE"
  fi
  echo "Done."
  exit 0
}

trap cleanup SIGINT SIGTERM

echo "Starting Laravel CRM services (project: $PROJECT_DIR)"
echo "  HTTP: http://${HOST}:${PORT}"
echo "  Reverb: port ${REVERB_PORT}"
echo ""

# 1. Laravel web server
php artisan serve --host="$HOST" --port="$PORT" &
echo $! >> "$PID_FILE"
sleep 2

# 2. Reverb WebSocket server
php artisan reverb:start --port="$REVERB_PORT" &
echo $! >> "$PID_FILE"
sleep 1

# 3. Queue worker
php artisan queue:work &
echo $! >> "$PID_FILE"
sleep 1

# 4. AMI listener
php artisan ami:listen &
echo $! >> "$PID_FILE"
sleep 1

# 5. Run migrations once (non-blocking)
php artisan migrate --force 2>/dev/null || true

echo ""
echo "All services running: serve, reverb, queue, ami:listen."
echo "Press Ctrl+C to stop all."
echo ""

wait || true
cleanup
