#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment_path="$repository_root/.env"
environment_example_path="$repository_root/.env.example"

cd "$repository_root"

if [[ ! -f "$environment_path" ]]; then
    cp "$environment_example_path" "$environment_path"
    printf '%s\n' 'Created local .env from .env.example.'
else
    printf '%s\n' 'Keeping the existing local .env file unchanged except for missing values you provide.'
fi

get_dotenv_value() {
    local key="$1"
    local line value
    line="$(grep -E "^${key}=" "$environment_path" | head -n 1 || true)"
    value="${line#*=}"
    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

dotenv_value() {
    local value="$1"
    if [[ "$value" =~ [[:space:]#\"] ]]; then
        value="${value//\\/\\\\}"
        value="${value//\"/\\\"}"
        printf '"%s"' "$value"
    else
        printf '%s' "$value"
    fi
}

set_dotenv_value() {
    local key="$1" value="$2" replacement temporary
    replacement="${key}=$(dotenv_value "$value")"
    temporary="$(mktemp)"
    if grep -q -E "^${key}=" "$environment_path"; then
        awk -v key="$key" -v replacement="$replacement" '
            index($0, key "=") == 1 { print replacement; next }
            { print }
        ' "$environment_path" > "$temporary"
        mv "$temporary" "$environment_path"
    else
        rm -f "$temporary"
        printf '\n%s\n' "$replacement" >> "$environment_path"
    fi
}

read_setup_value() {
    local key="$1" environment_key="$2" prompt="$3" secret="$4"
    local existing provided
    existing="$(get_dotenv_value "$key")"
    if [[ -n "$existing" ]]; then
        printf '%s' "$existing"
        return
    fi

    provided="${!environment_key:-}"
    if [[ -n "$provided" ]]; then
        set_dotenv_value "$key" "$provided"
        printf '%s' "$provided"
        return
    fi

    if [[ ! -t 0 || "${ATTENDPRO_NON_INTERACTIVE:-}" == "1" ]]; then
        return
    fi

    if [[ "$secret" == "true" ]]; then
        read -r -s -p "$prompt: " provided
        printf '\n' >&2
    else
        read -r -p "$prompt: " provided
    fi

    if [[ -n "$provided" ]]; then
        set_dotenv_value "$key" "$provided"
        printf '%s' "$provided"
    fi
}

composer install --no-interaction

if [[ -z "$(get_dotenv_value APP_KEY)" ]]; then
    php artisan key:generate --force --no-interaction
fi

gmail_address="$(read_setup_value MAIL_USERNAME ATTENDPRO_MAIL_USERNAME 'Gmail address' false)"
read_setup_value MAIL_PASSWORD ATTENDPRO_MAIL_PASSWORD 'Gmail App Password' true >/dev/null
read_setup_value GOOGLE_CLIENT_ID ATTENDPRO_GOOGLE_CLIENT_ID 'Google OAuth Client ID' false >/dev/null
read_setup_value GOOGLE_CLIENT_SECRET ATTENDPRO_GOOGLE_CLIENT_SECRET 'Google OAuth Client Secret' true >/dev/null
read_setup_value GOOGLE_REDIRECT_URI ATTENDPRO_GOOGLE_REDIRECT_URI 'Google OAuth redirect URI' false >/dev/null

if [[ -z "$(get_dotenv_value MAIL_FROM_ADDRESS)" && -n "$gmail_address" ]]; then
    set_dotenv_value MAIL_FROM_ADDRESS "$gmail_address"
fi

php artisan config:clear
php artisan cache:clear

printf '%s\n' 'Environment configuration complete.'
