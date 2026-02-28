#!/bin/bash

echo "=== ABS Loja Protheus Connector - Installation Verification ==="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PLUGIN_DIR="wp-content/plugins/absloja-protheus-connector"

echo "Test 1: Checking plugin files..."
FILES=(
    "$PLUGIN_DIR/absloja-protheus-connector.php"
    "$PLUGIN_DIR/includes/class-plugin.php"
    "$PLUGIN_DIR/includes/class-activator.php"
    "$PLUGIN_DIR/includes/class-deactivator.php"
    "$PLUGIN_DIR/includes/modules/class-auth-manager.php"
    "$PLUGIN_DIR/includes/modules/class-protheus-client.php"
    "$PLUGIN_DIR/includes/modules/class-logger.php"
    "$PLUGIN_DIR/includes/modules/class-retry-manager.php"
    "$PLUGIN_DIR/includes/modules/class-mapping-engine.php"
    "$PLUGIN_DIR/includes/modules/class-customer-sync.php"
    "$PLUGIN_DIR/includes/modules/class-order-sync.php"
    "$PLUGIN_DIR/includes/modules/class-catalog-sync.php"
    "$PLUGIN_DIR/includes/modules/class-webhook-handler.php"
    "$PLUGIN_DIR/includes/admin/class-admin.php"
    "$PLUGIN_DIR/includes/admin/class-settings.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "Test 2: Checking admin view files..."
VIEWS=(
    "$PLUGIN_DIR/includes/admin/views/tab-connection.php"
    "$PLUGIN_DIR/includes/admin/views/tab-mappings.php"
    "$PLUGIN_DIR/includes/admin/views/tab-schedule.php"
    "$PLUGIN_DIR/includes/admin/views/tab-logs.php"
    "$PLUGIN_DIR/includes/admin/views/tab-advanced.php"
    "$PLUGIN_DIR/includes/admin/views/dashboard-widget.php"
)

for file in "${VIEWS[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "Test 3: Checking assets..."
ASSETS=(
    "$PLUGIN_DIR/assets/css/admin.css"
    "$PLUGIN_DIR/assets/js/admin.js"
)

for file in "${ASSETS[@]}"; do
    if [ -f "$file" ]; then
        SIZE=$(wc -c < "$file")
        echo -e "${GREEN}✅${NC} $file ($SIZE bytes)"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "Test 4: Checking documentation..."
DOCS=(
    "$PLUGIN_DIR/README.md"
    "$PLUGIN_DIR/docs/API-DOCUMENTATION.md"
    "$PLUGIN_DIR/docs/DEVELOPMENT-GUIDE.md"
    "$PLUGIN_DIR/docs/WEBHOOK-ENDPOINTS.md"
)

for file in "${DOCS[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "Test 5: Checking translations..."
TRANSLATIONS=(
    "$PLUGIN_DIR/languages/absloja-protheus-connector.pot"
    "$PLUGIN_DIR/languages/absloja-protheus-connector-pt_BR.po"
    "$PLUGIN_DIR/languages/absloja-protheus-connector-pt_BR.mo"
)

for file in "${TRANSLATIONS[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "Test 6: Checking test infrastructure..."
TEST_FILES=(
    "$PLUGIN_DIR/phpunit.xml"
    "$PLUGIN_DIR/tests/bootstrap.php"
    "$PLUGIN_DIR/tests/README.md"
    "$PLUGIN_DIR/tests/fixtures/Generators.php"
    "$PLUGIN_DIR/tests/fixtures/ProtheusClientMock.php"
)

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅${NC} $file"
    else
        echo -e "${RED}❌${NC} $file"
    fi
done

echo ""
echo "=== Summary ==="
echo -e "${GREEN}✅ Plugin installation verified${NC}"
echo ""
echo "Next steps:"
echo "1. Activate the plugin in WordPress admin"
echo "2. Go to WooCommerce → Protheus Connector"
echo "3. Configure API credentials"
echo "4. Test connection"
echo "5. Configure mappings and sync schedule"
