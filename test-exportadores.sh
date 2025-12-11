#!/bin/bash

# ============================================================================
# SCRIPT DE VALIDACIÓN: Exportadores de Reportes
# Verifica que todos los archivos estén correctos y sin errores de sintaxis
# ============================================================================

echo "======================================================================"
echo "🧪 VALIDACIÓN DE EXPORTADORES DE REPORTES"
echo "======================================================================"
echo ""

# Contador de errores
ERRORS=0

# Función para validar sintaxis PHP
validate_php() {
    local file=$1
    local name=$2

    if [ -f "$file" ]; then
        echo -n "Validando $name... "
        if php -l "$file" > /dev/null 2>&1; then
            echo "✅ OK"
        else
            echo "❌ ERROR"
            php -l "$file"
            ERRORS=$((ERRORS + 1))
        fi
    else
        echo "⚠️  $name no existe: $file"
        ERRORS=$((ERRORS + 1))
    fi
}

# Función para verificar que archivo existe
check_file() {
    local file=$1
    local name=$2

    if [ -f "$file" ]; then
        echo "✅ $name existe"
    else
        echo "❌ $name NO existe: $file"
        ERRORS=$((ERRORS + 1))
    fi
}

echo "📋 1. VALIDANDO SERVICIOS"
echo "----------------------------------------------------------------------"
validate_php "app/Services/CreditReportFormatterService.php" "CreditReportFormatterService"
validate_php "app/Services/CreditPdfReportService.php" "CreditPdfReportService"
validate_php "app/Services/CreditHtmlReportService.php" "CreditHtmlReportService"
echo ""

echo "📋 2. VALIDANDO EXPORTADORES"
echo "----------------------------------------------------------------------"
validate_php "app/Exports/CreditsExport.php" "CreditsExport"
echo ""

echo "📋 3. VALIDANDO CONTROLADOR"
echo "----------------------------------------------------------------------"
validate_php "app/Http/Controllers/Api/CreditReportController.php" "CreditReportController"
echo ""

echo "📋 4. VALIDANDO VISTAS BLADE"
echo "----------------------------------------------------------------------"
check_file "resources/views/reports/credits-pdf.blade.php" "Vista PDF"
check_file "resources/views/reports/credits-html.blade.php" "Vista HTML"
echo ""

echo "📋 5. VALIDANDO DOCUMENTACIÓN"
echo "----------------------------------------------------------------------"
check_file "GUIA-EXPORTADORES-REPORTES.md" "Guía de Exportadores"
check_file "RUTAS-REPORTES-EJEMPLO.php" "Rutas de Ejemplo"
check_file "RESUMEN-EXPORTADORES-IMPLEMENTADOS.md" "Resumen de Implementación"
check_file "EJEMPLOS-REPORTES-ICONOS.md" "Ejemplos de Reportes"

# Verificar si existe en el directorio actual o en el padre
if [ -f "SISTEMA-ESTANDARIZADO-ESTADOS.md" ]; then
    echo "✅ Sistema Estandarizado existe (directorio actual)"
elif [ -f "../SISTEMA-ESTANDARIZADO-ESTADOS.md" ]; then
    echo "✅ Sistema Estandarizado existe (directorio padre)"
else
    echo "⚠️  Sistema Estandarizado NO encontrado (no crítico)"
fi
echo ""

echo "📋 6. VALIDANDO MODELO CREDIT"
echo "----------------------------------------------------------------------"
validate_php "app/Models/Credit.php" "Credit Model"
echo ""

echo "======================================================================"
echo "📊 RESUMEN DE VALIDACIÓN"
echo "======================================================================"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo "✅ TODAS LAS VALIDACIONES PASARON CORRECTAMENTE"
    echo ""
    echo "🎉 El sistema de exportadores está listo para usar!"
    echo ""
    echo "📝 Próximos pasos:"
    echo "   1. Agregar rutas de RUTAS-REPORTES-EJEMPLO.php a routes/api.php"
    echo "   2. Verificar que las dependencias estén instaladas:"
    echo "      - composer require maatwebsite/excel"
    echo "      - composer require barryvdh/laravel-dompdf"
    echo "   3. Probar los endpoints con datos reales"
    echo "   4. Consultar GUIA-EXPORTADORES-REPORTES.md para más información"
    echo ""
    exit 0
else
    echo "❌ SE ENCONTRARON $ERRORS ERRORES"
    echo ""
    echo "Por favor revisa los mensajes de error arriba y corrige los archivos."
    echo ""
    exit 1
fi
