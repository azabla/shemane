#!/usr/bin/env bash
# Starts the image as production would and waits for Octane to answer on 8000 and the health check to pass.
set -euo pipefail

image=${1:?usage: smoke-serve.sh <image>}
port=${SMOKE_PORT:-18000}
name=smoke-serve-$$

cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker run -d --name "$name" -p "127.0.0.1:$port:8000" \
  -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/smoke.sqlite \
  -e CACHE_DRIVER=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  "$image" >/dev/null

for _ in $(seq 1 60); do
  health=$(docker inspect -f '{{.State.Health.Status}}' "$name" 2>/dev/null || echo missing)
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/" || true)
  if [ "$health" = healthy ] && [ "$code" != 000 ]; then
    docker exec "$name" sh -c 'pgrep -x rr >/dev/null' || { echo "FAIL: port answers but rr is not running"; exit 1; }
    home=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: localhost' "http://127.0.0.1:$port/")
    [ "$home" -lt 500 ] || { echo "FAIL: / on a trusted host answered $home"; docker logs "$name" 2>&1 | tail -50; exit 1; }
    # Probes arrive on an IP; repeat so every worker has served a non-health request first.
    for _ in 1 2 3 4 5 6 7 8; do
      body=$(curl -s -H 'Host: 10.0.0.7' "http://127.0.0.1:$port/health")
      case "$body" in *'"status"'*) ;; *) echo "FAIL: /health by IP answered: ${body:0:120}"; exit 1 ;; esac
    done
    echo "ok: rr serving on 8000 (/ on localhost: HTTP $home; /health by IP: $body), container $health"
    exit 0
  fi
  if [ "$(docker inspect -f '{{.State.Running}}' "$name")" != true ]; then
    echo "FAIL: container exited"; docker logs "$name" 2>&1 | tail -50; exit 1
  fi
  sleep 5
done

echo "FAIL: not healthy after 300s (health=$health, http=$code)"
docker logs "$name" 2>&1 | tail -50
exit 1
