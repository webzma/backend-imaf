<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            background: #fff;
            color: #1a1a2e;
            margin: 0;
            padding: 40px;
        }
        .border {
            border: 6px double #1a1a2e;
            padding: 40px;
            min-height: 600px;
            text-align: center;
        }
        .titulo {
            font-size: 32px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 10px;
            color: #1a1a2e;
        }
        .subtitulo {
            font-size: 14px;
            color: #555;
            margin-bottom: 40px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .otorga {
            font-size: 16px;
            color: #555;
            margin-bottom: 10px;
        }
        .nombre {
            font-size: 36px;
            font-style: italic;
            border-bottom: 2px solid #1a1a2e;
            display: inline-block;
            padding: 0 40px 8px;
            margin: 10px 0 30px;
            color: #1a1a2e;
        }
        .texto {
            font-size: 15px;
            color: #444;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .curso {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a2e;
        }
        .fecha {
            font-size: 13px;
            color: #888;
            margin-top: 40px;
        }
        .firma {
            margin-top: 60px;
            display: inline-block;
            text-align: center;
        }
        .linea-firma {
            border-top: 1px solid #1a1a2e;
            width: 200px;
            margin: 0 auto 6px;
        }
        .nombre-firma {
            font-size: 13px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="border">
        <div class="titulo">Certificado de Graduación</div>
        <div class="subtitulo">Instituto IMAF</div>

        <div class="otorga">Se certifica que</div>
        <div class="nombre">{{ $estudiante->nombre }}</div>

        <div class="texto">
            ha completado satisfactoriamente el curso<br>
            <span class="curso">{{ $curso->nombre }}</span><br>
            cumpliendo con todos los requisitos académicos establecidos.
        </div>

        <div class="fecha">
            Emitido el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
        </div>

        <div class="firma">
            <div class="linea-firma"></div>
            <div class="nombre-firma">{{ $profesor->user->name ?? $profesor->nombre ?? 'Instructor' }}</div>
            <div class="nombre-firma" style="color:#aaa; font-size:11px;">Profesor del curso</div>
        </div>
    </div>
</body>
</html>
