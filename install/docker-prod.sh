#!/bin/bash
#
# PatchDashboard production Docker installer
#
set -euo pipefail

MY_PATH="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE_FILE="${MY_PATH}/docker-compose.prod.yml"
ENV_FILE="${MY_PATH}/.env"
ENV_EXAMPLE="${MY_PATH}/.env.prod.example"

PUBLIC_URL=""
HTTP_PORT="80"
ADMIN_USER="admin"
ADMIN_EMAIL=""
ADMIN_PASS=""
COMPANY=""
MARIADB_ROOT_PASSWORD=""
MARIADB_PASSWORD=""
JWT_SECRET=""
PW_SALT=""
UNATTENDED="no"
PREPARE_WEB_SETUP="no"
SETUP_TOKEN=""

usage() {
    cat <<EOF
PatchDashboard production Docker installer

Builds and runs the production stack (nginx + PHP-FPM API + MariaDB) via Docker Compose.

Usage:
  ./install/docker-prod.sh [options]

Interactive:
  ./install/docker-prod.sh

Hybrid web setup (containers only; finish admin in browser):
  ./install/docker-prod.sh --prepare-web-setup

From repo root (same flags):
  ./install.sh --docker-prod [--prepare-web-setup]

Options:
  --prepare-web-setup    Start stack and print a one-time /setup URL
  --unattended           Non-interactive mode
  --public-url=URL       Public dashboard URL (default: http://localhost)
  --http-port=PORT       Host port for web service (default: 80)
  --admin-user=USER      Admin username for full CLI bootstrap
  --admin-email=EMAIL    Admin email
  --admin-pass=PASS      Admin password
  --company=NAME         Company display name
  --db-root-pass=PASS    MariaDB root password
  --db-pass=PASS         MariaDB application user password
  --jwt-secret=SECRET    JWT signing secret
  --help                 Show this help

After install:
  docker compose -f docker-compose.prod.yml ps
  docker compose -f docker-compose.prod.yml logs -f web api

EOF
}

log() { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!>\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

random_secret() {
    tr -dc 'A-Za-z0-9~!@#$%^&*_-' < /dev/urandom | head -c "${1:-32}" || true
}

prompt() {
    local var_name="$1"
    local message="$2"
    local default="${3:-}"
    local secret="${4:-no}"
    local input=""

    [[ "$UNATTENDED" == "yes" ]] && return 0

    if [[ "$secret" == "yes" ]]; then
        read -rsp "${message}: " input
        echo
    elif [[ -n "$default" ]]; then
        read -rp "${message} [${default}]: " input
    else
        read -rp "${message}: " input
    fi

    if [[ -z "$input" && -n "$default" ]]; then
        input="$default"
    fi
    printf -v "$var_name" '%s' "$input"
}

parse_args() {
    local filtered=()
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --docker-prod) shift ;;
            --prepare-web-setup) PREPARE_WEB_SETUP="yes"; shift ;;
            --unattended) UNATTENDED="yes"; shift ;;
            --public-url=*) PUBLIC_URL="${1#*=}"; shift ;;
            --web-url=*) PUBLIC_URL="${1#*=}"; shift ;;
            --http-port=*) HTTP_PORT="${1#*=}"; shift ;;
            --admin-user=*) ADMIN_USER="${1#*=}"; shift ;;
            --admin-email=*) ADMIN_EMAIL="${1#*=}"; shift ;;
            --admin-pass=*) ADMIN_PASS="${1#*=}"; shift ;;
            --company=*) COMPANY="${1#*=}"; shift ;;
            --db-root-pass=*) MARIADB_ROOT_PASSWORD="${1#*=}"; shift ;;
            --db-pass=*) MARIADB_PASSWORD="${1#*=}"; shift ;;
            --jwt-secret=*) JWT_SECRET="${1#*=}"; shift ;;
            --help|-h) usage; exit 0 ;;
            *) filtered+=("$1"); shift ;;
        esac
    done
    if [[ ${#filtered[@]} -gt 0 ]]; then
        die "Unknown option: ${filtered[0]} (try --help)"
    fi
}

require_docker() {
    command -v docker >/dev/null 2>&1 || die "Docker is not installed"
    docker compose version >/dev/null 2>&1 || die "Docker Compose v2 plugin is required (docker compose)"
}

compose() {
    docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"
}

collect_config() {
    prompt PUBLIC_URL "Public dashboard URL" "${PUBLIC_URL:-http://localhost}"
    [[ -n "$PUBLIC_URL" ]] || die "--public-url is required in unattended mode"

    prompt HTTP_PORT "Host HTTP port" "${HTTP_PORT:-80}"

    if [[ -z "$MARIADB_ROOT_PASSWORD" ]]; then
        MARIADB_ROOT_PASSWORD="$(random_secret 24)"
        [[ "$UNATTENDED" == "yes" ]] || warn "Generated MariaDB root password"
    fi

    if [[ -z "$MARIADB_PASSWORD" ]]; then
        MARIADB_PASSWORD="$(random_secret 24)"
        [[ "$UNATTENDED" == "yes" ]] || warn "Generated MariaDB application password"
    fi

    JWT_SECRET="${JWT_SECRET:-$(random_secret 48)}"
    PW_SALT="${PW_SALT:-$(random_secret 16)}"

    if [[ "$PREPARE_WEB_SETUP" == "yes" ]]; then
        return 0
    fi

    prompt ADMIN_USER "Admin username" "$ADMIN_USER"
    prompt ADMIN_EMAIL "Admin email" "${ADMIN_EMAIL:-admin@localhost}"
    [[ -n "$ADMIN_EMAIL" ]] || die "--admin-email is required in unattended mode"

    if [[ -z "$ADMIN_PASS" ]]; then
        if [[ "$UNATTENDED" == "yes" ]]; then
            die "--admin-pass is required in unattended mode"
        fi
        ADMIN_PASS="$(random_secret 16)"
        warn "Generated admin password: ${ADMIN_PASS}"
    fi

    prompt COMPANY "Company name" "${COMPANY:-PatchDashboard}"
    [[ -n "$COMPANY" ]] || die "--company is required in unattended mode"
}

write_env_file() {
    log "Writing ${ENV_FILE}"
    cat > "$ENV_FILE" <<EOF
COMPOSE_PROJECT_NAME=patchdashboard
PUBLIC_URL=${PUBLIC_URL}
HTTP_PORT=${HTTP_PORT}
MARIADB_ROOT_PASSWORD=${MARIADB_ROOT_PASSWORD}
MARIADB_DATABASE=patchdashboard
MARIADB_USER=patchdashboard
MARIADB_PASSWORD=${MARIADB_PASSWORD}
JWT_SECRET=${JWT_SECRET}
PW_SALT=${PW_SALT}
YOUR_COMPANY=${COMPANY:-Pending setup}
MAIL_FROM=alerts@patchdashboard.local
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
EOF
    chmod 600 "$ENV_FILE"
}

wait_for_healthy() {
    log "Waiting for services to become healthy"
    local health_url="http://127.0.0.1:${HTTP_PORT}/health"
    local attempt
    for attempt in $(seq 1 60); do
        if curl -fsS --max-time 5 "$health_url" 2>/dev/null | grep -q '"status"'; then
            log "Health check passed: ${health_url}"
            return 0
        fi
        sleep 2
    done
    die "Timed out waiting for ${health_url}"
}

bootstrap_admin() {
    log "Creating admin user and company record"
    compose exec -T api php /database/bootstrap.php \
        --admin-user="$ADMIN_USER" \
        --admin-email="$ADMIN_EMAIL" \
        --admin-pass="$ADMIN_PASS" \
        --company="$COMPANY"
    compose exec -T api sh -c 'printf "{\"installed_at\":\"%s\",\"method\":\"docker-cli\"}\n" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > /app/storage/install.lock'
    compose exec -T api sh -c 'rm -f /app/storage/setup.token'
}

write_setup_token() {
    log "Generating one-time web setup token"
    SETUP_TOKEN="$(random_secret 32)"
    compose exec -T api sh -c "mkdir -p /app/storage && printf '%s\n' '${SETUP_TOKEN}' > /app/storage/setup.token && chown -R www-data:www-data /app/storage && chmod 770 /app/storage && chmod 640 /app/storage/setup.token && rm -f /app/storage/install.lock"
}

print_web_setup_summary() {
    cat <<EOF

PatchDashboard production Docker stack is running.

  Setup URL:   ${PUBLIC_URL%/}/setup?token=${SETUP_TOKEN}
  Dashboard:   ${PUBLIC_URL}
  Health:      ${PUBLIC_URL%/}/health
  HTTP port:   ${HTTP_PORT}

  Env file:    ${ENV_FILE}

Open the setup URL to create the admin account. The token is single-use.

Manage stack:
  docker compose -f docker-compose.prod.yml ps
  docker compose -f docker-compose.prod.yml logs -f web api

EOF
}

print_summary() {
    local install_key
    install_key="$(compose exec -T api php -r '
        $pdo = new PDO(
            "mysql:host=" . getenv("DB_HOST") . ";dbname=" . getenv("DB_NAME"),
            getenv("DB_USER"),
            getenv("DB_PASS")
        );
        echo $pdo->query("SELECT install_key FROM company LIMIT 1")->fetchColumn();
    ' 2>/dev/null || echo '(see database)')"
    cat <<EOF

PatchDashboard production Docker stack is running.

  Dashboard:   ${PUBLIC_URL}
  Health:      ${PUBLIC_URL%/}/health
  HTTP port:   ${HTTP_PORT}

  Admin login: ${ADMIN_USER}
  Password:    ${ADMIN_PASS}

  Agent install key (PD_AUTH_KEY): ${install_key}
  Env file:    ${ENV_FILE}

Manage stack:
  docker compose -f docker-compose.prod.yml ps
  docker compose -f docker-compose.prod.yml logs -f web api

EOF
}

main() {
    parse_args "$@"
    require_docker
    cd "$MY_PATH"

    if [[ ! -f "$ENV_EXAMPLE" ]]; then
        die "Missing ${ENV_EXAMPLE}"
    fi

    collect_config
    write_env_file

    log "Building production images"
    compose build

    log "Starting production stack"
    compose up -d

    wait_for_healthy

    if [[ "$PREPARE_WEB_SETUP" == "yes" ]]; then
        write_setup_token
        print_web_setup_summary
        return 0
    fi

    bootstrap_admin
    print_summary
}

main "$@"
