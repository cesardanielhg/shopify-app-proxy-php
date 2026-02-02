<?php
/**
 * Shopify Token Auto-Refresh - Versión Inteligente con Logs
 * Solo se ejecuta si han pasado 24 horas o más desde la última actualización
 * Llámalo desde tu archivo principal con: require_once 'shopify_token_check.php';
 */

// Función helper para logs consistentes
function logTokenRefresh($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = "[Shopify Token][$type][$timestamp]";
    echo "$prefix $message\n";
    error_log("$prefix $message");
}

logTokenRefresh("=== Iniciando verificación de token ===");

// Configuración
$shopifyUrl = getenv('SHOPIFY_TOKEN_URL');
$railwayToken = getenv('RAILWAY_TOKEN');
$railwayProjectId = getenv('RAILWAY_PROJECT_ID');
$railwayEnvironmentId = getenv('RAILWAY_ENVIRONMENT_ID');
$tokenVarName = 'SHOPIFY_ADMIN_TOKEN';
$timestampVarName = 'SHOPIFY_TOKEN_LAST_UPDATE';
$intervalHours = 24;

logTokenRefresh("Leyendo variables de entorno...");

// Validar configuración mínima
if (!$shopifyUrl) {
    logTokenRefresh("ERROR: Falta variable SHOPIFY_TOKEN_URL", 'ERROR');
    return;
}
if (!$railwayToken) {
    logTokenRefresh("ERROR: Falta variable RAILWAY_TOKEN", 'ERROR');
    return;
}
if (!$railwayProjectId) {
    logTokenRefresh("ERROR: Falta variable RAILWAY_PROJECT_ID", 'ERROR');
    return;
}
if (!$railwayEnvironmentId) {
    logTokenRefresh("ERROR: Falta variable RAILWAY_ENVIRONMENT_ID", 'ERROR');
    return;
}

logTokenRefresh("✓ Todas las variables de entorno están configuradas");

// Obtener timestamp de última actualización
$lastUpdate = getenv($timestampVarName);
$now = time();

logTokenRefresh("Timestamp actual: $now (" . date('Y-m-d H:i:s', $now) . ")");
logTokenRefresh("Última actualización: " . ($lastUpdate ?: 'nunca') . ($lastUpdate ? " (" . date('Y-m-d H:i:s', (int)$lastUpdate) . ")" : ""));

// Verificar si necesita actualización
if ($lastUpdate) {
    $hoursSinceUpdate = ($now - (int)$lastUpdate) / 3600;
    $hoursRounded = round($hoursSinceUpdate, 2);
    
    logTokenRefresh("Tiempo transcurrido: $hoursRounded horas (de $intervalHours requeridas)");
    
    if ($hoursSinceUpdate < $intervalHours) {
        $hoursRemaining = round($intervalHours - $hoursSinceUpdate, 2);
        logTokenRefresh("⏭️  No es necesario renovar. Faltan $hoursRemaining horas");
        logTokenRefresh("=== Verificación completada (sin cambios) ===");
        return;
    }
    
    logTokenRefresh("⏰ Han pasado $hoursRounded horas. ¡Es momento de renovar!");
} else {
    logTokenRefresh("⚠️  No hay timestamp guardado. Primera ejecución o variable vacía");
}

// Función para obtener token de Shopify
function getShopifyToken($url) {
    logTokenRefresh("🔗 Conectando a Shopify: $url");
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin'
        ],
        CURLOPT_ENCODING => '', // Maneja automáticamente gzip/deflate
        CURLOPT_MAXREDIRS => 5
    ]);
    
    logTokenRefresh("📡 Enviando petición GET...");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        logTokenRefresh("ERROR cURL: $error", 'ERROR');
        throw new Exception("Error de conexión con Shopify: $error");
    }
    
    curl_close($ch);
    
    logTokenRefresh("📥 Respuesta recibida. HTTP Code: $httpCode");
    
    if ($httpCode !== 200) {
        logTokenRefresh("ERROR: Código HTTP inesperado $httpCode", 'ERROR');
        logTokenRefresh("Respuesta del servidor: " . substr($response, 0, 200), 'ERROR');
        throw new Exception("Error obteniendo token de Shopify. HTTP Code: $httpCode");
    }
    
    logTokenRefresh("✓ Respuesta HTTP 200 OK");
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logTokenRefresh("⚠️  La respuesta no es JSON válido. Usando respuesta como texto plano");
        $token = $response;
    } else {
        $token = $data['access_token'] ?? $data['token'] ?? $response;
        logTokenRefresh("✓ Token extraído del JSON");
    }
    
    $tokenPreview = substr($token, 0, 15) . "..." . substr($token, -5);
    logTokenRefresh("✓ Token obtenido: $tokenPreview");
    
    return $token;
}

// Función para actualizar variable en Railway
function updateRailwayEnvVar($token, $projectId, $envId, $varName, $newValue) {
    logTokenRefresh("🚂 Actualizando variable '$varName' en Railway...");
    
    $query = <<<'GRAPHQL'
    mutation VariableUpsert($input: VariableUpsertInput!) {
        variableUpsert(input: $input)
    }
    GRAPHQL;
    
    $variables = [
        'input' => [
            'projectId' => $projectId,
            'environmentId' => $envId,
            'name' => $varName,
            'value' => $newValue
        ]
    ];
    
    logTokenRefresh("   Project ID: $projectId");
    logTokenRefresh("   Environment ID: $envId");
    logTokenRefresh("   Variable: $varName");
    
    if ($varName === 'SHOPIFY_APP_TOKEN') {
        $valuePreview = substr($newValue, 0, 15) . "..." . substr($newValue, -5);
        logTokenRefresh("   Valor: $valuePreview");
    } else {
        logTokenRefresh("   Valor: $newValue");
    }
    
    $ch = curl_init('https://backboard.railway.app/graphql/v2');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'query' => $query,
            'variables' => $variables
        ])
    ]);
    
    logTokenRefresh("📡 Enviando mutación GraphQL a Railway...");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        logTokenRefresh("ERROR cURL Railway: $error", 'ERROR');
        throw new Exception("Error de conexión con Railway API: $error");
    }
    
    curl_close($ch);
    
    logTokenRefresh("📥 Respuesta de Railway recibida. HTTP Code: $httpCode");
    
    if ($httpCode !== 200) {
        logTokenRefresh("ERROR: Railway respondió con código $httpCode", 'ERROR');
        logTokenRefresh("Respuesta: " . substr($response, 0, 500), 'ERROR');
        throw new Exception("Error actualizando Railway. HTTP Code: $httpCode");
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['errors'])) {
        logTokenRefresh("ERROR GraphQL: " . json_encode($result['errors']), 'ERROR');
        throw new Exception("Error GraphQL en Railway: " . json_encode($result['errors']));
    }
    
    logTokenRefresh("✓ Variable '$varName' actualizada correctamente en Railway");
    
    return $result;
}

// Ejecutar actualización
try {
    logTokenRefresh("🚀 Iniciando proceso de renovación de token...");
    
    // Obtener nuevo token
    logTokenRefresh("--- PASO 1/3: Obteniendo token de Shopify ---");
    $newToken = getShopifyToken($shopifyUrl);
    logTokenRefresh("✓ PASO 1 COMPLETADO");
    
    // Actualizar token en Railway
    logTokenRefresh("--- PASO 2/3: Actualizando token en Railway ---");
    updateRailwayEnvVar(
        $railwayToken,
        $railwayProjectId,
        $railwayEnvironmentId,
        $tokenVarName,
        $newToken
    );
    logTokenRefresh("✓ PASO 2 COMPLETADO");
    
    // Actualizar timestamp en Railway
    logTokenRefresh("--- PASO 3/3: Actualizando timestamp en Railway ---");
    updateRailwayEnvVar(
        $railwayToken,
        $railwayProjectId,
        $railwayEnvironmentId,
        $timestampVarName,
        (string)$now
    );
    logTokenRefresh("✓ PASO 3 COMPLETADO");
    
    // Actualizar variables de entorno locales
    logTokenRefresh("📝 Actualizando variables de entorno locales...");
    putenv("$tokenVarName=$newToken");
    putenv("$timestampVarName=$now");
    logTokenRefresh("✓ Variables locales actualizadas");
    
    logTokenRefresh("🎉 ¡PROCESO COMPLETADO EXITOSAMENTE!");
    logTokenRefresh("   Nuevo token guardado");
    logTokenRefresh("   Timestamp: $now (" . date('Y-m-d H:i:s', $now) . ")");
    logTokenRefresh("   Próxima renovación en aproximadamente $intervalHours horas");
    logTokenRefresh("=== Renovación de token finalizada ===");
    
} catch (Exception $e) {
    logTokenRefresh("❌ ERROR CRÍTICO EN LA RENOVACIÓN", 'ERROR');
    logTokenRefresh("Mensaje: " . $e->getMessage(), 'ERROR');
    logTokenRefresh("Archivo: " . $e->getFile(), 'ERROR');
    logTokenRefresh("Línea: " . $e->getLine(), 'ERROR');
    logTokenRefresh("Stack trace:", 'ERROR');
    logTokenRefresh($e->getTraceAsString(), 'ERROR');
    logTokenRefresh("=== El proceso falló - se reintentará en la próxima ejecución ===", 'ERROR');
}
?>