<?php
class SchemaCache {
    private static $cacheFile = __DIR__ . '/schema_cache.json';
    private static $cacheDuration = 86400; // 24 horas

    public static function getSchema() {
        // Si el caché es válido, retornarlo
        if (file_exists(self::$cacheFile) && 
            (time() - filemtime(self::$cacheFile)) < self::$cacheDuration) {
            return json_decode(file_get_contents(self::$cacheFile), true);
        }
        return self::actualizarCache();
    }

    public static function actualizarCache() {
        $db = new Database();
        $conn = $db->connect();

        $tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);
        $schema = [];

        foreach ($tables as $table) {
            $tableName = $table[0];
            $columns = $conn->query("SHOW COLUMNS FROM $tableName")->fetch_all(MYSQLI_ASSOC);
            $schema[$tableName] = array_column($columns, 'Field');
        }

        file_put_contents(self::$cacheFile, json_encode($schema));
        return $schema;
    }
}