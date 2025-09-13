<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test login
function testLogin($email, $password) {
    echo "\nAttempting to login with email: {$email}\n";
    
    $client = new GuzzleHttp\Client([
        'base_uri' => 'http://localhost:8000',
        'http_errors' => false,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'timeout' => 10,
        'connect_timeout' => 5,
    ]);

    try {
        echo "Sending request to /api/login...\n";
        
        $response = $client->post('/api/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
            'debug' => true
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();
        
        echo "\n=== Login Response ===\n";
        echo "Status Code: {$statusCode}\n";
        
        if ($statusCode === 200) {
            if (isset($body['status']) && $body['status'] === true) {
                echo "✅ Login successful!\n";
                echo "User ID: " . ($body['data']['user']['id'] ?? 'N/A') . "\n";
                echo "User Name: " . ($body['data']['user']['nombre'] ?? 'N/A') . " " . ($body['data']['user']['apellido'] ?? '') . "\n";
                
                if (isset($body['data']['hospital'])) {
                    echo "🏥 Hospital: " . ($body['data']['hospital']['nombre'] ?? 'N/A') . " (ID: " . ($body['data']['hospital']['id'] ?? 'N/A') . ")\n";
                } else {
                    echo "⚠️ No hospital assigned to this user\n";
                }
                
                if (isset($body['data']['sede'])) {
                    echo "🏢 Sede: " . ($body['data']['sede']['nombre'] ?? 'N/A') . " (ID: " . ($body['data']['sede']['id'] ?? 'N/A') . ")\n";
                } else {
                    echo "⚠️ No sede assigned to this user\n";
                }
            } else {
                echo "❌ Login failed: " . ($body['mensaje'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "❌ Error response: " . $statusCode . "\n";
            if (isset($body['error'])) {
                echo "Error details: " . print_r($body['error'], true) . "\n";
            }
        }
        
        echo "\nFull response:\n";
        print_r($body);
        
        return $body;
    } catch (Exception $e) {
        echo "\n❌ Exception during login: " . $e->getMessage() . "\n";
        if ($e->hasResponse()) {
            echo "Response: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
        return null;
    }
}

// Update admin user with hospital and sede
function updateAdminUser() {
    try {
        // Get first hospital and sede
        $hospital = DB::table('hospitales')->first();
        $sede = DB::table('sedes')->where('hospital_id', $hospital->id)->first();
        
        if (!$hospital || !$sede) {
            echo "Error: No hay hospitales o sedes en la base de datos.\n";
            exit(1);
        }
        
        // Update admin user
        DB::table('users')
            ->where('email', 'admin@example.com')
            ->update([
                'hospital_id' => $hospital->id,
                'sede_id' => $sede->id,
                'updated_at' => now()
            ]);
            
        echo "Admin user updated with hospital_id: {$hospital->id} and sede_id: {$sede->id}\n";
        
    } catch (Exception $e) {
        echo "Error updating admin user: " . $e->getMessage() . "\n";
    }
}

// Run the test
updateAdminUser();
echo "\nTesting login with admin@example.com...\n";
testLogin('admin@example.com', 'password');
