<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Restablecimiento de contraseña</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f6f9fc;color:#333;margin:0;padding:0}
        .container{max-width:560px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #eaeaea}
        .header{background:#0e6efd;color:#fff;padding:16px 20px}
        .content{padding:20px}
        .btn{display:inline-block;background:#0e6efd;color:#fff !important;text-decoration:none;padding:10px 16px;border-radius:6px;margin:12px 0}
        .muted{color:#6c757d;font-size:12px}
        .code{font-family:monospace;background:#f1f3f5;border-radius:6px;padding:8px 12px;display:inline-block}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Restablecimiento de contraseña</h2>
        </div>
        <div class="content">
            <p>Hola {{ $user->nombre ?? 'usuario' }},</p>
            <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>

            <p>Tu token de restablecimiento es:</p>
            <p class="code">{{ $token }}</p>

            @php
                $resetUrl = (config('app.url') ?: url('/')) . '/reset-password?token=' . urlencode($token);
            @endphp

            <p>Puedes utilizar el siguiente botón para continuar con el proceso:</p>
            <p>
                <a class="btn" href="{{ $resetUrl }}" target="_blank" rel="noopener">Restablecer contraseña</a>
            </p>

            <p class="muted">Si no has solicitado este cambio, puedes ignorar este mensaje.</p>
            <p class="muted">Este enlace es válido hasta que generes un nuevo token.</p>
        </div>
    </div>
</body>
</html>
