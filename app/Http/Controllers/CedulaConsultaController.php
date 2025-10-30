<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CedulaConsultaController extends Controller
{
    /**
     * Consultar cédula venezolana mediante scraping
     * POST /api/cedula/consultar
     */
    public function consultar(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'numeric', 'digits_between:6,8'],
        ]);

        $cedula = $validated['cedula'];

        try {
            // 1. Obtener la página inicial para extraer el CAPTCHA
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                ])
                ->get('https://www.sistemaspnp.com/cedula/');

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Error al conectar con el servicio de consulta.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $html = $response->body();

            // 2. Extraer la pregunta del CAPTCHA usando regex
            preg_match('/CAPTCHA:\s*¿Cuánto es\s*(\d+)\s*\+\s*(\d+)\?/', $html, $matches);

            if (count($matches) < 3) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se pudo extraer el CAPTCHA de la página.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $num1 = (int) $matches[1];
            $num2 = (int) $matches[2];
            $captchaRespuesta = $num1 + $num2;

            Log::info('CAPTCHA detectado', [
                'num1' => $num1,
                'num2' => $num2,
                'respuesta' => $captchaRespuesta
            ]);

            // 3. Enviar el formulario con los datos
            $responsePost = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Referer' => 'https://www.sistemaspnp.com/cedula/',
                ])
                ->post('https://www.sistemaspnp.com/cedula/resultado.php', [
                    'cedula' => $cedula,
                    'captcha' => $captchaRespuesta,
                    'jeje' => '', // Campo honeypot
                ]);

            if (!$responsePost->successful()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Error al consultar la cédula.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $htmlResultado = $responsePost->body();

            // 4. Extraer los datos del resultado usando regex
            $datos = $this->extraerDatos($htmlResultado);

            if (empty($datos)) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se encontraron datos para la cédula proporcionada.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Datos de cédula obtenidos exitosamente.',
                'data' => $datos,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Error en consulta de cédula', [
                'cedula' => $cedula,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar la consulta: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Extraer datos del HTML de resultado
     */
    private function extraerDatos(string $html): array
    {
        $datos = [];

        // Extraer Cédula
        if (preg_match('/<strong>Cédula:<\/strong>\s*(\d+)/', $html, $matches)) {
            $datos['cedula'] = $matches[1];
        }

        // Extraer RIF
        if (preg_match('/<strong>RIF:<\/strong>\s*([VEJGvejg]\d+)/', $html, $matches)) {
            $datos['rif'] = strtoupper($matches[1]);
        }

        // Extraer Primer Apellido
        if (preg_match('/<strong>Primer Apellido:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['primer_apellido'] = trim($matches[1]);
        }

        // Extraer Segundo Apellido
        if (preg_match('/<strong>Segundo Apellido:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['segundo_apellido'] = trim($matches[1]);
        }

        // Extraer Nombres
        if (preg_match('/<strong>Nombres:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['nombres'] = trim($matches[1]);
        }

        // Extraer Estado
        if (preg_match('/<strong>Estado:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['estado'] = trim($matches[1]);
        }

        // Extraer Municipio
        if (preg_match('/<strong>Municipio:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['municipio'] = trim($matches[1]);
        }

        // Extraer Parroquia
        if (preg_match('/<strong>Parroquia:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['parroquia'] = trim($matches[1]);
        }

        // Extraer Centro Electoral
        if (preg_match('/<strong>Centro Electoral:<\/strong>\s*([^<]+)/', $html, $matches)) {
            $datos['centro_electoral'] = trim($matches[1]);
        }

        return $datos;
    }
}
