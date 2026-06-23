#!/bin/bash
#
# PatchDashboard v2 VM installer
# Sets up nginx+PHP-FPM or Apache+mod_php, MariaDB, builds the SPA, and bootstraps the API.
#
set -euo pipefail

MY_PATH="$(cd "$(dirname "$0")" && pwd)"

case " ${*} " in
    *" --docker-prod "*) exec "${MY_PATH}/install/docker-prod.sh" "$@" ;;
esac

TEMPLATE_DIR="${MY_PATH}/install/templates"

INSTALL_ROOT="${INSTALL_ROOT:-/opt/patchdashboard}"
WEB_URL=""
SERVER_NAME="_"
ADMIN_USER="admin"
ADMIN_EMAIL=""
ADMIN_PASS=""
COMPANY=""
DB_HOST="127.0.0.1"
DB_NAME="patchdashboard"
DB_USER="patchdashboard"
DB_PASS=""
DB_ROOT_PASS=""
JWT_SECRET=""
PW_SALT=""
UNATTENDED="no"
SKIP_PACKAGES="no"
PREPARE_WEB_SETUP="no"
WEBSERVER="nginx"
WEB_USER=""
PHP_FPM_SOCK=""
SETUP_TOKEN=""
CHECK_FAILURES=0

usage() {
    cat <<EOF
PatchDashboard v2 VM installer

Installs MariaDB, PHP 8.2+, Node.js, builds the React frontend, and configures
the web stack using either nginx + PHP-FPM (default) or Apache + mod_php.

Usage:
  sudo ./install.sh [options]

Interactive mode (default):
  sudo ./install.sh

Hybrid web setup (CLI prepares the stack, browser finishes admin/company):
  sudo ./install.sh --prepare-web-setup

Hybrid mode runs pre- and post-install system checks (packages, services,
directories, permissions, database, and HTTP endpoints) before printing the
setup URL.

Docker production (nginx + PHP-FPM API + MariaDB in containers):
  ./install.sh --docker-prod
  ./install.sh --docker-prod --prepare-web-setup

Unattended full install:
  sudo ./install.sh --unattended \\
    --web-url=http://patch.example.com \\
    --admin-email=admin@example.com \\
    --admin-pass='secret' \\
    --company='Acme Inc' \\
    --db-pass='db-secret'

Options:
  --prepare-web-setup    Install OS packages, database, and web stack; open browser wizard for admin
  --unattended           Run without prompts (required flags below must be set)
  --web-url=URL          Public URL for the dashboard (e.g. http://10.0.0.5 or https://patch.example.com)
  --webserver=SERVER       Web stack: nginx (PHP-FPM) or apache (mod_php); default nginx
  --install-root=PATH    Install tree destination (default: /opt/patchdashboard)
  --admin-user=USER      Admin username (default: admin)
  --admin-email=EMAIL    Admin email address
  --admin-pass=PASS      Admin password
  --company=NAME         Company display name
  --db-host=HOST         Database host (default: 127.0.0.1)
  --db-name=NAME         Database name (default: patchdashboard)
  --db-user=USER         Database user (default: patchdashboard)
  --db-pass=PASS         Database user password
  --db-root-pass=PASS    MariaDB root password (optional on fresh Debian/Ubuntu)
  --skip-packages        Skip apt/dnf package installation
  --help                 Show this help

Legacy v1 installer: ./legacy/install.sh
Docker development:   docker compose up --build
Docker production:    ./install.sh --docker-prod
EOF
}

log() {
    printf '\033[1;32m==>\033[0m %s\n' "$*"
}

warn() {
    printf '\033[1;33m!!>\033[0m %s\n' "$*" >&2
}

die() {
    printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2
    exit 1
}

prompt() {
    local var_name="$1"
    local message="$2"
    local default="${3:-}"
    local secret="${4:-no}"
    local input=""

    if [[ "$UNATTENDED" == "yes" ]]; then
        return 0
    fi

    if [[ "$secret" == "yes" ]]; then
        if [[ -n "$default" ]]; then
            read -rsp "${message} [hidden]: " input
            echo
        else
            read -rsp "${message}: " input
            echo
        fi
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

random_secret() {
    tr -dc 'A-Za-z0-9~!@#$%^&*_-' < /dev/urandom | head -c "${1:-32}" || true
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --unattended) UNATTENDED="yes"; shift ;;
            --prepare-web-setup) PREPARE_WEB_SETUP="yes"; shift ;;
            --skip-packages) SKIP_PACKAGES="yes"; shift ;;
            --web-url=*) WEB_URL="${1#*=}"; shift ;;
            --webserver=*) WEBSERVER="${1#*=}"; shift ;;
            --install-root=*) INSTALL_ROOT="${1#*=}"; shift ;;
            --admin-user=*) ADMIN_USER="${1#*=}"; shift ;;
            --admin-email=*) ADMIN_EMAIL="${1#*=}"; shift ;;
            --admin-pass=*) ADMIN_PASS="${1#*=}"; shift ;;
            --company=*) COMPANY="${1#*=}"; shift ;;
            --db-host=*) DB_HOST="${1#*=}"; shift ;;
            --db-name=*) DB_NAME="${1#*=}"; shift ;;
            --db-user=*) DB_USER="${1#*=}"; shift ;;
            --db-pass=*) DB_PASS="${1#*=}"; shift ;;
            --db-root-pass=*) DB_ROOT_PASS="${1#*=}"; shift ;;
            --help|-h) usage; exit 0 ;;
            *) die "Unknown option: $1 (try --help)" ;;
        esac
    done
}

detect_os() {
    if [[ ! -f /etc/os-release ]]; then
        die "Unsupported OS: /etc/os-release not found"
    fi
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-unknown}"
    OS_VERSION_ID="${VERSION_ID:-}"
    OS_PRETTY="${PRETTY_NAME:-$OS_ID}"
}

require_root() {
    [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Run as root: sudo ./install.sh"
}

mysql_cmd() {
    if [[ -n "$DB_ROOT_PASS" ]]; then
        mysql -h "$DB_HOST" -uroot -p"$DB_ROOT_PASS" "$@"
    else
        mysql -h "$DB_HOST" -uroot "$@"
    fi
}

normalize_webserver() {
    local choice="${1:-nginx}"
    choice="$(printf '%s' "$choice" | tr '[:upper:]' '[:lower:]')"
    case "$choice" in
        nginx|apache) WEBSERVER="$choice" ;;
        *) die "Web server must be nginx or apache (got: ${1})" ;;
    esac
}

apache_service_unit() {
    if systemctl list-unit-files 2>/dev/null | grep -q '^apache2\.service'; then
        echo "apache2"
    else
        echo "httpd"
    fi
}

apache_log_dir() {
    if [[ -d /var/log/apache2 ]]; then
        echo "/var/log/apache2"
    else
        echo "/var/log/httpd"
    fi
}

apache_configtest() {
    if command -v apache2ctl >/dev/null 2>&1; then
        apache2ctl configtest
    elif command -v apachectl >/dev/null 2>&1; then
        apachectl configtest
    else
        httpd -t
    fi
}

debian_select_php_packages() {
    local sapi="$1"
    local version=""
    local -a packages=()

    if apt-cache show php8.3-cli >/dev/null 2>&1; then
        version="8.3"
    elif apt-cache show php8.2-cli >/dev/null 2>&1; then
        version="8.2"
    else
        warn "PHP 8.2+ not found in default repos; enabling ondrej/php PPA"
        apt-get install -y software-properties-common
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        version="8.3"
    fi

    packages=(
        "php${version}-cli"
        "php${version}-mysql"
        "php${version}-mbstring"
        "php${version}-xml"
        "php${version}-curl"
        "php${version}-zip"
    )
    if [[ "$sapi" == "fpm" ]]; then
        packages+=("php${version}-fpm")
    else
        packages+=("libapache2-mod-php${version}")
    fi

    printf -v DEBIAN_PHP_PACKAGES '%s ' "${packages[@]}"
}

install_packages_debian() {
    log "Installing packages on ${OS_PRETTY} (${WEBSERVER} + PHP)"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y ca-certificates curl git gnupg lsb-release rsync unzip

    local -a web_packages=()
    local php_sapi="fpm"
    if [[ "$WEBSERVER" == "apache" ]]; then
        web_packages=(apache2)
        php_sapi="apache"
    else
        web_packages=(nginx)
    fi

    debian_select_php_packages "$php_sapi"
    # shellcheck disable=SC2086
    apt-get install -y mariadb-server mariadb-client nodejs npm "${web_packages[@]}" $DEBIAN_PHP_PACKAGES

    if ! command -v composer >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
}

install_packages_redhat() {
    log "Installing packages on ${OS_PRETTY} (${WEBSERVER} + PHP)"
    dnf install -y epel-release || true

    local -a web_packages=()
    local -a php_packages=(
        php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip
    )

    if [[ "$WEBSERVER" == "apache" ]]; then
        web_packages=(httpd)
        php_packages=(php "${php_packages[@]}")
    else
        web_packages=(nginx)
        php_packages=(php-fpm "${php_packages[@]}")
    fi

    dnf install -y "${web_packages[@]}" mariadb-server mariadb nodejs npm git rsync unzip \
        "${php_packages[@]}"
    if ! command -v composer >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
    systemctl enable --now mariadb
}

install_packages() {
    [[ "$SKIP_PACKAGES" == "yes" ]] && return 0
    case "$OS_ID" in
        debian|ubuntu|raspbian)
            install_packages_debian
            ;;
        rocky|almalinux|centos|fedora|rhel)
            install_packages_redhat
            ;;
        *)
            die "Unsupported OS '${OS_ID}'. Install nginx or Apache, MariaDB, PHP 8.2+, Node.js, and Composer manually, then re-run with --skip-packages."
            ;;
    esac
}

require_php() {
    local version
    version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);" \
        || die "PHP 8.2+ required (found ${version})"
}

detect_php_fpm_sock() {
    [[ "$WEBSERVER" == "nginx" ]] || return 0
    local sock
    sock="$(find /run/php -maxdepth 1 -name '*.sock' 2>/dev/null | sort | head -1 || true)"
    [[ -n "$sock" ]] || sock="$(find /var/run/php-fpm -maxdepth 1 -name '*.sock' 2>/dev/null | sort | head -1 || true)"
    [[ -n "$sock" ]] || die "Could not find a PHP-FPM socket under /run/php or /var/run/php-fpm"
    PHP_FPM_SOCK="$sock"
}

detect_web_runtime_user() {
    WEB_USER=""

    if [[ "$WEBSERVER" == "nginx" && -n "${PHP_FPM_SOCK:-}" && -S "$PHP_FPM_SOCK" ]]; then
        WEB_USER="$(stat -c '%U' "$PHP_FPM_SOCK" 2>/dev/null || true)"
    fi

    if [[ -z "$WEB_USER" || "$WEB_USER" == "root" ]]; then
        if id www-data >/dev/null 2>&1; then
            WEB_USER="www-data"
        elif [[ "$WEBSERVER" == "apache" ]] && id apache >/dev/null 2>&1; then
            WEB_USER="apache"
        elif [[ "$WEBSERVER" == "nginx" ]] && id nginx >/dev/null 2>&1; then
            WEB_USER="nginx"
        else
            WEB_USER="root"
        fi
    fi
}

check_pass() {
    printf '    \033[1;32mOK\033[0m   %s\n' "$*"
}

check_fail() {
    printf '    \033[1;31mFAIL\033[0m %s\n' "$*" >&2
    CHECK_FAILURES=$((CHECK_FAILURES + 1))
}

require_command() {
    local name="$1"
    if command -v "$name" >/dev/null 2>&1; then
        check_pass "command available: ${name}"
        return 0
    fi
    check_fail "missing required command: ${name}"
    return 1
}

service_active() {
    local unit="$1"
    systemctl is-active --quiet "$unit" 2>/dev/null
}

require_service() {
    local unit="$1"
    if service_active "$unit"; then
        check_pass "service active: ${unit}"
        return 0
    fi
    check_fail "service not running: ${unit}"
    return 1
}

install_parent_dir() {
    dirname "$INSTALL_ROOT"
}

free_disk_mb() {
    local path="$1"
    df -Pm "$path" 2>/dev/null | awk 'NR==2 {print $4}'
}

check_hybrid_preflight() {
    log "Hybrid mode: pre-install system check"
    CHECK_FAILURES=0

    case "$OS_ID" in
        debian|ubuntu|raspbian|rocky|almalinux|centos|fedora|rhel)
            check_pass "supported OS: ${OS_PRETTY}"
            ;;
        *)
            check_fail "unsupported OS: ${OS_ID}"
            ;;
    esac

    local parent_dir
    parent_dir="$(install_parent_dir)"
    if [[ -d "$parent_dir" && -w "$parent_dir" ]]; then
        check_pass "install parent writable: ${parent_dir}"
    else
        check_fail "install parent missing or not writable: ${parent_dir}"
    fi

    if [[ -f "${INSTALL_ROOT}/api/storage/install.lock" ]]; then
        check_fail "already installed (${INSTALL_ROOT}/api/storage/install.lock exists)"
    else
        check_pass "no install.lock present"
    fi

    if [[ -f "${INSTALL_ROOT}/api/storage/setup.token" ]]; then
        warn "Removing stale setup token from a previous hybrid run"
        rm -f "${INSTALL_ROOT}/api/storage/setup.token"
    fi

    local free_mb
    free_mb="$(free_disk_mb "${parent_dir}")"
    if [[ -n "$free_mb" && "$free_mb" -ge 1024 ]]; then
        check_pass "disk space: ${free_mb}MB free on ${parent_dir}"
    elif [[ -n "$free_mb" ]]; then
        check_fail "disk space low: ${free_mb}MB free on ${parent_dir} (need at least 1024MB)"
    else
        check_fail "unable to determine free disk space for ${parent_dir}"
    fi

    if mysql_cmd -e "SELECT 1;" >/dev/null 2>&1; then
        check_pass "MariaDB/MySQL reachable as root on ${DB_HOST}"
    else
        check_fail "cannot connect to MariaDB/MySQL as root on ${DB_HOST}"
    fi

    if [[ "$CHECK_FAILURES" -gt 0 ]]; then
        die "Pre-install system check failed with ${CHECK_FAILURES} error(s). Fix the issues above and re-run."
    fi
}

ensure_hybrid_directories() {
    log "Hybrid mode: ensuring directories and permissions"
    detect_web_runtime_user

    local dirs=(
        "$INSTALL_ROOT"
        "${INSTALL_ROOT}/api"
        "${INSTALL_ROOT}/api/public"
        "${INSTALL_ROOT}/api/storage"
        "${INSTALL_ROOT}/frontend"
        "${INSTALL_ROOT}/frontend/dist"
        "${INSTALL_ROOT}/database"
        "${INSTALL_ROOT}/database/migrations"
    )

    local dir
    for dir in "${dirs[@]}"; do
        mkdir -p "$dir"
    done

    chmod 755 "$INSTALL_ROOT" "${INSTALL_ROOT}/api" "${INSTALL_ROOT}/api/public" \
        "${INSTALL_ROOT}/frontend" "${INSTALL_ROOT}/frontend/dist" \
        "${INSTALL_ROOT}/database" "${INSTALL_ROOT}/database/migrations"

    chmod 770 "${INSTALL_ROOT}/api/storage"
    chown root:"${WEB_USER}" "${INSTALL_ROOT}/api/storage"
    chmod g+s "${INSTALL_ROOT}/api/storage"

    if [[ -f "${INSTALL_ROOT}/api/.env" ]]; then
        chmod 640 "${INSTALL_ROOT}/api/.env"
        chown root:"${WEB_USER}" "${INSTALL_ROOT}/api/.env"
    fi

    if [[ -d "${INSTALL_ROOT}/api/vendor" ]]; then
        chmod -R a+rX "${INSTALL_ROOT}/api/vendor"
    fi

    find "${INSTALL_ROOT}/frontend/dist" -type d -exec chmod 755 {} +
    find "${INSTALL_ROOT}/frontend/dist" -type f -exec chmod 644 {} +

    check_pass "directories created under ${INSTALL_ROOT}"
    check_pass "api/storage owned by root:${WEB_USER} (770) for web setup writes"
}

check_hybrid_postflight() {
    log "Hybrid mode: post-install system check"
    CHECK_FAILURES=0
    detect_web_runtime_user

    local cmd
    for cmd in mysql php composer node npm rsync curl; do
        require_command "$cmd"
    done
    if [[ "$WEBSERVER" == "apache" ]]; then
        if command -v apache2ctl >/dev/null 2>&1 || command -v apachectl >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
            check_pass "Apache control utility available"
        else
            check_fail "missing Apache control utility (apache2ctl/apachectl/httpd)"
        fi
    else
        require_command nginx
    fi

    local version
    version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    if php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"; then
        check_pass "PHP version ${version}"
    else
        check_fail "PHP 8.2+ required (found ${version})"
    fi

    if php -m | grep -qi pdo_mysql; then
        check_pass "PHP extension: pdo_mysql"
    else
        check_fail "PHP extension missing: pdo_mysql"
    fi

    if [[ "$WEBSERVER" == "nginx" ]]; then
        if [[ -n "$PHP_FPM_SOCK" && -S "$PHP_FPM_SOCK" ]]; then
            check_pass "PHP-FPM socket: ${PHP_FPM_SOCK}"
        else
            check_fail "PHP-FPM socket not found"
        fi

        local fpm_unit=""
        if systemctl list-unit-files 2>/dev/null | grep -q '^php8.3-fpm'; then
            fpm_unit="php8.3-fpm"
        elif systemctl list-unit-files 2>/dev/null | grep -q '^php8.2-fpm'; then
            fpm_unit="php8.2-fpm"
        elif systemctl list-unit-files 2>/dev/null | grep -q '^php-fpm'; then
            fpm_unit="php-fpm"
        fi
        if [[ -n "$fpm_unit" ]]; then
            require_service "$fpm_unit"
        else
            check_fail "unable to determine PHP-FPM systemd unit"
        fi

        require_service nginx
        if nginx -t >/dev/null 2>&1; then
            check_pass "nginx configuration valid"
        else
            check_fail "nginx configuration test failed"
        fi
    else
        if apache2ctl -M 2>/dev/null | grep -qiE 'php[0-9]?_module' \
            || apachectl -M 2>/dev/null | grep -qiE 'php[0-9]?_module' \
            || httpd -M 2>/dev/null | grep -qiE 'php[0-9]?_module'; then
            check_pass "Apache mod_php loaded"
        else
            check_fail "Apache mod_php does not appear to be loaded"
        fi

        local apache_unit
        apache_unit="$(apache_service_unit)"
        require_service "$apache_unit"
        if apache_configtest >/dev/null 2>&1; then
            check_pass "Apache configuration valid"
        else
            check_fail "Apache configuration test failed"
        fi
    fi
    if service_active mariadb; then
        check_pass "service active: mariadb"
    elif service_active mysql; then
        check_pass "service active: mysql"
    else
        check_fail "MariaDB/MySQL service is not running"
    fi

    check_pass "web stack: ${WEBSERVER}"

    local path
    local required_paths=(
        "${INSTALL_ROOT}/api/.env"
        "${INSTALL_ROOT}/api/public/index.php"
        "${INSTALL_ROOT}/api/vendor/autoload.php"
        "${INSTALL_ROOT}/frontend/dist/index.html"
        "${INSTALL_ROOT}/database/migrate.php"
        "${INSTALL_ROOT}/database/bootstrap.php"
        "${INSTALL_ROOT}/api/storage"
    )
    for path in "${required_paths[@]}"; do
        if [[ -e "$path" ]]; then
            check_pass "path exists: ${path}"
        else
            check_fail "missing required path: ${path}"
        fi
    done

    if [[ -f "${INSTALL_ROOT}/api/.env" ]]; then
        local env_mode
        env_mode="$(stat -c '%a' "${INSTALL_ROOT}/api/.env")"
        if [[ "$env_mode" == "600" || "$env_mode" == "640" ]]; then
            check_pass "api/.env permissions (${env_mode})"
        else
            check_fail "api/.env should be mode 640 (found ${env_mode})"
        fi
    fi

    if [[ -d "${INSTALL_ROOT}/api/storage" ]]; then
        local storage_owner storage_mode
        storage_owner="$(stat -c '%U:%G' "${INSTALL_ROOT}/api/storage")"
        storage_mode="$(stat -c '%a' "${INSTALL_ROOT}/api/storage")"
        if [[ "$storage_owner" == "root:${WEB_USER}" && "$storage_mode" == "775" ]] \
            || [[ "$storage_owner" == "${WEB_USER}:${WEB_USER}" && "$storage_mode" == "700" ]] \
            || [[ "$storage_owner" == "root:${WEB_USER}" && "$storage_mode" == "770" ]]; then
            check_pass "api/storage ownership ${storage_owner} mode ${storage_mode}"
        else
            check_fail "api/storage should be writable by ${WEB_USER} (found ${storage_owner} ${storage_mode})"
        fi
    fi

    if [[ -f "${INSTALL_ROOT}/api/storage/setup.token" ]]; then
        local token_mode token_size
        token_mode="$(stat -c '%a' "${INSTALL_ROOT}/api/storage/setup.token")"
        token_size="$(wc -c < "${INSTALL_ROOT}/api/storage/setup.token" | tr -d ' ')"
        if [[ "$token_mode" == "640" || "$token_mode" == "600" || "$token_mode" == "660" ]]; then
            check_pass "setup.token permissions (${token_mode})"
        else
            check_fail "setup.token should be mode 640 (found ${token_mode})"
        fi
        if [[ "$token_size" -ge 16 ]]; then
            check_pass "setup.token present"
        else
            check_fail "setup.token is missing or too short"
        fi
    else
        check_fail "setup.token not created"
    fi

    if [[ ! -f "${INSTALL_ROOT}/api/storage/install.lock" ]]; then
        check_pass "install.lock not present (web setup still allowed)"
    else
        check_fail "install.lock already exists; web setup should not run"
    fi

    if mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "USE \`${DB_NAME}\`; SELECT 1;" >/dev/null 2>&1; then
        check_pass "database login: ${DB_USER}@${DB_HOST}/${DB_NAME}"
    else
        check_fail "application database user cannot connect"
    fi

    local user_count company_count
    user_count="$(mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM users;" "$DB_NAME" 2>/dev/null || echo -1)"
    company_count="$(mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM company;" "$DB_NAME" 2>/dev/null || echo -1)"
    if [[ "$user_count" == "0" && "$company_count" == "0" ]]; then
        check_pass "database ready for web bootstrap (no admin/company rows yet)"
    elif [[ "$user_count" -ge 0 && "$company_count" -ge 0 ]]; then
        check_fail "database already bootstrapped (users=${user_count}, company=${company_count})"
    else
        check_fail "unable to verify users/company tables"
    fi

    if mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM schema_migrations;" "$DB_NAME" 2>/dev/null | grep -qv '^0$'; then
        check_pass "database migrations applied"
    else
        check_fail "no migrations recorded in schema_migrations"
    fi

    local health_body health_code
    health_body="$(curl -fsS --max-time 10 -H "Host: ${SERVER_NAME}" "http://127.0.0.1/health" 2>/dev/null || true)"
    if [[ "$health_body" == *'"status"'* ]] || [[ "$health_body" == *"ok"* ]]; then
        check_pass "HTTP health endpoint responds"
    else
        check_fail "HTTP health endpoint check failed (GET http://127.0.0.1/health with Host: ${SERVER_NAME})"
    fi

    local setup_status
    setup_status="$(curl -fsS --max-time 10 -H "Host: ${SERVER_NAME}" "http://127.0.0.1/api/v1/setup/status" 2>/dev/null || true)"
    if [[ "$setup_status" == *'"webSetupPending":true'* ]]; then
        check_pass "setup API reports web setup pending"
    else
        check_fail "setup API status unexpected: ${setup_status:-<no response>}"
    fi

    if [[ "$CHECK_FAILURES" -gt 0 ]]; then
        die "Post-install system check failed with ${CHECK_FAILURES} error(s). Resolve them before opening the setup URL."
    fi

    log "Hybrid mode: all system checks passed"
}

collect_config() {
    local default_url="http://$(hostname -f 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}')"
    [[ -z "$default_url" || "$default_url" == "http://" ]] && default_url="http://127.0.0.1"

    prompt WEB_URL "Public dashboard URL" "${WEB_URL:-$default_url}"
    [[ -n "$WEB_URL" ]] || die "--web-url is required in unattended mode"

    SERVER_NAME="$(printf '%s' "$WEB_URL" | sed -E 's#^[a-zA-Z]+://##; s#/.*##; s#:.*##')"
    [[ -z "$SERVER_NAME" ]] && SERVER_NAME="_"

    if [[ "$UNATTENDED" != "yes" ]]; then
        prompt WEBSERVER "Web server (nginx or apache)" "$WEBSERVER"
    fi
    normalize_webserver "$WEBSERVER"

    if [[ "$PREPARE_WEB_SETUP" == "yes" ]]; then
        if [[ -z "$DB_PASS" ]]; then
            DB_PASS="$(random_secret 24)"
            warn "Generated database password for ${DB_USER}"
        fi
        JWT_SECRET="${JWT_SECRET:-$(random_secret 48)}"
        PW_SALT="${PW_SALT:-$(random_secret 16)}"
        return 0
    fi

    prompt ADMIN_USER "Admin username" "$ADMIN_USER"
    prompt ADMIN_EMAIL "Admin email" "${ADMIN_EMAIL:-admin@${SERVER_NAME}}"
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

    if [[ -z "$DB_PASS" ]]; then
        DB_PASS="$(random_secret 24)"
        warn "Generated database password for ${DB_USER}"
    fi

    JWT_SECRET="${JWT_SECRET:-$(random_secret 48)}"
    PW_SALT="${PW_SALT:-$(random_secret 16)}"
}

sync_application() {
    log "Deploying application to ${INSTALL_ROOT}"
    mkdir -p "$INSTALL_ROOT"
    rsync -a --delete \
        --exclude '.git' \
        --exclude 'frontend/node_modules' \
        --exclude 'frontend/dist' \
        --exclude 'api/vendor' \
        --exclude 'legacy' \
        "${MY_PATH}/" "${INSTALL_ROOT}/"
}

setup_database() {
    log "Creating database ${DB_NAME}"
    mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql_cmd -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql_cmd -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
    mysql_cmd -e "FLUSH PRIVILEGES;"

    local table_count
    table_count="$(mysql_cmd -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users';" 2>/dev/null || echo 0)"
    if [[ "${table_count}" == "0" ]]; then
        log "Importing base schema"
        mysql_cmd "$DB_NAME" < "${INSTALL_ROOT}/database/db_create.sql"
    else
        warn "Database ${DB_NAME} already has tables; skipping db_create.sql import"
    fi

    export DB_HOST DB_NAME DB_USER DB_PASS
    php "${INSTALL_ROOT}/database/migrate.php"
}

write_api_env() {
    log "Writing api/.env"
    cat > "${INSTALL_ROOT}/api/.env" <<EOF
APP_ENV=production
APP_DEBUG=false
APP_URL=${WEB_URL}

DB_HOST=${DB_HOST}
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}

JWT_SECRET=${JWT_SECRET}
JWT_TTL=86400

PW_SALT=${PW_SALT}
YOUR_COMPANY=${COMPANY:-Pending setup}
CORS_ORIGIN=${WEB_URL}

MAIL_FROM=alerts@${SERVER_NAME}
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
EOF
    chmod 600 "${INSTALL_ROOT}/api/.env"
}

build_frontend() {
    log "Building frontend"
    pushd "${INSTALL_ROOT}/frontend" >/dev/null
    npm install
    VITE_API_BASE= npm run build
    popd >/dev/null
}

install_api_dependencies() {
    log "Installing API dependencies"
    pushd "${INSTALL_ROOT}/api" >/dev/null
    composer install --no-dev --optimize-autoloader --no-interaction
    popd >/dev/null
}

bootstrap_admin() {
    log "Creating admin user and company record"
    export DB_HOST DB_NAME DB_USER DB_PASS
    php "${INSTALL_ROOT}/database/bootstrap.php" \
        --admin-user="$ADMIN_USER" \
        --admin-email="$ADMIN_EMAIL" \
        --admin-pass="$ADMIN_PASS" \
        --company="$COMPANY"
}

ensure_dashboard_ssh_keys() {
    log "Generating PatchDashboard SSH keypair for server enrollment"
    php "${INSTALL_ROOT}/database/ensure_ssh_keys.php" >/dev/null
}

write_setup_token() {
    log "Generating one-time web setup token"
    detect_web_runtime_user
    SETUP_TOKEN="$(random_secret 32)"
    mkdir -p "${INSTALL_ROOT}/api/storage"
    printf '%s\n' "$SETUP_TOKEN" > "${INSTALL_ROOT}/api/storage/setup.token"
    chmod 770 "${INSTALL_ROOT}/api/storage"
    chown root:"${WEB_USER}" "${INSTALL_ROOT}/api/storage"
    chmod g+s "${INSTALL_ROOT}/api/storage"
    chmod 640 "${INSTALL_ROOT}/api/storage/setup.token"
    chown root:"${WEB_USER}" "${INSTALL_ROOT}/api/storage/setup.token"
    rm -f "${INSTALL_ROOT}/api/storage/install.lock"
}

write_install_lock() {
    mkdir -p "${INSTALL_ROOT}/api/storage"
    printf '{"installed_at":"%s","method":"cli"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        > "${INSTALL_ROOT}/api/storage/install.lock"
    chmod 600 "${INSTALL_ROOT}/api/storage/install.lock"
    rm -f "${INSTALL_ROOT}/api/storage/setup.token"
}

configure_nginx() {
    log "Configuring nginx + PHP-FPM"
    local site_available="/etc/nginx/sites-available/patchdashboard"
    local site_enabled="/etc/nginx/sites-enabled/patchdashboard"
    local web_root="${INSTALL_ROOT}/frontend/dist"
    local api_root="${INSTALL_ROOT}/api/public"

    sed \
        -e "s|__SERVER_NAME__|${SERVER_NAME}|g" \
        -e "s|__WEB_ROOT__|${web_root}|g" \
        -e "s|__API_ROOT__|${api_root}|g" \
        -e "s|__PHP_FPM_SOCK__|${PHP_FPM_SOCK}|g" \
        "${TEMPLATE_DIR}/nginx-site.conf" > "$site_available"

    if [[ -d /etc/nginx/sites-enabled ]]; then
        ln -sf "$site_available" "$site_enabled"
        rm -f /etc/nginx/sites-enabled/default
    else
        ln -sf "$site_available" /etc/nginx/conf.d/patchdashboard.conf
    fi

    nginx -t
    systemctl enable nginx
    systemctl restart nginx
    disable_other_webserver
}

configure_apache() {
    log "Configuring Apache + mod_php"
    local web_root="${INSTALL_ROOT}/frontend/dist"
    local api_root="${INSTALL_ROOT}/api/public"
    local log_dir
    log_dir="$(apache_log_dir)"
    local site_conf=""

    if [[ -d /etc/apache2/sites-available ]]; then
        site_conf="/etc/apache2/sites-available/patchdashboard.conf"
        a2enmod rewrite >/dev/null 2>&1 || true
    else
        site_conf="/etc/httpd/conf.d/patchdashboard.conf"
    fi

    sed \
        -e "s|__SERVER_NAME__|${SERVER_NAME}|g" \
        -e "s|__WEB_ROOT__|${web_root}|g" \
        -e "s|__API_ROOT__|${api_root}|g" \
        -e "s|__APACHE_LOG_DIR__|${log_dir}|g" \
        "${TEMPLATE_DIR}/apache-site.conf" > "$site_conf"

    if [[ -d /etc/apache2/sites-available ]]; then
        a2dissite 000-default.conf default 2>/dev/null || true
        a2ensite patchdashboard.conf >/dev/null 2>&1 || a2ensite patchdashboard >/dev/null 2>&1 || true
    fi

    apache_configtest
    local apache_unit
    apache_unit="$(apache_service_unit)"
    systemctl enable "$apache_unit"
    systemctl restart "$apache_unit"
    disable_other_webserver
}

disable_other_webserver() {
    if [[ "$WEBSERVER" == "nginx" ]]; then
        systemctl stop apache2 httpd 2>/dev/null || true
        systemctl disable apache2 httpd 2>/dev/null || true
    else
        systemctl stop nginx 2>/dev/null || true
        systemctl disable nginx 2>/dev/null || true
    fi
}

configure_webserver() {
    if [[ "$WEBSERVER" == "apache" ]]; then
        configure_apache
    else
        configure_nginx
    fi
}

configure_php_fpm() {
    [[ "$WEBSERVER" == "nginx" ]] || return 0
    log "Ensuring PHP-FPM is running"
    if systemctl list-unit-files | grep -q '^php8.3-fpm'; then
        systemctl enable --now php8.3-fpm
    elif systemctl list-unit-files | grep -q '^php8.2-fpm'; then
        systemctl enable --now php8.2-fpm
    elif systemctl list-unit-files | grep -q '^php-fpm'; then
        systemctl enable --now php-fpm
    fi
}

configure_php_runtime() {
    if [[ "$WEBSERVER" == "nginx" ]]; then
        configure_php_fpm
    else
        log "Using Apache mod_php (no PHP-FPM service required)"
    fi
}

configure_mariadb() {
    log "Ensuring MariaDB is running"
    systemctl enable mariadb 2>/dev/null || systemctl enable mysql 2>/dev/null || true
    systemctl start mariadb 2>/dev/null || systemctl start mysql 2>/dev/null || true
}

print_web_setup_summary() {
    cat <<EOF

PatchDashboard stack is ready for web setup.

  Setup URL:   ${WEB_URL}/setup?token=${SETUP_TOKEN}
  Web stack:   ${WEBSERVER}
  Health:      ${WEB_URL}/health
  Install dir: ${INSTALL_ROOT}

  Database:    ${DB_NAME} on ${DB_HOST} (user ${DB_USER})

Open the setup URL in your browser to create the admin account and company record.
The token is single-use and the setup page disables itself when finished.

Keep this token private until setup completes.

EOF
}

print_summary() {
    local install_key
    install_key="$(mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT install_key FROM company LIMIT 1;" "$DB_NAME" 2>/dev/null || echo '(see database)')"
    cat <<EOF

PatchDashboard installation complete.

  Dashboard:   ${WEB_URL}
  Web stack:   ${WEBSERVER}
  Health:      ${WEB_URL}/health
  Install dir: ${INSTALL_ROOT}

  Admin login: ${ADMIN_USER}
  Password:    ${ADMIN_PASS}

  Agent install key (PD_AUTH_KEY): ${install_key}

  Dashboard SSH public key:
$(cat "${INSTALL_ROOT}/api/storage/ssh/patchdashboard_ed25519.pub" 2>/dev/null || echo '    (run database/ensure_ssh_keys.php)')

Next steps:
  1. Open ${WEB_URL} and sign in.
  2. Enroll managed nodes (installs SSH key + agent credentials):
       sudo ${INSTALL_ROOT}/agents/client/enroll-server.sh \\
         --host SERVER_IP --user root --api-base ${WEB_URL} --auth-key '${install_key}'
  3. Or install manually on each node:
       sudo ${INSTALL_ROOT}/agents/client/install-agent.sh
       # credentials in /etc/patchdashboard/agent.env
  4. Trust enrolled servers under Servers in the dashboard.
  5. See ${INSTALL_ROOT}/agents/client/cron/patchdashboard for cron schedule

To re-run migrations after upgrades:
  DB_HOST=${DB_HOST} DB_NAME=${DB_NAME} DB_USER=${DB_USER} DB_PASS='***' \\
    php ${INSTALL_ROOT}/database/migrate.php

EOF
}

main() {
    parse_args "$@"
    require_root
    detect_os
    collect_config

    if [[ "$PREPARE_WEB_SETUP" == "yes" ]]; then
        check_hybrid_preflight
    fi

    install_packages
    require_php
    if [[ "$WEBSERVER" == "nginx" ]]; then
        detect_php_fpm_sock
    fi
    configure_mariadb
    sync_application
    setup_database
    write_api_env
    install_api_dependencies
    build_frontend
    configure_php_runtime
    configure_webserver

    if [[ "$PREPARE_WEB_SETUP" == "yes" ]]; then
        ensure_hybrid_directories
        write_setup_token
        check_hybrid_postflight
        print_web_setup_summary
        return 0
    fi

    bootstrap_admin
    ensure_dashboard_ssh_keys
    write_install_lock
    print_summary
}

main "$@"
