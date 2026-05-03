<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            background: #ffffff;
            color: #1a1817;
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
        }
        .certificado {
            width: 297mm;
            height: 210mm;
            position: relative;
            background: #ffffff;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .bg-deco-1 {
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(214, 51, 132, 0.07);
            pointer-events: none;
        }
        .bg-deco-2 {
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 148, 189, 0.4);
            pointer-events: none;
        }
        .border-outer {
            position: absolute;
            inset: 15px;
            border: 3px solid rgba(214, 51, 132, 0.3);
            pointer-events: none;
        }
        .border-inner {
            position: absolute;
            inset: 22px;
            border: 1px solid rgba(214, 51, 132, 0.15);
            pointer-events: none;
        }
        .header-line {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            height: 4px;
            background: linear-gradient(90deg, #d63384, #ff94bd);
        }
        .content {
            position: relative;
            z-index: 10;
            padding: 50px 70px 40px;
            text-align: center;
            box-sizing: border-box;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            letter-spacing: 2.4px;
            text-transform: uppercase;
            color: #d63384;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .titulo {
            font-size: 36px;
            font-weight: 300;
            color: #1a1817;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .subtitulo {
            font-size: 12px;
            letter-spacing: 2.2px;
            text-transform: uppercase;
            color: #737373;
            margin-bottom: 28px;
        }
        .divider {
            width: 80px;
            height: 1px;
            background: rgba(214, 51, 132, 0.3);
            margin: 0 auto 24px;
        }
        .otorga {
            font-size: 14px;
            color: #737373;
            margin-bottom: 6px;
        }
        .nombre {
            font-size: 38px;
            font-weight: 300;
            color: #1a1817;
            border-bottom: 2px solid #d63384;
            display: inline-block;
            padding: 0 48px 8px;
            margin: 6px 0 24px;
            letter-spacing: -0.3px;
        }
        .texto {
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .curso-nombre {
            font-size: 20px;
            font-weight: 600;
            color: #d63384;
            margin-bottom: 4px;
        }
        .curso-codigo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            color: #65002f;
            background: #ffd9e2;
            padding: 3px 10px;
            border-radius: 2px;
            margin-bottom: 20px;
        }
        .fecha {
            font-size: 12px;
            color: #737373;
            margin-top: 22px;
        }
        .firma-section {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .linea-firma {
            border-top: 1.5px solid #d63384;
            width: 220px;
            margin-bottom: 8px;
        }
        .nombre-firma {
            font-size: 14px;
            font-weight: 600;
            color: #1a1817;
        }
        .cargo-firma {
            font-size: 11px;
            color: #737373;
            margin-top: 2px;
        }
        .footer-note {
            position: absolute;
            bottom: 28px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #a3a3a3;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="certificado">
        <div class="bg-deco-1"></div>
        <div class="bg-deco-2"></div>
        <div class="header-line"></div>
        <div class="border-outer"></div>
        <div class="border-inner"></div>

        <div class="content">
            <div class="badge">
                ✓ Certificado Oficial
            </div>

            <div class="titulo">Certificado de Graduación</div>
            <div class="subtitulo">Instituto de la Mujer, Atención a la Familia y Formación para el Trabajo</div>

            <div class="divider"></div>

            <div class="otorga">Se certifica que</div>
            <div class="nombre">{{ $estudiante->nombre }}</div>

            <div class="texto">
                ha completado satisfactoriamente el curso
            </div>

            <div class="curso-nombre">{{ $curso->nombre }}</div>
            <div class="curso-codigo">
                <span style="font-size:10px;">#</span> {{ $curso->codigo }}
            </div>

            <div class="texto" style="font-size:12px; color:#737373;">
                cumpliendo con todos los requisitos académicos establecidos
            </div>

            <div class="fecha">
                Emitido el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
            </div>

            <div class="firma-section">
                <div class="linea-firma"></div>
                <div class="nombre-firma">{{ $profesor->user->name ?? $profesor->nombre ?? 'Instructor' }}</div>
                <div class="cargo-firma">Instructor del curso</div>
            </div>
        </div>

        <div class="footer-note">
            Instituto IMAF · Documento generado digitalmente · {{ now()->format('Y') }}
        </div>
    </div>
</body>
</html>
