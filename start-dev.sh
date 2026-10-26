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

# Do not start listeners against a stale schema or hide migration failures.
echo "Applying pending database migrations..."
php artisan migrate --force

# 1. Laravel web server
php artisan serve --host="$HOST" --port="$PORT" &
echo $! >> "$PID_FILE"
sleep 2

# 2. Reverb WebSocket server
php artisan reverb:start --port="$REVERB_PORT" &
echo $! >> "$PID_FILE"
sleep 1

# 3. Queue worker
php artisan queue:work --queue=telephony,default --tries=1 --timeout=800 &
echo $! >> "$PID_FILE"
sleep 1

# Separate lanes keep long imports away from call and general application work.
php artisan queue:work --queue=imports --tries=1 --timeout=800 &
echo $! >> "$PID_FILE"
sleep 1

php artisan queue:work --queue=asterisk --tries=1 --timeout=120 &
echo $! >> "$PID_FILE"
sleep 1

# The outbox job uses the durable database connection and its own queue lane.
php artisan queue:work database --queue=telephony-sync --tries=1 --timeout=60 &
echo $! >> "$PID_FILE"
sleep 1

# 4. AMI listener
php artisan ami:listen &
echo $! >> "$PID_FILE"
sleep 1

# Email sends stay opt-in for local development. Set START_EMAIL_WORKER=1 only
# when the selected email queue is intentionally being consumed.
if [[ "${START_EMAIL_WORKER:-0}" == "1" ]]; then
  php artisan queue:work "${EMAIL_CAMPAIGN_CONNECTION:-email-database}" \
    --queue="${EMAIL_CAMPAIGN_QUEUE:-email-campaigns}" --tries=1 --timeout=60 &
  echo $! >> "$PID_FILE"
fi

echo ""
echo "Services running: serve, reverb, general/import/Asterisk/telephony-sync queues, ami:listen."
echo "Email queue is idle unless START_EMAIL_WORKER=1 is set."
echo "Press Ctrl+C to stop all."
echo ""

wait || true
cleanup
