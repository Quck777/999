#!/usr/bin/env bash
# ============================================================
#   Realm of Shadows — Launcher Script
#   Works on Linux, macOS, WSL
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PUBLIC_DIR="$SCRIPT_DIR/public"
PORT=8080

echo ""
echo "=========================================="
echo "   Realm of Shadows - MMORPG"
echo "=========================================="
echo ""

# Check if public directory exists
if [ ! -d "$PUBLIC_DIR" ]; then
    echo "[ERROR] public/ directory not found at:"
    echo "  $PUBLIC_DIR"
    echo ""
    echo "Please make sure this script is in the game root folder."
    echo ""
    read -p "Press Enter to exit..."
    exit 1
fi

SERVER_STARTED=false

# Try PHP built-in server
if command -v php >/dev/null 2>&1; then
    echo "[OK] PHP found"
    echo ""
    echo "Starting PHP server at: http://localhost:$PORT"
    echo "Open this URL in your browser."
    echo "Press Ctrl+C to stop the server."
    echo ""
    echo "=========================================="
    echo ""
    cd "$PUBLIC_DIR"
    php -S "localhost:$PORT"
    SERVER_STARTED=true

# Try Python 3
elif command -v python3 >/dev/null 2>&1; then
    echo "[OK] Python 3 found"
    echo ""
    echo "Starting Python server at: http://localhost:$PORT"
    echo "Open this URL in your browser."
    echo "Press Ctrl+C to stop the server."
    echo ""
    echo "=========================================="
    echo ""
    cd "$PUBLIC_DIR"
    python3 -m http.server "$PORT"
    SERVER_STARTED=true

# Try Python 2
elif command -v python >/dev/null 2>&1; then
    echo "[OK] Python found"
    echo ""
    echo "Starting Python server at: http://localhost:$PORT"
    echo "Open this URL in your browser."
    echo "Press Ctrl+C to stop the server."
    echo ""
    echo "=========================================="
    echo ""
    cd "$PUBLIC_DIR"
    python -m SimpleHTTPServer "$PORT" 2>/dev/null || python -m http.server "$PORT"
    SERVER_STARTED=true

# Fallback: open index.html directly (demo mode)
else
    echo "[INFO] No PHP or Python found on your system."
    echo ""
    echo "The game will open in DEMO MODE (single-player, no server needed)."
    echo ""

    # Try to open in default browser
    if command -v xdg-open >/dev/null 2>&1; then
        echo "Opening in browser..."
        xdg-open "$PUBLIC_DIR/index.html" 2>/dev/null &
    elif command -v open >/dev/null 2>&1; then
        echo "Opening in browser..."
        open "$PUBLIC_DIR/index.html" 2>/dev/null &
    elif command -v sensible-browser >/dev/null 2>&1; then
        echo "Opening in browser..."
        sensible-browser "$PUBLIC_DIR/index.html" 2>/dev/null &
    else
        echo "Could not auto-open browser."
        echo "Please open this file manually:"
        echo "  $PUBLIC_DIR/index.html"
    fi

    echo ""
    echo "=========================================="
fi

# If server was started and user pressed Ctrl+C, keep terminal open
if [ "$SERVER_STARTED" = true ]; then
    echo ""
    echo "Server stopped."
fi

echo ""
read -p "Press Enter to exit..."
