<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/debug', function() {
    try {
        // Test database connection
        \DB::connection()->getPdo();
        echo "Database connected successfully!<br>";
        
        // Test if migrations ran
        $tables = \DB::select('SHOW TABLES');
        echo "Tables in database: " . count($tables) . "<br>";
        
        foreach($tables as $table) {
            foreach($table as $key => $value) {
                echo "Table: " . $value . "<br>";
            }
        }
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
});

// Add to routes/web.php
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});