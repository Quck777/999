#!/bin/bash
# ============================================================================
# Realm of Shadows — Deployment Script
# ============================================================================
set -euo pipefail

APP_NAME="mmo-rpg"
APP_DIR="/var/www/${APP_NAME}"
REPO_DIR="${APP_DIR}/releases"

echo "=========================================="
echo "  Realm of Shadows — Deploy Script"
echo "=========================================="

# 1. Создание структуры директорий
echo "[1/7] Создание директорий..."
sudo mkdir -p "${APP_DIR}"/{public/{css,js,images},app/{Controllers,Models,Views,Middleware,Services,Core},config,database,storage/{cache,uploads,logs},logs,deploy}

# 2. Установка прав доступа
echo "[2/7] Установка прав..."
sudo chown -R www-data:www-data "${APP_DIR}"
sudo chmod -R 755 "${APP_DIR}"
sudo chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/logs"
sudo chmod 600 "${APP_DIR}/.env"

# 3. Проверка PHP
echo "[3/7] Проверка PHP..."
PHP_VERSION=$(php -v | head -1 | grep -oP '(?<=PHP )\d+\.\d+')
echo "  PHP версия: ${PHP_VERSION}"

# 4. Проверка расширений
echo "[4/7] Проверка расширений PHP..."
REQUIRED_EXTS=("pdo" "pdo_mysql" "mbstring" "json" "session" "openssl")
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m | grep -qi "^${ext}$"; then
        echo "  ✅ ${ext}"
    else
        echo "  ❌ ${ext} — НЕ УСТАНОВЛЕНО"
    fi
done

# 5. Оптимизация автозагрузки (если Composer)
echo "[5/7] Оптимизация..."
if [ -f "${APP_DIR}/vendor/autoload.php" ]; then
    cd "${APP_DIR}" && composer dump-autoload --optimize
fi

# 6. Очистка кэша
echo "[6/7] Очистка кэша..."
rm -rf "${APP_DIR}/storage/cache/"*

# 7. Перезапуск PHP-FPM
echo "[7/7] Перезапуск PHP-FPM..."
sudo systemctl reload php8.2-fpm 2>/dev/null || echo "  (перезапуск не требуется или нет прав)"

echo ""
echo "=========================================="
echo "  ✅ Деплой завершён!"
echo "=========================================="
